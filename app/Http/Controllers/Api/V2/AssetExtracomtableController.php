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
        $assetExtracomtable = AssetExtracomptable::with('jenis', 'ruang', 'gedung', 'subjenis','latestPeriodeAsset')
        ->paginate(10);
        $data['assetExtracomtable'] = $assetExtracomtable;
        return ResponseHelper::response(
            'Berhasil mendapatkan data asset extracomptable', // messages
            null, // errors
            $data, // data
            200 // status_code
        );
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