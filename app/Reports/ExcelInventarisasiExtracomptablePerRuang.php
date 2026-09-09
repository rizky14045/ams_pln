<?php

namespace App\Reports;

use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\Ruang;
use Illuminate\Support\Facades\DB;

/**
 * Export rekap inventarisasi extra comptable untuk 1 periode.
 *
 * Isi tiap baris sama dengan halaman "Report Extra Comptable"
 * (No, Gedung, Lantai, Ruang, Jenis, Sub Jenis, Jumlah), namun datanya
 * dipecah menjadi beberapa sheet: 1 sheet per Ruang, judul sheet = nama ruang.
 */
class ExcelInventarisasiExtracomptablePerRuang
{
    protected $periode;

    protected $columns = [
        'no' => [
            'label' => 'No.',
            'format' => null, // diisi di constructor (butuh $this)
        ],
        'gedung'      => ['label' => 'Gedung'],
        'lantai'      => ['label' => 'Lantai'],
        'ruang'       => ['label' => 'Ruang'],
        'jenis'       => ['label' => 'Jenis'],
        'subjenis'    => ['label' => 'Sub Jenis'],
        'jumlah'         => ['label' => 'Jumlah'],
        'periode_status' => ['label' => 'Status Barang', 'format' => null],
    ];

    public function __construct(Periode $periode)
    {
        $this->periode = $periode;
        $this->columns['no']['format'] = function ($val, $row, $i) {
            return $i + 1;
        };
        $this->columns['periode_status']['format'] = function ($val) {
            return \App\Models\AssetExtracomptable::scanStatusText($val);
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
     * Kalau teks itu ikut tercetak ke response, file .xlsx jadi rusak / corrupt
     * ("cannot be opened because it is corrupt"). Karena itu:
     *  - error_reporting(0) selama proses render (fatal tetap fatal),
     *  - display_errors dimatikan,
     *  - semua output buffer dibersihkan sebelum body dikirim,
     *  - file diambil sebagai string lalu dibungkus Response Laravel yang bersih
     *    (tidak memakai _download() bawaan Maatwebsite yang rawan output nyasar).
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
        $ruangs = $this->getRuangs();
        $columns = $this->columns;

        return \Excel::create($filename, function ($excel) use ($ruangs, $columns) {
            if ($ruangs->isEmpty()) {
                $excel->sheet('Kosong', function ($sheet) use ($columns) {
                    $this->writeHeader($sheet, $columns);
                });

                return;
            }

            foreach ($ruangs as $ruang) {
                $rows = AssetExtracomptable::queryReportByPeriode($this->periode->id, null, null, $ruang->id)
                    ->get()
                    ->toArray();

                $title = $this->sheetTitle($ruang->nama_ruang);

                $excel->sheet($title, function ($sheet) use ($columns, $rows) {
                    $this->writeHeader($sheet, $columns);
                    $this->writeRows($sheet, $columns, $rows);
                });
            }
        });
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
     * Ruang mana saja yang punya aset pada periode ini.
     */
    protected function getRuangs()
    {
        $ruangIds = DB::table('periode_asset')
            ->join('asset_extracomptable', 'asset_extracomptable.id', '=', 'periode_asset.asset_id')
            ->where('periode_asset.periode_id', $this->periode->id)
            ->whereNull('asset_extracomptable.deleted_at')
            ->distinct()
            ->pluck('asset_extracomptable.id_ruang')
            ->all();

        return Ruang::withTrashed()
            ->whereIn('id', $ruangIds)
            ->orderBy('nama_ruang', 'asc')
            ->get();
    }

    /**
     * Judul sheet: nama ruang, dibersihkan agar valid untuk Excel
     * (maks 31 karakter, tanpa karakter * : / \ ? [ ]).
     * Duplikasi nama ditangani otomatis oleh PHPExcel.
     */
    protected function sheetTitle($name)
    {
        $title = str_replace(['*', ':', '/', '\\', '?', '[', ']'], ' ', (string) $name);
        $title = trim(preg_replace('/\s+/u', ' ', $title));

        if ($title === '') {
            $title = 'Ruang';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    }
}
