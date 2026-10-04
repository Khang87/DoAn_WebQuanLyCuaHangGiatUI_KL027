<?php

namespace Database\Seeders;

use App\Models\Quyen;
use App\Models\VaiTro;
use App\Support\PermissionRegistry;
use App\Support\QuyenMapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RbacCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsByCode = [];

        foreach (PermissionRegistry::groups() as $permissions) {
            foreach ($permissions as $code => $name) {
                $maQuyen = QuyenMapper::resolveMaQuyen($code) ?? QuyenMapper::toMaQuyen($code);
                $permission = Quyen::query()->firstOrCreate(
                    ['MaQuyen' => $maQuyen],
                    ['TenQuyen' => $name, 'TrangThai' => 'Hoạt động'],
                );
                $permissionsByCode[$code] = $permission;
            }
        }

        $accountantRole = VaiTro::query()->firstOrCreate(
            ['TenVaiTro' => 'Kế toán'],
            [
                'MoTa' => 'Theo dõi hóa đơn, thanh toán và báo cáo kế toán.',
                'TrangThai' => 'Hoạt động',
            ],
        );

        $accountantPermissionCodes = [
            'dashboard.view',
            'reports.view',
            'invoices.view',
            'payments.view',
            'accounting.view',
        ];
        $permissionIds = collect($accountantPermissionCodes)
            ->map(fn (string $code) => ($permissionsByCode[$code] ?? null)?->getKey())
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($permissionIds as $permissionId) {
            DB::table('VaiTro_Quyen')->insertOrIgnore([
                'VaiTroID' => $accountantRole->getKey(),
                'QuyenID' => $permissionId,
            ]);
        }
    }
}
