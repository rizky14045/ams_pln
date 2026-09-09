<?php

namespace App\Models;

use App\Libraries\QrcodeAsset;
use App\Models\PeriodeAsset;
use App\Traits\AssetLogger;
use Datetime;
use Illuminate\Database\Eloquent\Model;

class AssetExtracomptable extends ArchiveableModel
{
    use AssetLogger;

    protected $table = 'asset_extracomptable';

    public static function generateKodeAsset(
        Gedung $gedung,
        $lantai,
        Ruang $ruang,
        JenisExtracomptable $jenis,
        SubJenisExtracomptable $subjenis,
        Datetime $date = null
    ) {
        if (!$date) {
            $date = new Datetime;
        }
        $separator = ".";
        $ndigit = 4;
        $prefix_kode = implode($separator, [
            $gedung->kd_gedung,
            $lantai,
            $ruang->kd_ruang,
            $jenis->kd_jenis,
            $subjenis->kd_subjenis,
            $date->format('Y')
        ]);

        $last = static::where('kd_asset', 'like', $prefix_kode.$separator.'%')->max('kd_asset');
        if (!$last) {
            $no_urut = 0;
        } else {
            $no_urut = (int) substr($last, -1 * $ndigit, $ndigit);
        }

        return $prefix_kode.$separator.str_pad($no_urut + 1, $ndigit, '0', STR_PAD_LEFT);
    }

    public static function queryReport($id_gedung, $lantai, $id_ruang)
    {
        $query = static::select([
            'asset_extracomptable.id_gedung',
            'asset_extracomptable.lantai',
            'asset_extracomptable.id_ruang',
            'asset_extracomptable.id_jenis',
            'asset_extracomptable.id_subjenis',
            'gedung.nama as gedung',
            'ruang.nama_ruang as ruang',
            'jenis_extracomptable.nama as jenis',
            'subjenis_extracomptable.nama as subjenis',
            \DB::raw('count(*) as jumlah'),
        ])
        ->join('gedung', 'asset_extracomptable.id_gedung', '=', 'gedung.id')
        ->join('ruang', 'asset_extracomptable.id_ruang', '=', 'ruang.id')
        ->join('jenis_extracomptable', 'asset_extracomptable.id_jenis', '=', 'jenis_extracomptable.id')
        ->join('subjenis_extracomptable', 'asset_extracomptable.id_subjenis', '=', 'subjenis_extracomptable.id')
        ->groupBy([
            'gedung.nama',
            'ruang.nama_ruang',
            'jenis_extracomptable.nama',
            'subjenis_extracomptable.nama',
            'asset_extracomptable.id_gedung',
            'asset_extracomptable.lantai',
            'asset_extracomptable.id_ruang',
            'asset_extracomptable.id_jenis',
            'asset_extracomptable.id_subjenis'
        ])
        ->orderBy('gedung.nama', 'asc');

        if ($id_gedung) {
            $query->where('asset_extracomptable.id_gedung', $id_gedung);
        }

        if ($lantai) {
            $query->where('asset_extracomptable.lantai', $lantai);
        }

        if ($id_ruang) {
            $query->where('asset_extracomptable.id_ruang', $id_ruang);
        }

        return $query;
    }

    /**
     * Sama seperti queryReport(), tapi dibatasi pada aset yang terdaftar
     * di sebuah periode inventarisasi (tabel periode_asset).
     */
    public static function queryReportByPeriode($periodeId, $id_gedung = null, $lantai = null, $id_ruang = null)
    {
        $query = static::queryReport($id_gedung, $lantai, $id_ruang);
        $query->join('periode_asset', 'periode_asset.asset_id', '=', 'asset_extracomptable.id')
              ->where('periode_asset.periode_id', $periodeId)
              // pecah jumlah berdasarkan status hasil scan / inventarisasi
              // (alias 'periode_status' — hindari bentrok dengan accessor scan_status)
              ->addSelect('periode_asset.status as periode_status')
              ->groupBy('periode_asset.status')
              ->orderBy('jenis_extracomptable.nama', 'asc')
              ->orderBy('subjenis_extracomptable.nama', 'asc')
              ->orderBy('periode_asset.status', 'asc');

        return $query;
    }

