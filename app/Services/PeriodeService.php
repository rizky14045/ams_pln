<?php

namespace App\Services;

use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\PeriodeAsset;
use Illuminate\Support\Facades\DB;

/**
 * Logika inti periode inventarisasi extra comptable.
 *
 * Dipakai bersama oleh:
 * - Web  : App\Http\Controllers\PeriodeInventarisasiController
 * - API  : App\Http\Controllers\Api\V2\PeriodeController (POST /api/v2/periode)
 *
 * Sama seperti API: saat periode dibuat, setiap asset extra comptable
 * otomatis mendapat 1 baris "slot" di periode_asset (status kosong) yang
 * nanti diisi petugas saat inventarisasi.
 */
class PeriodeService
{
    /**
     * Buat periode baru + generate slot periode_asset untuk SEMUA asset extra comptable.
     * (Logika identik dengan API v2 createPeriode.)
     *
     * @param  int|string  $year
     * @return \App\Models\Periode
     */
    public function create($year)
    {
        return DB::transaction(function () use ($year) {
            $periode = Periode::create(['year' => $year]);

            $this->generateAssetSlots($periode);

            return $periode;
        });
    }

    /**
     * Perbarui periode (ubah tahun) lalu lengkapi slot periode_asset untuk
     * asset yang belum tercatat pada periode ini (mis. asset baru dibuat
     * setelah periode dibentuk). Slot yang sudah diisi tidak disentuh.
     *
     * @param  \App\Models\Periode  $periode
     * @param  int|string  $year
     * @return \App\Models\Periode
     */
    public function update(Periode $periode, $year)
    {
        return DB::transaction(function () use ($periode, $year) {
            $periode->year = $year;
            $periode->save();

            $this->generateAssetSlots($periode);

            return $periode;
        });
    }

    /**
     * Insert baris periode_asset (status = null) untuk setiap asset extra
     * comptable yang belum punya slot pada periode ini.
     * Batch insert per 1.000 baris — sama seperti API.
     *
     * @param  \App\Models\Periode  $periode
     * @return int  jumlah slot baru yang dibuat
     */
    public function generateAssetSlots(Periode $periode)
    {
        $existingAssetIds = PeriodeAsset::where('periode_id', $periode->id)
            ->pluck('asset_id')
            ->all();

        $query = AssetExtracomptable::query();
        if (!empty($existingAssetIds)) {
            $query->whereNotIn('id', $existingAssetIds);
        }
        $assetIds = $query->pluck('id');

        if ($assetIds->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $assetIds->map(function ($assetId) use ($periode, $now) {
            return [
                'periode_id'         => $periode->id,
                'asset_id'           => $assetId,
                'status'             => null,
                'tanggal_inventaris' => null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        })->all();

        foreach (array_chunk($rows, 1000) as $chunk) {
            PeriodeAsset::insert($chunk);
        }

        return count($rows);
    }
}
