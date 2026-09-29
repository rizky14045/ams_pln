{{-- JS untuk modal export-modal.blade.php. Include di @section('scripts'). --}}
<script>
  function openExportPeriodeModal(periodeId, year) {
    var baseUrl = '{{ url('periode-inventarisasi') }}';
    var $list = $('#export-room-list');

    $('#export-modal-year').text(year);
    $('#form-export-periode').attr('action', baseUrl + '/' + periodeId + '/export');
    $('#export-select-all').prop('checked', false);
    $('#export-search-ruang').val('');
    $list.html('<div class="text-center text-muted">Memuat daftar ruangan...</div>');
    $('#export-room-selected-count').text('');
    $('#modal-export-periode').modal('show');

    $.getJSON(baseUrl + '/' + periodeId + '/rooms.json')
      .done(function(res) {
        var rooms = (res && res.data) || [];
        if (!rooms.length) {
          $list.html('<div class="text-muted">Tidak ada ruangan pada periode ini.</div>');
          return;
        }

        var html = rooms.map(function(r) {
          var name = String(r.name).replace(/</g, '&lt;').replace(/>/g, '&gt;');
          return '<div class="checkbox room-item" data-name="' + name.toLowerCase() + '">' +
              '<label><input type="checkbox" name="ruang_id[]" value="' + r.ids.join(',') + '"> ' +
                name + ' <span class="text-muted">(' + r.jumlah_aset + ' aset)</span>' +
              '</label>' +
            '</div>';
        }).join('');

        $list.html(html);
        updateExportSelectedCount();
      })
      .fail(function() {
        $list.html('<div class="text-danger">Gagal memuat daftar ruangan.</div>');
      });
  }

  function updateExportSelectedCount() {
    var $checks = $('#export-room-list input[type=checkbox]');
    var n = $checks.filter(':checked').length;
    var total = $checks.length;
    $('#export-room-selected-count').text(
      n ? (n + ' dari ' + total + ' ruangan dipilih') : (total ? 'Tidak ada yang dipilih = export semua ruangan' : '')
    );
  }

  $(document).on('click', '.btn-export-periode', function() {
    openExportPeriodeModal($(this).data('periode-id'), $(this).data('periode-year'));
  });

  $(document).on('input', '#export-search-ruang', function() {
    var q = $(this).val().toLowerCase();
    $('#export-room-list .room-item').each(function() {
      $(this).toggle($(this).data('name').indexOf(q) !== -1);
    });
  });

  $(document).on('change', '#export-select-all', function() {
    var checked = $(this).is(':checked');
    $('#export-room-list .room-item:visible input[type=checkbox]').prop('checked', checked);
    updateExportSelectedCount();
  });

  $(document).on('change', '#export-room-list input[type=checkbox]', updateExportSelectedCount);
</script>