    public static function querySummaryByJenis()
    {
        return static::select([
            'asset_extracomptable.id_jenis',
            'asset_extracomptable.id_subjenis',
            'jenis_extracomptable.nama as jenis',
            'subjenis_extracomptable.nama as subjenis',
            \DB::raw('count(*) as jumlah'),
        ])
        ->join('jenis_extracomptable', 'asset_extracomptable.id_jenis', '=', 'jenis_extracomptable.id')
        ->join('subjenis_extracomptable', 'asset_extracomptable.id_subjenis', '=', 'subjenis_extracomptable.id')
        ->groupBy([
            'jenis_extracomptable.nama',
            'subjenis_extracomptable.nama',
            'asset_extracomptable.id_jenis',
            'asset_extracomptable.id_subjenis'
        ])
        ->orderBy('jenis_extracomptable.nama', 'asc');
    }

    public static function querySummaryByLokasi()
    {
        return static::select([
            'asset_extracomptable.id_gedung',
            'asset_extracomptable.lantai',
            'asset_extracomptable.id_ruang',
            'gedung.nama as gedung',
            'ruang.nama_ruang as ruang',
            \DB::raw('count(*) as jumlah'),
        ])
        ->join('gedung', 'asset_extracomptable.id_gedung', '=', 'gedung.id')
        ->join('ruang', 'asset_extracomptable.id_ruang', '=', 'ruang.id')
        ->groupBy([
            'gedung.nama',
            'ruang.nama_ruang',
            'asset_extracomptable.id_gedung',
            'asset_extracomptable.lantai',
            'asset_extracomptable.id_ruang'
        ])
        ->orderBy('gedung.nama', 'asc');
    }

    public static function querySummaryPerSubJenis(SubJenisExtracomptable $subjenis)
    {
        return static::select([
            'asset_extracomptable.kd_asset',
            'asset_extracomptable.nama_asset',
            'asset_extracomptable.id_gedung',
            'asset_extracomptable.lantai',
            'asset_extracomptable.id_ruang',
            'gedung.nama as gedung',
            'ruang.nama_ruang as ruang',
            'asset_extracomptable.status',
        ])
        ->join('gedung', 'asset_extracomptable.id_gedung', '=', 'gedung.id')
        ->join('ruang', 'asset_extracomptable.id_ruang', '=', 'ruang.id')
        ->where('id_subjenis', $subjenis->getKey())
        ->orderBy('gedung.nama', 'asc')
        ->orderBy('lantai', 'asc')
        ->orderBy('ruang', 'asc');
    }

    public static function querySummaryPerRuang(Ruang $ruang)
    {
        return static::select([
            'asset_extracomptable.kd_asset',
            'asset_extracomptable.nama_asset',
            'asset_extracomptable.id_jenis',
            'asset_extracomptable.id_subjenis',
            'jenis_extracomptable.nama as jenis',
            'subjenis_extracomptable.nama as subjenis',
            'asset_extracomptable.status',
        ])
        ->join('jenis_extracomptable', 'asset_extracomptable.id_jenis', '=', 'jenis_extracomptable.id')
        ->join('subjenis_extracomptable', 'asset_extracomptable.id_subjenis', '=', 'subjenis_extracomptable.id')
        ->where('id_ruang', $ruang->getKey())
        ->orderBy('jenis', 'asc')
        ->orderBy('subjenis', 'asc');
    }

    public function qrcode()
    {
        $textRuangLantai = ($this->ruang ? $this->ruang->nama_ruang : '') . ' / Lt. ' . $this->lantai;
        $textGedung = $this->gedung ? $this->gedung->nama : '';
        return QrcodeAsset::make($this->kd_asset, [
            'name' => $this->nama_asset,
            'location' => $this->getLocation(),
            'texts' => [
                $textRuangLantai,
                $textGedung
            ],
            'directory' => 'extracomptable',
        ]);
    }

