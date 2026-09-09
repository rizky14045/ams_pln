<?php



namespace App\Http\Controllers\Api\V2;

use App\Helper\ResponseHelper;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Concerns\Authenticator;
use App\Http\Controllers\Controller;
use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\PeriodeAsset;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AssetExtracomtableController extends ApiController
{

    public function getAssets(Request $request)
    {
        try {
            // 1. Base Query dengan Eager Loading
            $query = AssetExtracomptable::with([
                'jenis',
                'ruang',
                'gedung',
                'subjenis',
                'latestPeriodeAsset'
            ]);

            // 2. Filter / Search Global (Kode Asset, Nama Asset, Lantai, Gedung, Ruang, Jenis, Subjenis)
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('kd_asset', 'like', "%{$search}%")
                    ->orWhere('nama_asset', 'like', "%{$search}%")
                    ->orWhere('lantai', 'like', "%{$search}%")
                    // Filter Gedung
                    ->orWhereHas('gedung', function ($g) use ($search) {
                        $g->where('nama', 'like', "%{$search}%");
                    })
                    // Filter Ruang
                    ->orWhereHas('ruang', function ($r) use ($search) {
                        $r->where('nama_ruang', 'like', "%{$search}%");
                    })
                    // Filter Jenis
                    ->orWhereHas('jenis', function ($j) use ($search) {
                        $j->where('nama', 'like', "%{$search}%");
                    })
                    // Filter Subjenis
                    ->orWhereHas('subjenis', function ($sj) use ($search) {
                        $sj->where('nama', 'like', "%{$search}%");
                    });
                });
            }

            // 3. Eksekusi pagination dan append query string
            $assetExtracomtable = $query->paginate(10)->appends($request->all());

            $data['assetExtracomtable'] = $assetExtracomtable;

            return ResponseHelper::response(
                'Berhasil mendapatkan data asset extracomptable', // messages
                null,                                             // errors
                $data,                                            // data
                200                                               // status_code
            );

        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data asset extracomptable',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    public function GetDetailAsset($code)
    {
        $assetExtracomtable = AssetExtracomptable::with('jenis', 'ruang', 'gedung', 'subjenis','latestPeriodeAsset')
        ->where('kd_asset', $code)
        ->first();
        if (!$assetExtracomtable) {
            return ResponseHelper::response(
                'Asset tidak ditemukan!', // messages
                null, // errors
                null, // data
                404 // status_code
            );
        }
        $data['assetExtracomtable'] = $assetExtracomtable;
        return ResponseHelper::response(
            'Berhasil mendapatkan data asset extracomptable', // messages
            null, // errors
            $data, // data
            200 // status_code
        );
    }

    public function updateStatus(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'periode' => 'required',
            'code'    => 'required',
            'status'  => 'required',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::response(
                'Gagal memperbarui status asset',
                $validator->errors(),
                null,
                422
            );
        }

        // 2. Cek Keberadaan Asset
        $assetExtracomtable = AssetExtracomptable::where('kd_asset', $request->code)->first();
        if (!$assetExtracomtable) {
            return ResponseHelper::response(
                'Asset tidak ditemukan!',
                null,
                null,
                404
            );
        }
        $periode = Periode::where('year', $request->periode)->first();
        if (!$periode) {
            return ResponseHelper::response(
                'Periode tidak ditemukan!',
                null,
                null,
                404
            );
        }

        // 3. Cek Keberadaan Periode Asset
        $periodeAsset = PeriodeAsset::where('periode_id', $periode->id)
            ->where('asset_id', $assetExtracomtable->id)
            ->first();

        if (!$periodeAsset) {
            return ResponseHelper::response(
                'Periode asset tidak ditemukan!',
                null,
                null,
                404
            );
        }

        // 4. Proses Update dengan Try-Catch & Database Transaction
        DB::beginTransaction();

        try {
            $periodeAsset->status  = $request->status;
            $periodeAsset->scan_by = Auth::id();
            $periodeAsset->tanggal_inventaris = now();
            $periodeAsset->save();

            DB::commit();

            return ResponseHelper::response(
                'Berhasil memperbarui status asset',
                null,
                $periodeAsset,
                200
            );

        } catch (Exception $e) {
            DB::rollBack();

            return ResponseHelper::response(
                'Gagal memperbarui status asset',
                $e->getMessage(),
                null,
                500
            );
        }
    }

}