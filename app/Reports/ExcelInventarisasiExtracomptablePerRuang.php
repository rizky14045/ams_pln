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
     * Generate lalu langsung kirim sebagai unduhan .xlsx.
     *
     * PHPExcel 1.8 masih memakai sintaks lama (mis. string offset kurung kurawal)
     * yang memicu E_DEPRECATED / E_STRICT pada PHP >= 7.4; tanpa diredam, Laravel
     * mempromosikannya menjadi ErrorException saat proses render Excel.
     */
    public function download($filename = null)
    {
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

        try {
            return $this->generate($filename)->download('xlsx');
        } finally {
            error_reporting($previousErrorReporting);
        }
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
