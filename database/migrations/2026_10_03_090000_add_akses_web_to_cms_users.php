<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Whitelist akses WEB (admin panel). Hanya user dengan akses_web = 1 yang
 * boleh login ke web. API mobile (JWT) tidak memakai kolom ini.
 *
 * User yang sudah ada diset 1 supaya tidak ada yang terkunci keluar;
 * selanjutnya atur lewat menu Users Management.
 */
class AddAksesWebToCmsUsers extends Migration
{
    public function up()
    {
        Schema::table('cms_users', function (Blueprint $table) {
            $table->tinyInteger('akses_web')->default(0)->after('id_user_group');
        });

        DB::table('cms_users')->update(['akses_web' => 1]);
    }

    public function down()
    {
        Schema::table('cms_users', function (Blueprint $table) {
            $table->dropColumn('akses_web');
        });
    }
}
