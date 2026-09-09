<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{

    protected $table = 'periode';

    protected $fillable = [
        'year',
    ];

    public function periodeAssets()
    {
        return $this->hasMany(PeriodeAsset::class, 'periode_id', 'id');
    }
}
