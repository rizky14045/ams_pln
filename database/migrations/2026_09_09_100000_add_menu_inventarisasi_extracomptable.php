<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan menu sidebar "Inventarisasi Extra Comptable" tepat DI ATAS
 * menu "Asset Extra Comptable" dan "Report Extra Comptable".
 *
 * CrudBooster menyimpan menu di tabel cms_menus; urutan tampil mengikuti
 * kolom `sorting` dalam lingkup `parent_id` yang sama. Menu "Asset Extra
 * Comptable" & "Report Extra Comptable" adalah anak dari sebuah grup menu,
 * jadi menu baru ini disisipkan sebagai anak pertama pada grup tersebut.
 */
class AddMenuInventarisasiExtracomptable extends Migration
{
    const MENU_NAME = 'Inventarisasi Extra Comptable';
    const MENU_PATH = 'periode-inventarisasi::index'; // route name (type = Route)

    public function up()
    {
        if (DB::table('cms_menus')->where('name', self::MENU_NAME)->exists()) {
            return;
        }

        // Cari menu "Asset Extra Comptable" / "Report Extra Comptable" yang sudah ada
        // untuk menentukan parent group + posisi sisip.
        $anchor = DB::table('cms_menus')
            ->where(function ($q) {
                $q->where('name', 'like', '%Extra Comptable%')
                  ->orWhere('name', 'like', '%Extracomptable%')
                  ->orWhere('path', 'like', '%asset_extracomptable%')
                  ->orWhere('path', 'like', '%extracomptable%')
                  ->orWhere('path', 'like', '%Extracomptable%');
            })
            ->orderBy('parent_id', 'desc') // utamakan yang berada di dalam grup (parent_id != 0)
            ->orderBy('sorting', 'asc')
            ->first();

        $parentId = $anchor ? (int) $anchor->parent_id : 0;

        $targetSorting = (int) DB::table('cms_menus')
            ->where('parent_id', $parentId)
            ->where(function ($q) {
                $q->where('name', 'like', '%Extra Comptable%')
                  ->orWhere('name', 'like', '%Extracomptable%')
                  ->orWhere('path', 'like', '%extracomptable%')
                  ->orWhere('path', 'like', '%Extracomptable%');
            })
            ->min('sorting');

        if (!$targetSorting) {
            $targetSorting = 1;
        }

        // Beri ruang: geser menu satu-parent yang berada pada / di bawah posisi target.
        DB::table('cms_menus')
            ->where('parent_id', $parentId)
            ->where('sorting', '>=', $targetSorting)
            ->increment('sorting');

        $now = date('Y-m-d H:i:s');

        $menuId = DB::table('cms_menus')->insertGetId([
            'name'         => self::MENU_NAME,
            'type'         => 'Route',
            'path'         => self::MENU_PATH,
            'color'        => null,
            'icon'         => 'fa fa-check-square-o',
            'parent_id'    => $parentId,
            'is_active'    => 1,
            'is_dashboard' => 0,
            'sorting'      => $targetSorting,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // Beri akses ke semua privilege supaya menu tampil di sidebar.
        foreach (DB::table('cms_privileges')->pluck('id') as $privilegeId) {
            DB::table('cms_menus_privileges')->insert([
                'id_cms_menus'      => $menuId,
                'id_cms_privileges' => $privilegeId,
            ]);
        }
    }

    public function down()
    {
        $menu = DB::table('cms_menus')->where('name', self::MENU_NAME)->first();
        if (!$menu) {
            return;
        }

        DB::table('cms_menus_privileges')->where('id_cms_menus', $menu->id)->delete();
        DB::table('cms_menus')->where('id', $menu->id)->delete();

        // Rapikan kembali urutan menu sesama parent.
        DB::table('cms_menus')
            ->where('parent_id', $menu->parent_id)
            ->where('sorting', '>', $menu->sorting)
            ->decrement('sorting');
    }
}
