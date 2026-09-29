{{-- Modal pilih ruangan untuk export Excel periode inventarisasi.
     Dipakai bareng-bareng oleh index.blade.php (tombol per baris) dan
     show.blade.php (tombol di header) lewat @include. Logic JS ada di
     partial export-modal-scripts.blade.php (di-include di @section('scripts')). --}}
<div class="modal fade" id="modal-export-periode" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Export Excel &mdash; Periode <span id="export-modal-year"></span></h4>
      </div>
      <form id="form-export-periode" method="GET" action="">
        <div class="modal-body">
          <p class="text-muted" style="margin-top:0">
            Pilih ruangan yang ingin di-export (1 sheet per ruangan). Biarkan tidak ada yang dicentang untuk export <strong>semua ruangan</strong>.
          </p>
          <div class="form-group">
            <input type="text" id="export-search-ruang" class="form-control" placeholder="Cari nama ruangan...">
          </div>
          <div class="checkbox">
            <label><input type="checkbox" id="export-select-all"> <strong>Pilih Semua Ruangan (yang tampil)</strong></label>
          </div>
          <hr style="margin:8px 0">
          <div id="export-room-list" style="max-height:320px; overflow-y:auto; border:1px solid #eee; padding:8px;">
            <div class="text-center text-muted">Memuat daftar ruangan...</div>
          </div>
        </div>
        <div class="modal-footer">
          <span class="pull-left text-muted" id="export-room-selected-count"></span>
          <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</button>
        </div>
      </form>
    </div>
  </div>
</div>
