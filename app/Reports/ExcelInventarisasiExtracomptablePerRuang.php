<?php

namespace App\Reports;

use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\Ruang;
use Illuminate\Support\Facades\DB;

/**
 * Export inventarisasi extra comptable untuk 1 periode.
 *
 * Data dirinci PER BARANG (1 baris = 1 aset), bukan rekap jumlah, supaya
 * status hasil scan tiap barang kelihatan. Dipecah menjadi beberapa sheet:
 * 1 sheet per nama Ruang, judul sheet = nama ruang.
 *
 * Kolom: No, Gedung, Lantai, Ruang, Kode Aset, Nama Aset, Jenis, Sub Jenis,
 *        Status Barang, Tanggal Scan, Discan Oleh.
 */
class ExcelInventarisasiExtracomptablePerRuang
{
    protected $periode;

    protected $columns = [
        'no'           => ['label' => 'No.', 'format' => null],       // diisi di constructor
        'gedung'       => ['label' => 'Gedung'],
        'lantai'       => ['label' => 'Lantai'],
        'ruang'        => ['label' => 'Ruang'],
        'kd_asset'     => ['label' => 'Kode Aset'],
        'nama_asset'   => ['label' => 'Nama Aset'],
        'jenis'        => ['label' => 'Jenis'],
        'subjenis'     => ['label' => 'Sub Jenis'],
        'status'       => ['label' => 'Status Barang', 'format' => null],
        'tanggal_scan' => ['label' => 'Tanggal Scan', 'format' => null],
        'scan_by'      => ['label' => 'Discan Oleh', 'format' => null],
    ];

    public function __construct(Periode $periode)
    {
        $this->periode = $periode;

        $this->columns['no']['format'] = function ($val, $row, $i) {
            return $i + 1;
        };
        $this->columns['status']['format'] = function ($val) {
            return AssetExtracomptable::scanStatusText($val);
        };
        $this->columns['tanggal_scan']['format'] = function ($val) {
            return $val ? date('d/m/Y H:i', strtotime($val)) : '-';
        };
        $this->columns['scan_by']['format'] = function ($val) {
            return $val !== null && $val !== '' ? $val : '-';
        };
    }

    public function getDefaultFilename()
    {
        return 'inventarisasi-extracomptable-'.$this->periode->year.'_'.date('ymdhis');
    }

