<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PricingRequest;
use App\Models\Pricing;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index()
    {
        return view('admin.pricings.index', ['pricings' => Pricing::latest()->get()]);
    }

    public function create()
    {
        return view('admin.pricings.create');
    }

    public function store(PricingRequest $request)
    {
        try {
            Pricing::create($request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        return view('admin.pricings.show', ['pricing' => Pricing::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.pricings.edit', ['pricing' => Pricing::findOrFail($id)]);
    }

    public function update(PricingRequest $request, $id)
    {
        try {
            Pricing::findOrFail($id)->update($request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            Pricing::findOrFail($id)->delete();

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
