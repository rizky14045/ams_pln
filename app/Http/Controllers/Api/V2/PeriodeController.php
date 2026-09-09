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
use App\Services\PeriodeService;
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
        try {
            // Buat periode + generate slot periode_asset untuk seluruh asset
            // extra comptable (di dalam DB transaction).
            $periode = app(PeriodeService::class)->create($request->year);

            return ResponseHelper::response(
                'Berhasil membuat periode', // messages
                null, // errors
                $periode, // data
                201 // status_code
            );

        } catch (Exception $e) {

            return ResponseHelper::response(
                'Gagal membuat periode', // messages
                $e->getMessage(), // errors
                null, // data
                500 // status_code
            );
        }
    }

    public function show(Request $request, $periodeId)
    {
        try {
            // 1. Cek keberadaan periode
            $periode = Periode::find($periodeId);

            if (!$periode) {
                return ResponseHelper::response(
                    'Periode tidak ditemukan',
                    null,
                    null,
                    404
                );
            }

            // 2. Base Query dengan Eager Loading
            $query = PeriodeAsset::with([
                'asset',
                'asset.jenis',
                'asset.ruang',
                'asset.gedung',
                'asset.subjenis',
                'scanBy'
            ])->where('periode_id', $periodeId);

            // 3. Filter / Search Global (Kode Asset, Nama Asset, Gedung, Lantai, Ruang, Jenis, Subjenis)
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;

                $query->whereHas('asset', function ($q) use ($search) {
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

            // 4. Eksekusi pagination dan append query string
            $assetCount = $query->paginate(10)->appends($request->all());

            $data = [
                'periode'     => $periode,
                'asset_count' => $assetCount
            ];

            return ResponseHelper::response(
                'Berhasil mendapatkan data periode',
                null,
                $data,
                200
            );

        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data periode',
                $e->getMessage(),
                null,
                500
            );
        }
    }

}