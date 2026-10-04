<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class QuyenController extends Controller
{
    public function __construct(
        private RoleService $roleService,
    ) {}

    public function index(): View
    {
        Gate::authorize('roles.manage');

        $permissions = $this->roleService->getAllPermissions()->loadCount('vaiTros');

        return view('admin.permissions.index', compact('permissions'));
    }
}
