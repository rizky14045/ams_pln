<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyembunyikan menu sidebar "Report Extra Comptable"
 * (halaman /asset/extracomptable/report) tanpa menghapusnya.
 *
 * Route halaman tsb juga dinonaktifkan di routes/web.php.
 * Untuk mengaktifkan kembali: set is_active = 1 di sini + uncomment
 * route 'page-report' / 'json-get-report' / 'download-report'.
 */
class HideMenuReportExtracomptable extends Migration
{
    /**
     * Cari baris menu "Report Extra Comptable".
     */
    protected function menuQuery()
    {
        return DB::table('cms_menus')->where(function ($q) {
            $q->where('path', 'like', '%asset/extracomptable/report%')
              ->orWhere('path', 'asset-extracomptable::page-report')
              ->orWhere('name', 'Report Extra Comptable');
        });
    }

    public function up()
    {
        (clone $this->menuQuery())->update(['is_active' => 0]);
    }

    public function down()
    {
        (clone $this->menuQuery())->update(['is_active' => 1]);
    }
}
