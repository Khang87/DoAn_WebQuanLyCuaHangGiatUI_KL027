<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SePayController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function webhook(Request $request): Response
    {
        $apiKey = trim((string) config('services.sepay.webhook_api_key'));
        $authorization = trim((string) $request->header('Authorization'));

        if ($apiKey === '') {
            return response()->json(['success' => false, 'message' => 'Webhook is not configured.'], 503);
        }

        if (! hash_equals('Apikey '.$apiKey, $authorization)) {
            Log::warning('SePay webhook authentication rejected.', [
                'authorization_header_present' => $authorization !== '',
                'content_type' => $request->header('Content-Type'),
            ]);

            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        // SePay sends the payment reference in `code` when available; some bank
        // feeds only include it in `content`.
        $prefix = strtoupper((string) config('services.payments.payment_prefix', 'DH'));
        $referencePattern = '/(?<![A-Z0-9])'.preg_quote($prefix, '/').'([0-9]{8})(?![0-9])/';
        $referenceText = strtoupper(implode(' ', array_filter([
            (string) ($payload['code'] ?? ''),
            (string) ($payload['content'] ?? ''),
        ])));

        if (! preg_match($referencePattern, $referenceText, $matches)) {
            // Ignore ordinary bank movements without an order code so SePay
            // does not retry unrelated transactions indefinitely.
            return response()->json(['success' => true, 'ignored' => true]);
        }

        if (($payload['transferType'] ?? null) !== 'in') {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        $transactionId = trim((string) ($payload['id'] ?? ''));
        $referenceCode = trim((string) ($payload['referenceCode'] ?? ''));
        $transactionReference = 'SEPAY-'.($transactionId !== '' ? $transactionId : $referenceCode);
        $receivedAmount = filter_var($payload['transferAmount'] ?? null, FILTER_VALIDATE_FLOAT);
        $configuredAccount = preg_replace('/\D+/', '', (string) config('services.vietqr.account_number'));
        $receivedAccount = preg_replace('/\D+/', '', (string) ($payload['accountNumber'] ?? ''));

        if ($transactionReference === 'SEPAY-' || $receivedAmount === false || $receivedAmount <= 0
            || $configuredAccount === '' || $receivedAccount === '') {
            return response()->json(['success' => false, 'message' => 'Incomplete transaction data.'], 422);
        }

        if (! hash_equals($configuredAccount, $receivedAccount)) {
            Log::warning('SePay transaction sent for a different receiving account.', [
                'sepay_transaction_id' => $transactionId,
            ]);

            return response()->json(['success' => false, 'message' => 'Transaction was sent to another account.'], 422);
        }

        $orderCode = $prefix.$matches[1];

        try {
            DB::transaction(function () use ($matches, $transactionReference, $receivedAmount, $payload): void {
                $order = DonHang::query()->with('booking')->lockForUpdate()->findOrFail((int) $matches[1]);
                $pendingPayment = ThanhToan::query()
                    ->where('DonHangID', $order->DonHangID)
                    ->where('PhuongThuc', 'Chuyển khoản')
                    ->where('TrangThai', PaymentStatus::Pending->value)
                    ->orderByDesc('ThoiGian')
                    ->lockForUpdate()
                    ->first();
                if ($order->booking?->PhuongThucThanhToan !== 'Chuyển khoản' && ! $pendingPayment) {
                    throw new \DomainException('This order was not configured for bank transfer.');
                }

                $invoice = HoaDon::query()->where('DonHangID', $order->DonHangID)->lockForUpdate()->first();
                if (! $invoice || InvoiceStatus::parse($invoice->TrangThai) !== InvoiceStatus::Unpaid) {
                    if ($invoice && InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Paid) {
                        return;
                    }
                    throw new \DomainException('No unpaid invoice is available for this order.');
                }

                if ($order->statusEnum() === OrderStatus::Cancelled) {
                    throw new \DomainException('Payment is not available for a cancelled order.');
                }

                if (round((float) $receivedAmount, 2) !== round((float) $invoice->ThanhTien, 2)) {
                    throw new \DomainException('The received amount does not match the invoice.');
                }

                $existingTransaction = ThanhToan::query()->where('MaGiaoDich', $transactionReference)->first();
                if ($existingTransaction) {
                    if ((int) $existingTransaction->DonHangID === (int) $order->DonHangID) {
                        return;
                    }
                    throw new \DomainException('The bank transaction reference was already used.');
                }

                $attributes = [
                    'amount' => (float) $invoice->ThanhTien,
                    'method' => 'bank_transfer',
                    'transaction_code' => $transactionReference,
                    'paid_at' => now(),
                    'status' => PaymentStatus::Paid->value,
                    'notes' => 'SePay: '.mb_substr((string) ($payload['content'] ?? $payload['description'] ?? 'Bank transfer confirmed'), 0, 400),
                ];

                if ($pendingPayment) {
                    $this->paymentService->update($pendingPayment, $attributes);
                } else {
                    $this->paymentService->create([
                        ...$attributes,
                        'order_id' => $order->DonHangID,
                        'invoice_id' => $invoice->HoaDonID,
                    ]);
                }

                if ($order->statusEnum() === OrderStatus::Delivered) {
                    $order->update([
                        'TrangThai' => OrderStatus::Paid->value,
                        'NgayCapNhat' => now(),
                    ]);
                }
            });
        } catch (\DomainException $exception) {
            Log::warning('SePay transaction rejected.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error('SePay webhook processing failed.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                // Keep the primary cause visible in providers that truncate
                // long log entries (the full exception trace can hide it).
                'exception_class' => $exception::class,
                'exception_message' => mb_substr($exception->getMessage(), 0, 500),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            return response()->json(['success' => false, 'message' => 'Could not process payment.'], 500);
        }

        return response()->json(['success' => true]);
    }
}
