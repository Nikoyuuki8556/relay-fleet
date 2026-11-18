<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $primaryKey = 'icao';
    protected $keyType = 'string';

    protected $fillable = [
        'icao', 'name', 'lat', 'lon', 'source',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
        ];
    }
}
