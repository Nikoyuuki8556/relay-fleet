<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatsimSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'callsign', 'cid', 'departure', 'arrival', 'logon_time', 'last_seen_at', 'raw_json',
    ];

    protected function casts(): array
    {
        return [
            'logon_time' => 'datetime',
            'last_seen_at' => 'datetime',
            'raw_json' => 'array',
        ];
    }
}
