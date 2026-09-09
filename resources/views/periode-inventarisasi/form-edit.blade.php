@extends('crudbooster::admin_template')

@section('content')
  @include('partials.alert-messages')

  @component('components.panel-form')
    @slot('title')
      <i class="fa fa-pencil"></i> Edit Periode Inventarisasi
    @endslot

    <div class="alert alert-info">
      Periode ini punya <strong>{{ number_format($total_slot, 0, ',', '.') }}</strong> data aset,
      sedangkan total aset extra comptable saat ini <strong>{{ number_format($total_asset, 0, ',', '.') }}</strong>.
      Menyimpan akan melengkapi data aset yang belum tercatat tanpa mengubah data yang sudah diinventarisasi.
    </div>

    <form action="{{ route('periode-inventarisasi::post-edit', [$periode->id]) }}" method="POST" class="form-horizontal">
      @include('partials.fields.number', [
        'name' => 'year',
        'value' => $periode->year,
        'required' => true,
        'label' => 'Tahun Periode',
      ])

      <hr>

      <div class="form-group">
        <div class="col-md-push-2 col-md-10">
          <a href="{{ route('periode-inventarisasi::index') }}" class="btn btn-default"><i class="fa fa-chevron-left"></i> Batal</a>
          &nbsp;
          <button name="action" value="save" class="btn btn-success"><i class="fa fa-save"></i> Simpan</button>
        </div>
      </div>
      {!! csrf_field() !!}
    </form>
  @endcomponent
@endsection
