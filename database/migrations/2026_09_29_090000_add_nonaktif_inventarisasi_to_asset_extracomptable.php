<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * "Hapus" asset extracomptable yang TIDAK menghilangkan asset dari tampilan
 * mana pun (list, detail, histori, laporan) — cuma menandai supaya asset itu
 * tidak ikut disertakan saat periode inventarisasi BARU dibuat/di-sync
 * (App\Services\PeriodeService::generateAssetSlots()).
 *
 * nonaktif_inventarisasi_at berisi timestamp = asset dikecualikan.
 * null = asset ikut disertakan seperti biasa.
 */
class AddNonaktifInventarisasiToAssetExtracomptable extends Migration
{
    public function up()
    {
        Schema::table('asset_extracomptable', function (Blueprint $table) {
            $table->timestamp('nonaktif_inventarisasi_at')->nullable()->after('ref_id_request');
            $table->integer('nonaktif_inventarisasi_by')->nullable()->after('nonaktif_inventarisasi_at');
        });
    }

    public function down()
    {
        Schema::table('asset_extracomptable', function (Blueprint $table) {
            $table->dropColumn(['nonaktif_inventarisasi_at', 'nonaktif_inventarisasi_by']);
        });
    }
}