    public function getLocation()
    {
        $ruang = $this->ruang;
        $gedung = $this->gedung;

        $paths = [];
        if ($ruang) $paths[] = $ruang->nama_ruang;
        $paths[] = "Lt. {$this->lantai}";
        if ($gedung) $paths[] = $gedung->nama;

        return implode(' / ', $paths);
    }

    public function urlGambar()
    {
        return asset('uploads/assets-extracomptable/'.$this->gambar) ?: asset('img/default-asset-extracomptable.png');
    }

    public function updateLocation(Gedung $gedung, $lantai, Ruang $ruang)
    {
        $this->id_gedung = $gedung->getKey();
        $this->lantai = $lantai;
        $this->id_ruang = $ruang->getKey();
        return $this->save();
    }

    public function scopeAtGedung($query, Gedung $gedung)
    {
        return $query->where('id_gedung', $gedung->getKey());
    }

    public function scopeAtGedungLantai($query, Gedung $gedung, $lantai)
    {
        return $query->atGedung($gedung)->where('lantai', $lantai);
    }

    public function scopeAtRuang($query, Ruang $ruang)
    {
        return $query->where('id_ruang', $ruang->getKey());
    }

    public function jenis()
    {
        return $this->hasOne(JenisExtracomptable::class, 'id', 'id_jenis');
    }

    public function subjenis()
    {
        return $this->hasOne(SubJenisExtracomptable::class, 'id', 'id_subjenis');
    }

    public function gedung()
    {
        return $this->hasOne(Gedung::class, 'id', 'id_gedung')->withTrashed();
    }

    public function ruang()
    {
        return $this->hasOne(Ruang::class, 'id', 'id_ruang')->withTrashed();
    }

    public function latestPeriodeAsset()
    {
        // Mengambil 1 record PeriodeAsset terakhir berdasarkan id/created_at
        return $this->hasOne(PeriodeAsset::class, 'asset_id', 'id')->latest('id');
    }

    public function periodeAssets()
    {
        return $this->hasMany(PeriodeAsset::class, 'asset_id', 'id');
    }

    /**
     * Scan / inventarisasi terakhir yang sudah memiliki status.
     */
    public function latestScan()
    {
        return $this->hasOne(PeriodeAsset::class, 'asset_id', 'id')
            ->whereNotNull('status')
            ->orderBy('tanggal_inventaris', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Seluruh riwayat scan barang (terbaru lebih dulu).
     */
    public function scanHistories()
    {
        return $this->hasMany(PeriodeAsset::class, 'asset_id', 'id')
            ->whereNotNull('status')
            ->orderBy('tanggal_inventaris', 'desc')
            ->orderBy('id', 'desc');
    }

    /**
     * Status barang diambil dari status scan terakhir.
     * Mengembalikan null bila barang belum pernah discan.
     */
    public function getScanStatusAttribute()
    {
        $scan = $this->relationLoaded('latestScan') ? $this->getRelation('latestScan') : $this->latestScan;

        return $scan ? $scan->status : null;
    }

    /**
     * Render label status (dari hasil scan) menjadi HTML badge.
     */
    public static function scanStatusBadge($status)
    {
        if (!$status) {
            return "<span class='label label-default'>Belum diinventarisasi</span>";
        }

        $configs = config('asset.status_extracomptable');
        $config = array_first($configs, function ($opt) use ($status) {
            return $opt['value'] == $status;
        });

        $class = !empty($config) ? $config['class'] : 'label-primary';
        $label = !empty($config) ? $config['label'] : $status;

        return "<span class='label {$class}'>".e($label)."</span>";
    }

    /**
     * Label status scan dalam bentuk teks biasa (untuk export Excel dsb).
     */
    public static function scanStatusText($status)
    {
        if (!$status) {
            return 'Belum diinventarisasi';
        }

        $configs = config('asset.status_extracomptable');
        $config = array_first($configs, function ($opt) use ($status) {
            return $opt['value'] == $status;
        });

        return !empty($config) ? $config['label'] : $status;
    }

}
