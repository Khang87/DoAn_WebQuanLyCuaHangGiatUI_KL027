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
            'order_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = isset($validated['order_id'])
            ? $this->messageService->findOrder((int) $validated['order_id'])
            : null;

        if (isset($validated['order_id']) && $order === null) {
            abort(404);
        }

        return view('admin.messages.index', [
            'orders' => $this->messageService->getOrders(),
            'selectedOrder' => $order,
            'messages' => $order ? $this->messageService->getMessages($order) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'min:1', 'exists:DonHang,DonHangID'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

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

    public function updates(Request $request, int $order): JsonResponse
    {
        $conversation = DonHang::query()->findOrFail($order);

        return response()->json([
            'order_id' => (int) $conversation->getKey(),
            'messages' => $this->messageService->getMessages($conversation)
                ->map(fn ($message): array => [
                    'id' => (int) $message->getKey(),
                    'content' => (string) $message->NoiDung,
                    'sender_name' => (int) $message->NguoiGuiID === (int) $request->user()->getKey()
                        ? 'Cửa hàng' : ($message->sender?->TenDangNhap ?: 'Khách hàng'),
                    'is_mine' => (int) $message->NguoiGuiID === (int) $request->user()->getKey(),
                    'sent_at' => $message->ThoiGianGui?->format('d-m-Y H:i'),
                ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
