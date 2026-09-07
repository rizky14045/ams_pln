<?php

namespace App\Models;

use App\Models\AssetExtracomptable;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PeriodeAsset extends Model
{

    protected $table = 'periode_asset';

    protected $fillable = [
        'periode_id',
        'asset_id',
        'status',
        'tanggal_inventaris',
        'scan_by',
    ];

    public function periode()
    {
        return $this->belongsTo(Periode::class, 'periode_id', 'id');
    }
    public function asset()
    {
        return $this->belongsTo(AssetExtracomptable::class, 'asset_id', 'id');
    }

    public function scanBy()
    {
        return $this->belongsTo(User::class, 'scan_by', 'id');
    }
}
