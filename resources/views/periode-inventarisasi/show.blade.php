@extends('crudbooster::admin_template')

@section('content')
  @include('partials.alert-messages')

  <div class="form-group">
    <a href="{{ route('periode-inventarisasi::index') }}" class="btn btn-default">
      <i class="fa fa-chevron-left"></i> Kembali
    </a>
    <a href="{{ route('periode-inventarisasi::form-edit', [$periode->id]) }}" class="btn btn-primary">
      <i class="fa fa-pencil"></i> Edit Periode
    </a>
    <a href="{{ route('periode-inventarisasi::export', [$periode->id]) }}" class="btn btn-success">
      <i class="fa fa-file-excel-o"></i> Export Excel
    </a>
  </div>

  <div class="row">
    <div class="col-sm-4">
      <div class="box box-solid box-primary">
        <div class="box-body">
          <h4 style="margin-top:0">Tahun Periode</h4>
          <h2 style="margin:0">{{ $periode->year }}</h2>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="box box-solid">
        <div class="box-body">
          <h4 style="margin-top:0">Total Aset</h4>
          <h2 style="margin:0">{{ number_format($total_asset, 0, ',', '.') }}</h2>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="box box-solid box-success">
        <div class="box-body">
          <h4 style="margin-top:0">Sudah Diinventarisasi</h4>
          <h2 style="margin:0">
            {{ number_format($total_selesai, 0, ',', '.') }}
            <small>/ {{ number_format($total_asset, 0, ',', '.') }}</small>
          </h2>
        </div>
      </div>
    </div>
  </div>

  <div class="box box-solid">
    <div class="box-body table-responsive">
      <table class="table table-bordered table-hover table-striped">
        <thead>
          <tr>
            <th width="40">No.</th>
            <th>Kode Aset</th>
            <th>Nama Aset</th>
            <th>Lokasi</th>
            <th>Status</th>
            <th>Tanggal Scan</th>
            <th>Discan Oleh</th>
          </tr>
        </thead>
        <tbody>
          @forelse($pagination as $i => $row)
            <tr>
              <td>{{ $pagination->firstItem() + $i }}</td>
              <td>{{ $row->asset ? $row->asset->kd_asset : '-' }}</td>
              <td>{{ $row->asset ? $row->asset->nama_asset : '-' }}</td>
              <td>
                @if($row->asset)
                  {{ $row->asset->gedung ? $row->asset->gedung->nama : '-' }}
                  / Lt. {{ $row->asset->lantai }}
                  / {{ $row->asset->ruang ? $row->asset->ruang->nama_ruang : '-' }}
                @else
                  -
                @endif
              </td>
              <td>{!! App\Models\AssetExtracomptable::scanStatusBadge($row->status) !!}</td>
              <td>{{ $row->tanggal_inventaris ? date('d/m/Y H:i', strtotime($row->tanggal_inventaris)) : '-' }}</td>
              <td>{{ $row->scanBy ? $row->scanBy->name : '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center">Belum ada data aset pada periode ini.</td>
            </tr>
          @endforelse
        </tbody>
      </table>

      {!! $pagination->appends(request()->except('page'))->render() !!}
    </div>
  </div>
@endsection
