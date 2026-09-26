<?php

namespace App\Http\Controllers\Api\V2;

use App\Helper\ResponseHelper;
use App\Http\Controllers\Api\ApiController;
use App\Models\Gedung;
use App\Models\JenisExtracomptable;
use App\Models\Ruang;
use App\Models\SubJenisExtracomptable;
use Exception;
use Illuminate\Http\Request;

class MasterDataController extends ApiController
{
    /**
     * Mengambil daftar gedung
     * Parameter request opsional:
     * - search: kata kunci pencarian (nama / kd_gedung / lokasi)
     */
    public function getGedung(Request $request)
    {
        try {
            $query = Gedung::query();

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('kd_gedung', 'like', "%{$search}%")
                      ->orWhere('lokasi', 'like', "%{$search}%");
                });
            }

            $gedung = $query->orderBy('nama', 'asc')->get();

            return ResponseHelper::response(
                'Berhasil mendapatkan data gedung',
                null,
                $gedung,
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data gedung',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Mengambil daftar lantai berdasarkan gedung
     * Parameter request:
     * - id_gedung (atau gedung_id): ID gedung (opsional/disarankan)
     * - format: 'plain' jika ingin array integer saja [1, 2, 3]
     */
    public function getLantai(Request $request)
    {
        try {
            $idGedung = $request->get('id_gedung') ?: $request->get('gedung_id');

            if ($idGedung) {
                $gedung = Gedung::find($idGedung);
                if (!$gedung) {
                    return ResponseHelper::response(
                        'Gedung tidak ditemukan!',
                        null,
                        null,
                        404
                    );
                }

                $listLantai = $gedung->getListLantai();
                if (empty($listLantai) || $gedung->jumlah_lantai <= 0) {
                    $listLantai = Ruang::where('id_gedung', $gedung->id)
                        ->distinct()
                        ->orderBy('lantai', 'asc')
                        ->pluck('lantai')
                        ->toArray();
                }
            } else {
                $listLantai = Ruang::distinct()
                    ->orderBy('lantai', 'asc')
                    ->pluck('lantai')
                    ->toArray();
            }

            // Jika client meminta format array integer plain
            if ($request->get('format') === 'plain' || $request->get('flat')) {
                $plainLantai = array_values(array_map('intval', $listLantai));
                return ResponseHelper::response(
                    'Berhasil mendapatkan data lantai',
                    null,
                    $plainLantai,
                    200
                );
            }

            $formatted = [];
            foreach ($listLantai as $lt) {
                $formatted[] = [
                    'id'     => (int) $lt,
                    'lantai' => (int) $lt,
                    'nama'   => 'Lantai ' . $lt,
                ];
            }

            return ResponseHelper::response(
                'Berhasil mendapatkan data lantai',
                null,
                $formatted,
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data lantai',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Mengambil daftar ruang berdasarkan gedung dan/atau lantai
     * Parameter request:
     * - id_gedung (atau gedung_id): filter ID gedung (opsional)
     * - lantai: filter lantai (opsional)
     * - search: kata kunci pencarian nama_ruang atau kd_ruang (opsional)
     */
    public function getRuang(Request $request)
    {
        try {
            $query = Ruang::with('gedung');

            if ($idGedung = ($request->get('id_gedung') ?: $request->get('gedung_id'))) {
                $query->where('id_gedung', $idGedung);
            }

            if ($lantai = $request->get('lantai')) {
                $query->where('lantai', $lantai);
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_ruang', 'like', "%{$search}%")
                      ->orWhere('kd_ruang', 'like', "%{$search}%");
                });
            }

            $ruang = $query->orderBy('nama_ruang', 'asc')->get();

            return ResponseHelper::response(
                'Berhasil mendapatkan data ruang',
                null,
                $ruang,
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data ruang',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Mengambil daftar jenis extracomptable
     * Parameter request opsional:
     * - search: kata kunci pencarian nama atau kd_jenis
     */
    public function getJenis(Request $request)
    {
        try {
            $query = JenisExtracomptable::query();

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('kd_jenis', 'like', "%{$search}%");
                });
            }

            $jenis = $query->orderBy('nama', 'asc')->get();

            return ResponseHelper::response(
                'Berhasil mendapatkan data jenis',
                null,
                $jenis,
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data jenis',
                $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Mengambil daftar sub-jenis extracomptable
     * Parameter request:
     * - id_jenis (atau jenis_id): filter ID jenis (opsional)
     * - search: kata kunci pencarian nama atau kd_subjenis (opsional)
     */
    public function getSubJenis(Request $request)
    {
        try {
            $query = SubJenisExtracomptable::with('jenis');

            if ($idJenis = ($request->get('id_jenis') ?: $request->get('jenis_id'))) {
                $query->where('id_jenis', $idJenis);
            }

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('kd_subjenis', 'like', "%{$search}%");
                });
            }

            $subjenis = $query->orderBy('nama', 'asc')->get();

            return ResponseHelper::response(
                'Berhasil mendapatkan data sub-jenis',
                null,
                $subjenis,
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::response(
                'Gagal mendapatkan data sub-jenis',
                $e->getMessage(),
                null,
                500
            );
        }
    }
}
