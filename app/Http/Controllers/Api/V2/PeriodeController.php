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

class PeriodeController extends ApiController
{

    public function getPeriode()
    {
        $periode = Periode::all();
        return ResponseHelper::response(
            'Berhasil mendapatkan data periode', // messages
            null, // errors
            $periode, // data
            200 // status_code
        );
    }

    public function createPeriode(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|unique:periode,year',
        ]);

        // Format error validasi disesuaikan menggunakan ResponseHelper
        if ($validator->fails()) {
            return ResponseHelper::response(
                'Gagal membuat periode', // messages
                $validator->errors(),   // errors (mengembalikan objek invalid)
                null,                    // data
                422                      // status_code (Unprocessable Entity)
            );
        }
        DB::beginTransaction();

        try {
            // 1. Buat data periode
            $periode = Periode::create([
                'year' => $request->year,
            ]);

            // 2. Ambil semua asset_id sekaligus
            $assetIds = AssetExtracomptable::pluck('id');

            $now = now();
            $periodeAssets = [];

            // 3. Susun array data untuk batch insert
            foreach ($assetIds as $assetId) {
                $periodeAssets[] = [
                    'periode_id' => $periode->id,
                    'asset_id'   => $assetId,
                    'status'     => null,
                    'tanggal_inventaris' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 4. Lakukan batch insert per 1.000 record agar tidak melebihi limit query
            foreach (array_chunk($periodeAssets, 1000) as $chunk) {
                PeriodeAsset::insert($chunk);
            }

            DB::commit();

            return ResponseHelper::response(
                'Berhasil membuat periode', // messages
                null, // errors
                $periode, // data
                201 // status_code
            );

        } catch (Exception $e) {
            DB::rollBack();

            return ResponseHelper::response(
                'Gagal membuat periode', // messages
                $e->getMessage(), // errors
                null, // data
                500 // status_code
            );
        }
    }

    public function show($periodeId)
    {
        $periode = Periode::find($periodeId);
        $assetCount = PeriodeAsset::with('asset','asset.jenis','asset.ruang','asset.gedung','asset.subjenis','scanBy')->where('periode_id', $periodeId)->paginate(10);

        $data = [
            'periode' => $periode,
            'asset_count' => $assetCount
        ];

        if (!$periode) {
            return ResponseHelper::response(
                'Periode tidak ditemukan', // messages
                null, // errors
                null, // data
                404 // status_code
            );
        }

        return ResponseHelper::response(
            'Berhasil mendapatkan data periode', // messages
            null, // errors
            $data, // data
            200 // status_code
        );
    }

}