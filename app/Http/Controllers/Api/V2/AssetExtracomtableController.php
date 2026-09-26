<?php



namespace App\Http\Controllers\Api\V2;

use App\Helper\ResponseHelper;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Concerns\Authenticator;
use App\Http\Controllers\Controller;
use App\Models\AssetExtracomptable;
use App\Models\Gedung;
use App\Models\JenisExtracomptable;
use App\Models\Periode;
use App\Models\PeriodeAsset;
use App\Models\Ruang;
use App\Models\SubJenisExtracomptable;
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

    public function getKodeAsset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_gedung' => 'required|exists:gedung,id',
            'lantai' => 'required',
            'id_ruang' => 'required|exists:ruang,id',
            'id_jenis' => 'required|exists:jenis_extracomptable,id',
            'id_subjenis' => 'required|exists:subjenis_extracomptable,id',
            'tgl_masuk' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::response(
                'Gagal membuat kode asset',
                $validator->errors(),
                null,
                422
            );
        }

        $date = $request->get('tgl_masuk')
            ? new \DateTime($request->get('tgl_masuk'))
            : null;

        $kdAsset = AssetExtracomptable::generateKodeAsset(
            Gedung::findOrFail($request->get('id_gedung')),
            $request->get('lantai'),
            Ruang::findOrFail($request->get('id_ruang')),
            JenisExtracomptable::findOrFail($request->get('id_jenis')),
            SubJenisExtracomptable::findOrFail($request->get('id_subjenis')),
            $date
        );

        return ResponseHelper::response(
            'Berhasil membuat kode asset',
            null,
            ['kd_asset' => $kdAsset],
            200
        );
    }

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
            if($request->has('date') && $request->date != '') {
                $date = Carbon::parse($request->date)->format('Y-m-d');
                $query->whereHas('periodeAssets', function ($q) use ($date) {
                    $q->whereDate('tanggal_inventaris', $date);
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

    public function createAsset(Request $request)
    {
        // 1. Validasi Input sesuai skema
        $validator = Validator::make($request->all(), [
            'periode'     => 'required',
            'id_gedung'   => 'required|exists:gedung,id',
            'lantai'      => 'required',
            'id_ruang'    => 'required|exists:ruang,id',
            'id_jenis'    => 'required|exists:jenis_extracomptable,id',
            'id_subjenis' => 'required|exists:subjenis_extracomptable,id',
            'nama_asset'  => 'required|string',
            'kd_asset'    => 'required|string|unique:asset_extracomptable,kd_asset',
            'status'      => 'required|string',
            'gambar'      => 'required',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::response(
                'Gagal menambahkan data asset',
                $validator->errors(),
                null,
                422
            );
        }

        // 2. Cek Keberadaan Periode
        $periode = Periode::where('year', $request->periode)->first();
        if (!$periode) {
            $periode = Periode::find($request->periode);
        }

        if (!$periode) {
            return ResponseHelper::response(
                'Periode tidak ditemukan!',
                null,
                null,
                404
            );
        }

        // 3. Proses upload / penyimpanan gambar
        $uploadPath = public_path('uploads/assets-extracomptable');
        $filename = null;

        try {
            if ($request->hasFile('gambar')) {
                $file = $request->file('gambar');
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = implode('-', [md5($request->kd_asset), date('ymd'), uniqid()]) . '.' . $ext;
                $file->move($uploadPath, $filename);
            } elseif (is_string($request->gambar) && !empty($request->gambar)) {
                $gambarData = $request->gambar;
                $ext = 'jpg';

                if (preg_match('/^data:image\/(\w+);base64,/', $gambarData, $type)) {
                    $gambarData = substr($gambarData, strpos($gambarData, ',') + 1);
                    $ext = strtolower($type[1]);
                    if ($ext === 'jpeg') {
                        $ext = 'jpg';
                    }
                }

                $gambarData = str_replace(' ', '+', $gambarData);
                $imageContent = base64_decode($gambarData);
                if ($imageContent === false) {
                    return ResponseHelper::response(
                        'Format base64 gambar tidak valid!',
                        null,
                        null,
                        422
                    );
                }

                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $filename = implode('-', [md5($request->kd_asset), date('ymd'), uniqid()]) . '.' . $ext;
                file_put_contents($uploadPath . '/' . $filename, $imageContent);
            } else {
                return ResponseHelper::response(
                    'Gambar tidak valid!',
                    null,
                    null,
                    422
                );
            }
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mengunggah gambar: ' . $e->getMessage(),
                $e->getMessage(),
                null,
                500
            );
        }

        // 4. Simpan Asset & PeriodeAsset dalam Database Transaction
        DB::beginTransaction();

        try {
            $asset = new AssetExtracomptable();
            $asset->id_gedung = $request->id_gedung;
            $asset->lantai = $request->lantai;
            $asset->id_ruang = $request->id_ruang;
            $asset->id_jenis = $request->id_jenis;
            $asset->id_subjenis = $request->id_subjenis;
            $asset->kd_asset = $request->kd_asset;
            $asset->nama_asset = $request->nama_asset;
            $asset->tgl_masuk = Carbon::now()->toDateString();
            $asset->status = $request->status;
            $asset->gambar = $filename;
            $asset->ref_id_request = $request->ref_id_request ?: null;
            $asset->save();

            // Log activity create
            $userId = Auth::id();
            try {
                $asset->logCreate($userId);
            } catch (Exception $logEx) {
                // Abaikan jika log gagal agar pembuatan asset tidak terhambat
            }

            // Buat / update record PeriodeAsset untuk periode yang ditentukan
            $periodeAsset = PeriodeAsset::firstOrNew([
                'periode_id' => $periode->id,
                'asset_id'   => $asset->id,
            ]);
            $periodeAsset->status = $request->status;
            $periodeAsset->tanggal_inventaris = Carbon::now();
            $periodeAsset->scan_by = $userId;
            $periodeAsset->save();

            DB::commit();

            // Load relasi lengkap untuk output response
            $asset->load(['jenis', 'ruang', 'gedung', 'subjenis', 'latestPeriodeAsset']);
            $asset->url_gambar = $asset->urlGambar();

            $data = [
                'asset'              => $asset,
                'assetExtracomtable' => $asset,
                'periode_asset'      => $periodeAsset,
            ];

            return ResponseHelper::response(
                'Berhasil menambahkan data asset',
                null,
                $data,
                201
            );

        } catch (Exception $e) {
            DB::rollBack();

            // Hapus file gambar jika sudah terlanjur dibuat
            if ($filename && file_exists($uploadPath . '/' . $filename)) {
                @unlink($uploadPath . '/' . $filename);
            }

            return ResponseHelper::response(
                'Gagal menambahkan data asset',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    public function getGedung(Request $request)
    {
        return app(MasterDataController::class)->getGedung($request);
    }

    public function getLantai(Request $request)
    {
        return app(MasterDataController::class)->getLantai($request);
    }

    public function getRuang(Request $request)
    {
        return app(MasterDataController::class)->getRuang($request);
    }

    public function getJenis(Request $request)
    {
        return app(MasterDataController::class)->getJenis($request);
    }

    public function getSubJenis(Request $request)
    {
        return app(MasterDataController::class)->getSubJenis($request);
    }

}