<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class GarmentConditionController extends Controller
{
    public function index()
    {
        return view('admin.garment-conditions.index');
    }

    public function create()
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }

    public function store()
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }

    public function show(int $id)
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }

    public function edit(int $id)
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }

    public function update(int $id)
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }

    public function destroy(int $id)
    {
        abort(404, 'Bảng điều kiện đồ giặt chưa tồn tại trong schema Supabase hiện tại.');
    }
}
