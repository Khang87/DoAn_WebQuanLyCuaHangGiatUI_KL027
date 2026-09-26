<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarmentConditionRequest;
use App\Models\Garment;
use App\Models\GarmentCondition;
use App\Services\GarmentConditionService;
use Illuminate\Http\Request;

class GarmentConditionController extends Controller
{
    public function __construct(
        private GarmentConditionService $conditionService,
    ) {}

    public function index(Request $request)
    {
        $conditions = $this->conditionService->getAll([
            'garment_id' => $request->input('garment_id'),
            'condition_type' => $request->input('condition_type'),
            'status' => $request->input('status'),
        ]);

        $garments = Garment::where('status', 'active')->orderBy('name')->get();

        $statuses = RecordStatus::options();

        return view('admin.garment-conditions.index', compact('conditions', 'garments', 'statuses'));
    }

    public function create()
    {
        $garments = Garment::where('status', 'active')->orderBy('name')->get();

        return view('admin.garment-conditions.create', compact('garments'));
    }

    public function store(GarmentConditionRequest $request)
    {
        try {
            $this->conditionService->create($request->validated());

            return redirect()->route('garment-conditions.index')->with('success', 'Điều kiện đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('garment-conditions.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $condition = $this->conditionService->find($id);

        if (!$condition) {
            abort(404);
        }

        return view('admin.garment-conditions.show', compact('condition'));
    }

    public function edit(int $id)
    {
        $condition = $this->conditionService->find($id);

        if (!$condition) {
            abort(404);
        }

        $garments = Garment::where('status', 'active')->orderBy('name')->get();

        return view('admin.garment-conditions.edit', compact('condition', 'garments'));
    }

    public function update(GarmentConditionRequest $request, int $id)
    {
        $condition = $this->conditionService->find($id);

        if (!$condition) {
            abort(404);
        }

        try {
            $this->conditionService->update($condition, $request->validated());

            return redirect()->route('garment-conditions.index')->with('success', 'Điều kiện đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('garment-conditions.edit', $condition)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $condition = $this->conditionService->find($id);

        if (!$condition) {
            abort(404);
        }

        try {
            $this->conditionService->delete($condition);

            return redirect()->route('garment-conditions.index')->with('success', 'Đã xóa điều kiện.');
        } catch (\Exception $e) {
            return redirect()->route('garment-conditions.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
