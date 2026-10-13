<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\BankTransferReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BeePayController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function config(): JsonResponse
    {
        $bankBin = trim((string) config('services.vietqr.bank_bin'));
        $accountNumber = trim((string) config('services.vietqr.account_number'));
        $accountName = trim((string) config('services.vietqr.account_name'));

        return response()->json([
            'enabled' => $bankBin === '970422' && ctype_digit($accountNumber) && $accountName !== '',
            'bank_bin' => $bankBin,
            'bank_name' => config('services.vietqr.bank_name'),
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'payment_prefix' => strtoupper((string) config('services.beepay.payment_prefix', 'DH')),
        ]);
    }

    public function webhook(Request $request): Response
    {
        $secret = (string) config('services.beepay.webhook_secret');
        $configuredAccount = preg_replace('/\D+/', '', (string) config('services.vietqr.account_number'));
        if ($secret === '' || $configuredAccount === '') {
            return response()->json(['ok' => false, 'error' => 'Webhook is not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $signature = (string) ($request->header('X-Webhook-Signature')
            ?: $request->header('X-BeePay-Signature'));
        $providedHash = preg_replace('/^sha256=/i', '', trim($signature));
        $expectedHash = hash_hmac('sha256', $rawBody, $secret);

        if ($providedHash === '' || ! hash_equals($expectedHash, $providedHash)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature.'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid JSON.'], 400);
        }

        if (($payload['event'] ?? null) === 'test') {
            return response()->json(['ok' => true]);
        }

        $validation = Validator::make($payload, [
            'order_id' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:40'],
            'transaction_type' => ['required', 'string'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'tx_id' => ['nullable', 'string', 'max:100'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:30'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);
        if ($validation->fails()) {
            return response()->json(['ok' => false, 'error' => 'Incomplete or invalid transaction data.'], 422);
        }

        $prefix = preg_quote(strtoupper((string) config('services.beepay.payment_prefix', 'DH')), '/');
        $orderCode = strtoupper(trim((string) ($payload['order_id'] ?? $payload['reference'] ?? '')));
        if (! preg_match('/^'.$prefix.'(\d{8,10})$/', $orderCode, $matches)) {
            return response()->json(['ok' => false, 'error' => 'Unknown payment reference.'], 422);
        }

        if ((int) $matches[1] < 1 || (int) $matches[1] > 2147483647) {
            return response()->json(['ok' => false, 'error' => 'Unknown payment reference.'], 422);
        }

        if ($payload['transaction_type'] !== 'credit') {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $transactionReference = trim((string) ($payload['transaction_ref'] ?? $payload['tx_id'] ?? $payload['transaction_id'] ?? ''));
        $receivedAmount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($transactionReference === '' || $receivedAmount === false || $receivedAmount <= 0 || ! is_finite($receivedAmount) || mb_strlen($transactionReference) > 100) {
            return response()->json(['ok' => false, 'error' => 'Incomplete transaction data.'], 422);
        }

        $receivedAccount = preg_replace('/\D+/', '', (string) ($payload['account_number'] ?? ''));
        if ($receivedAccount === '' || ! hash_equals($configuredAccount, $receivedAccount)) {
            return response()->json(['ok' => false, 'error' => 'Transaction was sent to another account.'], 422);
        }

        $actorId = filter_var(config('services.beepay.audit_account_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $actor = $actorId === false ? null : User::query()->find($actorId);
        if (! $actor || ! $actor->NhanVienID || $actor->KhachHangID || ! $actor->canPermission('payments.create')) {
            return response()->json(['ok' => false, 'error' => 'An active payment audit account must be configured.'], 503);
        }

        try {
            DB::transaction(function () use ($matches, $orderCode, $transactionReference, $receivedAmount, $payload, $actor): void {
                // Serialize this bank reference across orders; locking only the order cannot deduplicate cross-order deliveries.
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['beepay:'.$transactionReference]);
                }
                $order = DonHang::query()->lockForUpdate()->find((int) $matches[1]);
                if (! $order) {
                    throw new \DomainException('Unknown payment reference.');
                }
                if (BankTransferReference::forOrder((int) $order->getKey()) !== $orderCode) {
                    throw new \DomainException('Unknown payment reference.');
                }
                $invoice = HoaDon::query()->where('DonHangID', $order->DonHangID)->lockForUpdate()->first();
                if (! $invoice || $order->statusEnum() === OrderStatus::Cancelled
                    || InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Cancelled) {
                    throw new \DomainException('Payment is not available for this order.');
                }
                if (round((float) $receivedAmount, 2) !== round((float) $invoice->ThanhTien, 2)) {
                    throw new \DomainException('The received amount does not match the invoice.');
                }
                $existing = ThanhToan::query()->where('MaGiaoDich', $transactionReference)->lockForUpdate()->first();
                if ($existing && (int) $existing->DonHangID !== (int) $order->DonHangID) {
                    throw new \DomainException('The bank transaction reference was already used.');
                }
                if ($existing && $existing->isFinanciallyFinal()) {
                    if ($existing->TrangThai === PaymentStatus::Paid->value
                        && $existing->PhuongThuc === 'Chuyển khoản'
                        && round((float) $existing->SoTien, 2) === round((float) $receivedAmount, 2)
                        && InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Paid) {
                        return;
                    }
                    throw new \DomainException('The existing transaction does not match this credit.');
                }
                if (InvoiceStatus::parse($invoice->TrangThai) !== InvoiceStatus::Unpaid) {
                    throw new \DomainException('Invoice is already settled; this additional transfer requires reconciliation.');
                }
                $pendingPayment = $existing ?? ThanhToan::query()
                    ->where('DonHangID', $order->DonHangID)
                    ->where('TrangThai', PaymentStatus::Pending->value)
                    ->orderByDesc('ThoiGian')->lockForUpdate()->first();
                $attributes = [
                    'amount' => (float) $invoice->ThanhTien,
                    'method' => 'bank_transfer',
                    'transaction_code' => $transactionReference,
                    'paid_at' => now(),
                    'status' => PaymentStatus::Paid->value,
                    'notes' => 'BeePay: '.mb_substr((string) ($payload['description'] ?? 'Bank transfer confirmed'), 0, 400),
                ];
                if ($pendingPayment) {
                    $this->paymentService->update($pendingPayment, $attributes, auditActor: $actor);
                } else {
                    $this->paymentService->create([
                        ...$attributes,
                        'order_id' => $order->DonHangID,
                        'invoice_id' => $invoice->HoaDonID,
                    ], auditActor: $actor);
                }
                if ($order->statusEnum() === OrderStatus::Delivered) {
                    $order->update(['TrangThai' => OrderStatus::Paid->value, 'NgayCapNhat' => now()]);
                }
            });
        } catch (\DomainException|ValidationException $exception) {
            Log::warning('BeePay transaction rejected.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                'reason' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error('BeePay webhook processing failed.', [
                'order_id' => $orderCode,
                'transaction_ref' => $transactionReference,
                'exception' => $exception::class,
            ]);

            return response()->json(['ok' => false, 'error' => 'Could not process payment.'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