    /**
     * Generate lalu kirim sebagai unduhan .xlsx.
     *
     * PHPExcel 1.8 mengeluarkan banyak notice/warning/deprecation di PHP >= 7.4.
     * Kalau teks itu ikut tercetak ke response, file .xlsx jadi rusak / corrupt.
     * Karena itu: error_reporting(0) + display_errors off selama render, buffer
     * dibersihkan, lalu file dibungkus Response Laravel yang bersih.
     */
    public function download($filename = null)
    {
        $filename = $filename ?: $this->getDefaultFilename();

        $previousErrorReporting = error_reporting();
        $previousDisplayErrors = ini_get('display_errors');
        error_reporting(0);
        ini_set('display_errors', '0');
        @ini_set('zlib.output_compression', '0');

        try {
            $content = $this->generate($filename)->string('xlsx');
        } finally {
            error_reporting($previousErrorReporting);
            ini_set('display_errors', $previousDisplayErrors);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    public function generate($filename = null)
    {
        $filename = $filename ?: $this->getDefaultFilename();
        $columns = $this->columns;
        $groups = $this->getRuangGroups();

        // Judul sheet disiapkan di awal supaya benar-benar unik (case-insensitive)
        // dan valid untuk Excel (maks 31 char). Nama sheet duplikat / case-berbeda
        // membuat Excel menolak file: "problem with content ... workbook.xml".
        $used = [];
        foreach ($groups as $i => $group) {
            $groups[$i]['sheet'] = $this->makeSheetTitle($group['name'], $used);
        }

        return \Excel::create($filename, function ($excel) use ($groups, $columns) {
            if (empty($groups)) {
                $excel->sheet('Kosong', function ($sheet) use ($columns) {
                    $this->fixPageMargins($sheet);
                    $this->writeHeader($sheet, $columns);
                });

                return;
            }

            foreach ($groups as $group) {
                $rows = $this->getRows($group['ids']);

                $excel->sheet($group['sheet'], function ($sheet) use ($columns, $rows) {
                    $this->fixPageMargins($sheet);
                    $this->writeHeader($sheet, $columns);
                    $this->writeRows($sheet, $columns, $rows);
                });
            }
        });
    }

    /**
     * Daftar barang (1 baris/aset) untuk sekumpulan ruang, pada periode ini.
     *
     * @param  int[]  $ruangIds
     * @return array[]
     */
    protected function getRows(array $ruangIds)
    {
        return DB::table('periode_asset')
            ->join('asset_extracomptable', 'asset_extracomptable.id', '=', 'periode_asset.asset_id')
            ->leftJoin('gedung', 'gedung.id', '=', 'asset_extracomptable.id_gedung')
            ->leftJoin('ruang', 'ruang.id', '=', 'asset_extracomptable.id_ruang')
            ->leftJoin('jenis_extracomptable', 'jenis_extracomptable.id', '=', 'asset_extracomptable.id_jenis')
            ->leftJoin('subjenis_extracomptable', 'subjenis_extracomptable.id', '=', 'asset_extracomptable.id_subjenis')
            ->leftJoin('cms_users', 'cms_users.id', '=', 'periode_asset.scan_by')
            ->where('periode_asset.periode_id', $this->periode->id)
            ->whereNull('asset_extracomptable.deleted_at')
            ->whereIn('asset_extracomptable.id_ruang', $ruangIds)
            ->orderBy('gedung.nama', 'asc')
            ->orderBy('asset_extracomptable.lantai', 'asc')
            ->orderBy('jenis_extracomptable.nama', 'asc')
            ->orderBy('subjenis_extracomptable.nama', 'asc')
            ->orderBy('asset_extracomptable.kd_asset', 'asc')
            ->get([
                'gedung.nama as gedung',
                'asset_extracomptable.lantai as lantai',
                'ruang.nama_ruang as ruang',
                'asset_extracomptable.kd_asset as kd_asset',
                'asset_extracomptable.nama_asset as nama_asset',
                'jenis_extracomptable.nama as jenis',
                'subjenis_extracomptable.nama as subjenis',
                'periode_asset.status as status',
                'periode_asset.tanggal_inventaris as tanggal_scan',
                'cms_users.name as scan_by',
            ])
            ->map(function ($row) {
                return (array) $row;
            })
            ->all();
    }

    /**
     * Maatwebsite 2.1 memakai config 'page_margin' => false lalu memanggil
     * PageMargins::setTop(false) dst → writer menulis <pageMargins left="" .../>
     * yang TIDAK valid → Excel: "problem with content ... Worksheet properties".
     * Set ulang dengan angka valid.
     */
    protected function fixPageMargins($sheet)
    {
        $margins = $sheet->getPageMargins();
        $margins->setTop(0.75);
        $margins->setBottom(0.75);
        $margins->setLeft(0.7);
        $margins->setRight(0.7);
        $margins->setHeader(0.3);
        $margins->setFooter(0.3);
    }

    protected function writeHeader($sheet, array $columns)
    {
        $x = 'A';
        foreach ($columns as $opts) {
            $sheet->setCellValue(($x++).'1', $opts['label']);
        }
    }

    protected function writeRows($sheet, array $columns, array $rows)
    {
        foreach ($rows as $i => $row) {
            $x = 'A';
            foreach ($columns as $key => $opts) {
                $value = isset($row[$key]) ? $row[$key] : '';
                if (isset($opts['format']) && is_callable($opts['format'])) {
                    $value = call_user_func_array($opts['format'], [$value, $row, $i]);
                }
                $sheet->setCellValue(($x++).($i + 2), $value);
            }
        }
    }

    /**
     * Kelompokkan ruang yang punya aset pada periode ini berdasarkan NAMA
     * (case-insensitive) — jadi ruang bernama sama di gedung berbeda, atau
     * beda kapitalisasi, digabung ke satu sheet (kolom Gedung/Lantai membedakan).
     *
     * @return array<int, array{name: string, ids: int[]}>
     */
    protected function getRuangGroups()
    {
        $ruangIds = DB::table('periode_asset')
            ->join('asset_extracomptable', 'asset_extracomptable.id', '=', 'periode_asset.asset_id')
            ->where('periode_asset.periode_id', $this->periode->id)
            ->whereNull('asset_extracomptable.deleted_at')
            ->distinct()
            ->pluck('asset_extracomptable.id_ruang')
            ->all();

        $ruangs = Ruang::withTrashed()
            ->whereIn('id', $ruangIds)
            ->orderBy('nama_ruang', 'asc')
            ->get();

        $groups = [];
        foreach ($ruangs as $ruang) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $ruang->nama_ruang));
            if ($name === '') {
                $name = 'Tanpa Nama Ruang';
            }

            $key = $this->lower($name);
            if (!isset($groups[$key])) {
                $groups[$key] = ['name' => $name, 'ids' => []];
            }
            $groups[$key]['ids'][] = (int) $ruang->id;
        }

        return array_values($groups);
    }

    /**
     * Judul sheet valid Excel: maks 31 karakter, tanpa * : / \ ? [ ] dan tanpa
     * apostrof di ujung, serta unik case-insensitive (Excel menganggap "Ruang"
     * dan "ruang" sama). Bentrok diberi akhiran " (2)", " (3)", ...
     */
    protected function makeSheetTitle($name, array &$used)
    {
        $title = str_replace(['*', ':', '/', '\\', '?', '[', ']'], ' ', (string) $name);
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        $title = trim($title, "'");

        if ($title === '') {
            $title = 'Ruang';
        }

        $title = $this->cut($title, 31);

        if (in_array($this->lower($title), $used, true)) {
            $base = $title;
            $n = 2;
            do {
                $suffix = ' ('.$n.')';
                $title = $this->cut($base, 31 - strlen($suffix)).$suffix;
                $n++;
            } while (in_array($this->lower($title), $used, true) && $n < 1000);
        }

        $used[] = $this->lower($title);

        return $title;
    }

    protected function cut($s, $len)
    {
        if ($len < 1) {
            $len = 1;
        }

        return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    }

    protected function lower($s)
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }
}
