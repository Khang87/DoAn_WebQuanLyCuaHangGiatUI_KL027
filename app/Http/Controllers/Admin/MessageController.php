<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $order = isset($validated['order_id'])
            ? $this->messageService->findOrder((int) $validated['order_id'])
            : null;

        if (isset($validated['order_id']) && $order === null) {
            abort(404);
        }

        $customer = isset($validated['customer_id']) ? $this->messageService->supportCustomer((int) $validated['customer_id']) : null;

        return view('admin.messages.index', [
            'orders' => $this->messageService->getOrders(),
            'selectedOrder' => $order,
            'selectedCustomer' => $customer,
            'supportCustomers' => $this->messageService->supportCustomers(),
            'messages' => $order ? $this->messageService->getMessages($order) : ($customer ? $this->messageService->supportMessages($customer) : collect()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['nullable', 'required_without:customer_id', 'prohibits:customer_id', 'integer', 'min:1', 'exists:DonHang,DonHangID'],
            'customer_id' => ['nullable', 'required_without:order_id', 'prohibits:order_id', 'integer', 'min:1'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        if (isset($validated['customer_id'])) {
            $customer = $this->messageService->supportCustomer((int) $validated['customer_id']);
            $this->messageService->sendSupport($customer, $request->user(), $validated['content']);

            return redirect()->route('admin.messages.index', ['customer_id' => $customer->getKey()])
                ->with('success', 'Tin nhắn hỗ trợ đã được gửi.');
        }

        $order = $this->messageService->findOrder((int) $validated['order_id']);

        if (! $order) {
            abort(404);
        }

        $sender = $request->user();

        if (! $sender instanceof User) {
            abort(403);
        }

        $this->messageService->sendFromStore($order, $sender, $validated['content']);

        return redirect()
            ->route('admin.messages.index', ['order_id' => $order->DonHangID])
            ->with('success', 'Tin nhắn đã được gửi cho khách hàng.');
    }

    public function supportUpdates(Request $request, int $customer): JsonResponse
    {
        $account = $this->messageService->supportCustomer($customer);

        return response()->json([
            'order_id' => null,
            'customer_account_id' => (int) $account->getKey(),
            'messages' => $this->messageService->supportMessages($account)->map(fn ($message): array => [
                'id' => (int) $message->getKey(), 'content' => (string) $message->NoiDung,
                'sender_name' => $this->messageService->displayName($message, $request->user()),
                'is_mine' => (int) $message->NguoiGuiID === (int) $request->user()->getKey(),
                'sent_at' => $message->ThoiGianGui?->format('d-m-Y H:i'),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updates(Request $request, int $order): JsonResponse
    {
        $conversation = DonHang::query()->findOrFail($order);

        return response()->json([
            'order_id' => (int) $conversation->getKey(),
            'messages' => $this->messageService->getMessages($conversation)
                ->map(fn ($message): array => [
                    'id' => (int) $message->getKey(),
                    'content' => (string) $message->NoiDung,
                    'sender_name' => $this->messageService->displayName($message, $request->user()),
                    'is_mine' => (int) $message->NguoiGuiID === (int) $request->user()->getKey(),
                    'sent_at' => $message->ThoiGianGui?->format('d-m-Y H:i'),
                ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
