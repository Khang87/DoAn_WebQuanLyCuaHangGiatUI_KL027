<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\TinNhan;
use App\Models\User;
use App\Services\ChatMessageDecryptionException;
use App\Services\MessageRealtimeService;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(
        private MessageService $messageService,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'integer', 'min:1', 'prohibits:order_id'],
            'q' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'in:orders,support'],
        ]);
        $search = trim($validated['q'] ?? '');

        $order = isset($validated['order_id'])
            ? $this->messageService->findOrder((int) $validated['order_id'])
            : null;

        if (isset($validated['order_id']) && $order === null) {
            abort(404);
        }

        $customer = isset($validated['customer_id']) ? $this->messageService->supportCustomer((int) $validated['customer_id']) : null;

        $messageDecryptionUnavailable = false;

        try {
            $messages = $order
                ? $this->messageService->getMessages($order)
                : ($customer ? $this->messageService->supportMessages($customer) : collect());
        } catch (\Throwable $exception) {
            report($exception);
            $messages = collect();
            $messageDecryptionUnavailable = true;
        }

        return view('admin.messages.index', [
            'orders' => $this->messageService->getOrders($search),
            'selectedOrder' => $order,
            'selectedCustomer' => $customer,
            'supportCustomers' => $this->messageService->supportCustomers($search),
            'activeTab' => $customer ? 'support' : ($order ? 'orders' : ($validated['tab'] ?? 'orders')),
            'search' => $search,
            'messages' => $messages,
            'messageDecryptionUnavailable' => $messageDecryptionUnavailable,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            return $this->storeMessage($request);
        } catch (\Throwable $exception) {
            if (! $exception instanceof ValidationException) {
                Log::error('Web chat send failed.', [
                    'exception' => $exception::class,
                    'reason' => mb_substr($exception->getMessage(), 0, 400),
                    'location' => basename($exception->getFile()).':'.$exception->getLine(),
                ]);
            }
            throw $exception;
        }
    }

    private function storeMessage(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'prohibits:customer_id', 'integer', 'min:1', 'exists:DonHang,DonHangID'],
            'customer_id' => ['nullable', 'required_without:order_id', 'prohibits:order_id', 'integer', 'min:1'],
            'content' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'image_path' => ['nullable', 'string', 'max:100', 'regex:/^\d+\/[a-f0-9-]+\.(jpg|png|webp)$/'],
        ]);
        if (! $request->hasFile('image') && empty($validated['image_path']) && trim((string) ($validated['content'] ?? '')) === '') {
            throw ValidationException::withMessages(['content' => 'Vui lòng nhập tin nhắn hoặc chọn ảnh.']);
        }

        if (isset($validated['customer_id'])) {
            $customer = $this->messageService->supportCustomer((int) $validated['customer_id']);
            if (! empty($validated['image_path'])) {
                abort_unless(str_starts_with($validated['image_path'], $request->user()->getAuthIdentifier().'/'), 403);
                $this->messageService->sendSupportImagePath($customer, $request->user(), $validated['image_path']);
            } elseif ($request->hasFile('image')) {
                $this->messageService->sendSupportImage($customer, $request->user(), $request->file('image'));
            } else {
                try {
                    $this->messageService->sendSupport($customer, $request->user(), $validated['content']);
                } catch (ChatMessageDecryptionException $exception) {
                    return $this->messageCryptoUnavailable($request, $exception);
                }
            }

            return $request->expectsJson()
                ? response()->json(['ok' => true])
                : redirect()->route('admin.messages.index', ['customer_id' => $customer->getKey()]);
        }

        $order = $this->messageService->findOrder((int) $validated['order_id']);

        if (! $order) {
            abort(404);
        }

        $sender = $request->user();

        if (! $sender instanceof User) {
            abort(403);
        }

        if (! empty($validated['image_path'])) {
            abort_unless(str_starts_with($validated['image_path'], $sender->getAuthIdentifier().'/'), 403);
            $this->messageService->sendOrderImagePath($order, $sender, $validated['image_path']);
        } elseif ($request->hasFile('image')) {
            $this->messageService->sendOrderImage($order, $sender, $request->file('image'));
        } else {
            try {
                $this->messageService->sendFromStore($order, $sender, $validated['content']);
            } catch (ChatMessageDecryptionException $exception) {
                return $this->messageCryptoUnavailable($request, $exception);
            }
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('admin.messages.index', ['order_id' => $order->DonHangID]);
    }

    private function messageCryptoUnavailable(Request $request, ChatMessageDecryptionException $exception): JsonResponse|RedirectResponse
    {
        report($exception);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Chưa thể mã hóa tin nhắn. Vui lòng thử lại sau.',
            ], 503)->header('Cache-Control', 'no-store, private');
        }

        return back()->with('error', 'Chưa thể mã hóa tin nhắn. Vui lòng thử lại sau.');
    }

    public function hide(TinNhan $message, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
        ]);
        $this->assertConversationContains($message, $validated);
        $this->messageService->hideForAccount(
            (int) $message->getKey(),
            (int) $request->user()->getAuthIdentifier(),
        );

        return response()->json(['ok' => true])->header('Cache-Control', 'private, no-store');
    }

    public function imageUploadUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
            'mime_type' => ['required', 'in:image/jpeg,image/png,image/webp'],
        ]);
        if (isset($validated['customer_id'])) {
            $this->messageService->supportCustomer((int) $validated['customer_id']);
        } else {
            $order = $this->messageService->findOrder((int) $validated['order_id']);
            abort_unless($order && $order->khachHang?->taiKhoan, 404);
        }
        $sender = $request->user();
        abort_unless($sender instanceof User, 403);

        return response()->json($this->messageService->createImageUploadUrl($sender, $validated['mime_type']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function recall(TinNhan $message, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
        ]);
        $this->assertConversationContains($message, $validated);
        if ((int) $message->NguoiGuiID !== (int) $request->user()->getAuthIdentifier()) {
            abort(403);
        }
        $this->messageService->recall($message);

        return response()->json(['ok' => true])->header('Cache-Control', 'private, no-store');
    }

    public function edit(TinNhan $message, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);
        $this->assertConversationContains($message, $validated);
        abort_unless((int) $message->NguoiGuiID === (int) $request->user()->getAuthIdentifier(), 403);
        $this->messageService->edit($message, $validated['content']);

        return response()->json(['ok' => true])->header('Cache-Control', 'private, no-store');
    }

    /** @param array{order_id?: int|null, customer_id?: int|null} $validated */
    private function assertConversationContains(TinNhan $message, array $validated): void
    {
        if (isset($validated['order_id'])) {
            abort_unless((int) $message->DonHangID === (int) $validated['order_id'], 404);
            DonHang::query()->findOrFail((int) $validated['order_id']);

            return;
        }
        $customer = $this->messageService->supportCustomer((int) $validated['customer_id']);
        abort_unless($message->DonHangID === null && in_array((int) $customer->getKey(), [(int) $message->NguoiGuiID, (int) $message->NguoiNhanID], true), 404);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['unread_count' => $this->messageService->unreadCount()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function inbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['orders' => $this->messageService->getOrders(trim($validated['q'] ?? ''))->map(fn ($order) => [
            'id' => (int) $order->getKey(), 'number' => (string) $order->MaDonHang,
            'customer' => $order->khachHang?->HoTen ?: 'Khách hàng',
            'preview' => $order->last_message_preview,
            'unread' => (int) $order->unread_count > 0,
            'date' => $order->last_message_at ? Carbon::parse($order->last_message_at, 'UTC')->setTimezone(config('app.timezone'))->format('d/m/Y') : $order->NgayTao?->format('d/m/Y'),
        ])->values()])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
            'message_ids' => ['required', 'array', 'min:1', 'max:100'],
            'message_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $orderId = isset($validated['order_id']) ? (int) $validated['order_id'] : null;
        $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
        if ($orderId !== null) {
            DonHang::query()->findOrFail($orderId);
        } else {
            $this->messageService->supportCustomer($customerId);
        }

        return response()->json(['updated' => $this->messageService->markRead($orderId, $customerId, $validated['message_ids'])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function realtime(Request $request, MessageRealtimeService $realtime): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'integer', 'min:1', 'prohibits:customer_id'],
            'customer_id' => ['nullable', 'required_without:order_id', 'integer', 'min:1', 'prohibits:order_id'],
        ]);
        if (isset($validated['order_id'])) {
            $order = DonHang::query()->findOrFail($validated['order_id']);
            $scope = 'order:'.$order->getKey();
        } else {
            $customer = $this->messageService->supportCustomer((int) $validated['customer_id']);
            $scope = 'support:'.$customer->getKey();
        }
        try {
            $configuration = $realtime->configuration($scope);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Realtime temporarily unavailable'], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json($configuration)->header('Cache-Control', 'private, no-store');
    }

    public function supportUpdates(Request $request, int $customer): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(403);
        }

        $account = $this->messageService->supportCustomer($customer);

        try {
            $messages = $this->messageService->supportMessages($account)->map(fn ($message): array => [
                'id' => (int) $message->getKey(), 'content' => (string) $message->NoiDung,
                'image_url' => $message->ImageUrl,
                'recalled' => (bool) ($message->DaThuHoi ?? false),
                'sender_name' => $this->messageService->displayName($message, $viewer),
                'is_mine' => (int) $message->NguoiGuiID === (int) $viewer->getKey(),
                'sent_at' => $this->messageService->sentAt($message)?->toIso8601String(),
            ])->values();
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Chưa thể tải nội dung hội thoại. Vui lòng thử lại sau.'], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json([
            'order_id' => null,
            'customer_account_id' => (int) $account->getKey(),
            'messages' => $messages,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function orderUpdates(Request $request, int $order): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            abort(403);
        }

        $conversation = DonHang::query()->findOrFail($order);

        try {
            $messages = $this->messageService->getMessages($conversation)
                ->map(fn ($message): array => [
                    'id' => (int) $message->getKey(),
                    'content' => (string) $message->NoiDung,
                    'image_url' => $message->ImageUrl,
                    'recalled' => (bool) ($message->DaThuHoi ?? false),
                    'sender_name' => $this->messageService->displayName($message, $viewer),
                    'is_mine' => (int) $message->NguoiGuiID === (int) $viewer->getKey(),
                    'sent_at' => $this->messageService->sentAt($message)?->toIso8601String(),
                ])->values();
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Chưa thể tải nội dung hội thoại. Vui lòng thử lại sau.'], 503)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json([
            'order_id' => (int) $conversation->getKey(),
            'messages' => $messages,
        ])->header('Cache-Control', 'private, no-store');
    }
}
