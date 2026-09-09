@extends('crudbooster::admin_template')

@section('content')
  @include('partials.alert-messages')

  @component('components.panel-form')
    @slot('title')
      <i class="fa fa-plus"></i> Tambah Periode Inventarisasi
    @endslot

    <div class="alert alert-info">
      Saat periode dibuat, sistem otomatis membuat daftar inventarisasi untuk
      <strong>{{ number_format($total_asset, 0, ',', '.') }} aset extra comptable</strong>
      yang ada saat ini (status awal kosong).
    </div>

    <form action="{{ route('periode-inventarisasi::post-create') }}" method="POST" class="form-horizontal">
      @include('partials.fields.number', [
        'name' => 'year',
        'value' => prev_input('year'),
        'required' => true,
        'label' => 'Tahun Periode',
        'help' => 'Contoh: 2026. Satu tahun hanya boleh punya satu periode.',
      ])

      <hr>

      <div class="form-group">
        <div class="col-md-push-2 col-md-10">
          <a href="{{ route('periode-inventarisasi::index') }}" class="btn btn-default"><i class="fa fa-chevron-left"></i> Batal</a>
          &nbsp;
          <button name="action" value="save-and-new" class="btn btn-success"><i class="fa fa-plus"></i> Simpan dan Buat Baru</button>
          &nbsp;
          <button name="action" value="save" class="btn btn-success"><i class="fa fa-save"></i> Simpan</button>
        </div>
      </div>
      {!! csrf_field() !!}
    </form>
  @endcomponent
@endsection
