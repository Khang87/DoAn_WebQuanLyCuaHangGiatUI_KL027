<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'TaiKhoan_VaiTro_TaiKhoanID_unique';

    public function up(): void
    {
        $duplicate = DB::table('TaiKhoan_VaiTro')
            ->select('TaiKhoanID')
            ->groupBy('TaiKhoanID')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                'Cannot enforce single-role accounts while TaiKhoanID '.$duplicate->TaiKhoanID.' has multiple roles.',
            );
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS "'.self::INDEX_NAME.'" ON public."TaiKhoan_VaiTro" ("TaiKhoanID")');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS public."'.self::INDEX_NAME.'"');
    }
};
