@extends('crudbooster::admin_template')

@section('content')
  @component('components.panel-form')
    @slot('title')
      <i class="fa fa-file-text"></i>
      Detail Asset
      <strong>
        {{ $asset->nama_asset }}
      </strong>
    @endslot
    <div class="row">
      <div class="col-md-8">
        <div class="table-responsive">
          <table class="table table-hover table-bordered">
            <tr>
              <td width="200">Nama Asset</td>
              <td><strong>{{ $asset->nama_asset }}</strong></td>
            </tr>
            <tr>
              <td width="200">Kode Asset</td>
              <td><strong>{{ $asset->kd_asset }}</strong></td>
            </tr>
            <tr>
              <td width="200">Jenis</td>
              <td><strong>{{ $asset->jenis ? $asset->jenis->nama : '-' }}</strong></td>
            </tr>
            <tr>
              <td width="200">Sub Jenis</td>
              <td><strong>{{ $asset->subjenis ? $asset->subjenis->nama : '-' }}</strong></td>
            </tr>
            <tr>
              <td width="200">Lokasi</td>
              <td><strong>{!! $asset->getLocation() !!}</strong></td>
            </tr>
            <tr>
              <td width="200">Tgl. Masuk</td>
              <td><strong>{!! date('d/m/Y', strtotime($asset->tgl_masuk)) !!}</strong></td>
            </tr>
            <tr>
              <td width="200">Status</td>
              <td>
                {!! App\Models\AssetExtracomptable::scanStatusBadge($latest_scan ? $latest_scan->status : null) !!}
                @if($latest_scan)
                  <br>
                  <small class="text-muted">
                    Scan terakhir:
                    {{ $latest_scan->tanggal_inventaris ? date('d/m/Y H:i', strtotime($latest_scan->tanggal_inventaris)) : '-' }}
                    @if($latest_scan->periode)
                      &middot; Periode {{ $latest_scan->periode->year }}
                    @endif
                  </small>
                @endif
              </td>
            </tr>
          </table>
        </div>
      </div>
      <div class="col-md-4">
        <img style="width:100%;" src="{{ $asset->urlGambar() }}" alt="" class="thumbnail">
      </div>
    </div>
  @endcomponent

  @component('components.panel-form')
    @slot('title')
      <i class="fa fa-history"></i>
      <strong>Log Perubahan</strong>
      <small class="text-muted">(riwayat scan / inventarisasi barang)</small>
    @endslot
    <div class="table-responsive">
      <table class="table table-bordered table-hover table-striped">
        <thead>
          <tr>
            <th width="60">No.</th>
            <th>Periode</th>
            <th>Status</th>
            <th>Tanggal Scan</th>
            <th>Discan Oleh</th>
          </tr>
        </thead>
        <tbody>
          @forelse($scan_histories as $i => $scan)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>{{ $scan->periode ? $scan->periode->year : '-' }}</td>
              <td>{!! App\Models\AssetExtracomptable::scanStatusBadge($scan->status) !!}</td>
              <td>{{ $scan->tanggal_inventaris ? date('d/m/Y H:i', strtotime($scan->tanggal_inventaris)) : '-' }}</td>
              <td>{{ $scan->scanBy ? $scan->scanBy->name : '-' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5">
                <div class="text-center" style="padding: 10px">
                  - Belum ada riwayat scan -
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endcomponent
@endsection
