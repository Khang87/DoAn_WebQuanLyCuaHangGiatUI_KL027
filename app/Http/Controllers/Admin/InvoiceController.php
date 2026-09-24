<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $invoices = $this->invoiceService->getAll([
            'order_id' => $request->input('order_id'),
            'status' => $request->input('status'),
            'search' => $request->input('search'),
        ]);

        $orders = \App\Models\Order::orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.index', compact('invoices', 'orders', 'statuses'));
    }

    public function export(Request $request)
    {
        $invoices = Invoice::query()
            ->with('order.customer')
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->input('search'), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('code', 'LIKE', '%' . $s . '%')
                        ->orWhere('notes', 'LIKE', '%' . $s . '%')
                        ->orWhereHas('order.customer', fn($c) => $c->where('name', 'LIKE', '%' . $s . '%'));
                });
            })
            ->latest()
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sách hóa đơn');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3056D3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $headers = ['STT', 'Mã hóa đơn', 'Khách hàng', 'Mã đơn', 'Tổng tiền', 'Trạng thái', 'Ngày lập'];
        $col = 1;
        foreach ($headers as $h) {
            $coordinate = Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->getCell($coordinate)->setValue($h);
            $sheet->getStyle($coordinate)->applyFromArray($headerStyle);
            $col++;
        }
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($invoices as $i => $invoice) {
            $sheet->getCell('A' . $row)->setValue($i + 1);
            $sheet->getCell('B' . $row)->setValue($invoice->code);
            $sheet->getCell('C' . $row)->setValue($invoice->order?->customer?->name ?? 'N/A');
            $sheet->getCell('D' . $row)->setValue($invoice->order?->code ?? '-');
            $sheet->getCell('E' . $row)->setValue((float) $invoice->total);
            $sheet->getCell('F' . $row)->setValue($invoice->status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán');
            $sheet->getCell('G' . $row)->setValue($invoice->created_at?->format('d/m/Y H:i') ?? '');
            $row++;
        }

        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('B:B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G:G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'Danh_Sach_Hoa_Don_' . now()->format('Y_m_d') . '.xlsx';

        $response = new StreamedResponse();
        $response->setCallback(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    public function create()
    {
        $orders = \App\Models\Order::whereDoesntHave('invoice')->orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.create', compact('orders', 'statuses'));
    }

    public function store(InvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        $orders = \App\Models\Order::orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.edit', compact('invoice', 'orders', 'statuses'));
    }

    public function update(InvoiceRequest $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        try {
            $this->invoiceService->update($invoice, $request->validated());

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.edit', $invoice)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if ($invoice) {
            try {
                $this->invoiceService->delete($invoice);
            } catch (\Exception $e) {
                return redirect()->route('invoices.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            }
        }

        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được xóa.');
    }
}
