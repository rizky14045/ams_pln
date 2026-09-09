<?php

namespace App\Http\Controllers;

use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\PeriodeAsset;
use App\Services\PeriodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodeInventarisasiController extends Controller
{
    /** @var \App\Services\PeriodeService */
    protected $periodeService;

    public function __construct(PeriodeService $periodeService)
    {
        $this->periodeService = $periodeService;
    }

    /**
     * Halaman daftar periode inventarisasi extra comptable.
     */
    public function index()
    {
        $data['page_title'] = 'Inventarisasi Extra Comptable';
        $data['datagrid'] = $this->datagrid();

        return view('periode-inventarisasi.index', $data);
    }

    /**
     * Data JSON untuk datagrid.
     */
    public function getJsonList()
    {
        return $this->datagrid()->getResults();
    }

    /**
     * Form tambah periode.
     */
    public function formCreate()
    {
        $data['page_title'] = 'Tambah Periode Inventarisasi';
        $data['total_asset'] = AssetExtracomptable::count();

        return view('periode-inventarisasi.form-create', $data);
    }

    /**
     * Simpan periode baru.
     * Memakai PeriodeService::create() — logika sama dengan API v2 POST /api/v2/periode:
     * buat periode + generate slot periode_asset untuk seluruh asset extra comptable.
     */
    public function postCreate(Request $request)
    {
        $this->validate($request, [
            'year' => 'required|integer|unique:periode,year',
        ], [], [
            'year' => 'tahun periode',
        ]);

        $periode = $this->periodeService->create($request->get('year'));
        $jumlahSlot = $periode->periodeAssets()->count();

        $action = $request->get('action');
        $route = ($action == 'save-and-new')
            ? 'periode-inventarisasi::form-create'
            : 'periode-inventarisasi::index';

        return redirect()->route($route)->with(
            'alert-success',
            'Periode inventarisasi '.$periode->year.' berhasil dibuat beserta '.$jumlahSlot.' data aset.'
        );
    }

    /**
     * Form edit periode.
     */
    public function formEdit($id)
    {
        $periode = Periode::findOrFail($id);

        $data['page_title'] = 'Edit Periode Inventarisasi';
        $data['periode'] = $periode;
        $data['total_asset'] = AssetExtracomptable::count();
        $data['total_slot'] = PeriodeAsset::where('periode_id', $periode->id)->count();

        return view('periode-inventarisasi.form-edit', $data);
    }

    /**
     * Simpan perubahan periode.
     * Memakai PeriodeService::update() — ubah tahun + lengkapi slot aset yang belum tercatat.
     */
    public function postEdit(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);

        $this->validate($request, [
            'year' => 'required|integer|unique:periode,year,'.$periode->id,
        ], [], [
            'year' => 'tahun periode',
        ]);

        $this->periodeService->update($periode, $request->get('year'));

        return redirect()->route('periode-inventarisasi::index')->with(
            'alert-info',
            'Periode inventarisasi '.$periode->year.' berhasil diperbarui.'
        );
    }

    /**
     * Detail periode: daftar aset beserta status inventarisasinya.
     */
    public function show(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);
        $perPage = (int) $request->get('limit') ?: 25;

        $baseQuery = PeriodeAsset::where('periode_id', $periode->id);

        $data['page_title'] = 'Detail Periode Inventarisasi '.$periode->year;
        $data['periode'] = $periode;
        $data['total_asset'] = (clone $baseQuery)->count();
        $data['total_selesai'] = (clone $baseQuery)->whereNotNull('status')->count();
        $data['pagination'] = PeriodeAsset::with([
                'asset', 'asset.gedung', 'asset.ruang', 'asset.jenis', 'asset.subjenis', 'scanBy',
            ])
            ->where('periode_id', $periode->id)
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        return view('periode-inventarisasi.show', $data);
    }

    /**
     * Export rekap inventarisasi periode ke Excel.
     * Format kolom sama dengan halaman Report Extra Comptable, namun
     * dipecah per sheet berdasarkan Ruang (judul sheet = nama ruang).
     */
    public function export($id)
    {
        $periode = Periode::findOrFail($id);

        return (new \App\Reports\ExcelInventarisasiExtracomptablePerRuang($periode))->download();
    }

    /**
     * Hapus periode (beserta seluruh slot periode_asset-nya).
     */
    public function deletes(Request $request)
    {
        $ids = (array) $request->get('ids');
        $ids = array_filter(array_map('intval', $ids));

        if (count($ids)) {
            DB::transaction(function () use ($ids) {
                PeriodeAsset::whereIn('periode_id', $ids)->delete();
                Periode::whereIn('id', $ids)->delete();
            });
        }

        return ['status' => 'success'];
    }

    /**
     * Definisi datagrid daftar periode.
     */
    protected function datagrid()
    {
        $query = Periode::query();

        return \Datagrid::make($query, [
            'id' => [
                'real_key' => 'periode.id',
                'display' => false,
            ],
            'no' => [
                'real_key' => 'periode.id',
                'label' => 'No.',
                'width' => 50,
                'th_class' => 'text-center',
                'td_class' => 'text-center',
                'format' => function ($val, $row, $i, $res) {
                    return $res['from'] + $i;
                },
            ],
            'year' => [
                'real_key' => 'periode.year',
                'label' => 'Tahun Periode',
                'sortable' => true,
                'searchable' => true,
                'width' => 160,
            ],
            'jumlah_asset' => [
                'real_key' => 'periode.id',
                'label' => 'Jumlah Aset',
                'width' => 130,
                'th_class' => 'text-center',
                'td_class' => 'text-center',
                'format' => function ($val, $row) {
                    return PeriodeAsset::where('periode_id', $row->id)->count();
                },
            ],
            'progres' => [
                'real_key' => 'periode.id',
                'label' => 'Sudah Diinventarisasi',
                'width' => 220,
                'format' => function ($val, $row) {
                    $total = PeriodeAsset::where('periode_id', $row->id)->count();
                    $done = PeriodeAsset::where('periode_id', $row->id)->whereNotNull('status')->count();
                    $pct = $total ? round($done / $total * 100) : 0;

                    return "<div class='progress' style='margin-bottom:0'>
                        <div class='progress-bar progress-bar-success' role='progressbar' style='min-width:3em;width:{$pct}%'>
                            {$done}/{$total} ({$pct}%)
                        </div>
                    </div>";
                },
            ],
            'created_at' => [
                'real_key' => 'periode.created_at',
                'label' => 'Dibuat',
                'sortable' => true,
                'width' => 160,
            ],
            'aksi' => [
                'real_key' => 'periode.id',
                'label' => 'Aksi',
                'width' => 280,
                'format' => function ($val, $row) {
                    $urlShow = route('periode-inventarisasi::show', $row->id);
                    $urlEdit = route('periode-inventarisasi::form-edit', $row->id);
                    $urlExport = route('periode-inventarisasi::export', $row->id);

                    return "
                        <a class='btn btn-info btn-xs' href='{$urlShow}'><i class='fa fa-list'></i> Detail</a>
                        <a class='btn btn-success btn-xs' href='{$urlExport}'><i class='fa fa-file-excel-o'></i> Excel</a>
                        <a class='btn btn-primary btn-xs btn-edit' href='{$urlEdit}'><i class='fa fa-pencil'></i> Edit</a>
                        <a class='btn btn-danger btn-xs btn-delete'><i class='fa fa-trash'></i> Hapus</a>
                    ";
                },
            ],
        ])
        ->orderBy('periode.year', 'desc')
        ->withOptions([
            'per_page' => 15,
            'limit_options' => [15, 30, 50, 100],
            'checkables' => true,
            'primary_key' => 'id',
            'empty_message' => 'Belum ada periode inventarisasi.',
            'fetch_url' => route('periode-inventarisasi::json-list'),
        ]);
    }
}
