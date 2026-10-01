<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuBangGiaRequest;
use App\Models\BangGia;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\LoaiDoGiat;
use App\Services\PricingService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class BangGiaController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function index(Request $request)
    {
        $pricings = $this->pricingService->getAll([
            'search' => $request->input('search'),
            'service_id' => $request->input('service_id'),
            'garment_id' => $request->input('garment_id'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $statuses = [
            'Hoạt động' => 'Hoạt động',
            'Hết hiệu lực' => 'Hết hiệu lực',
            'Tạm ngưng' => 'Tạm ngưng',
        ];
        $units = BangGia::unitOptions();

        return view('admin.pricings.index', compact('pricings', 'services', 'garments', 'statuses', 'units'));
    }

    public function create()
    {
        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $units = DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();

        return view('admin.pricings.create', compact('services', 'garments', 'units'));
    }

    public function store(LuuBangGiaRequest $request)
    {
        try {
            $this->pricingService->create($request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (! $pricing) {
            abort(404);
        }

        return view('admin.pricings.show', compact('pricing'));
    }

    public function edit(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (! $pricing) {
            abort(404);
        }

        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $units = DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();

        return view('admin.pricings.edit', compact('pricing', 'services', 'garments', 'units'));
    }

    public function update(LuuBangGiaRequest $request, int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (! $pricing) {
            abort(404);
        }

        try {
            $this->pricingService->update($pricing, $request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.edit', $id)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (! $pricing) {
            abort(404);
        }

        try {
            $this->pricingService->delete($pricing);

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.index')->with('error', FriendlyError::message($e));
        }
    }
}
