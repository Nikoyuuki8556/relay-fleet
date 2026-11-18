<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityLink extends Model
{
    public $timestamps = false;

    protected $fillable = ['community_id', 'label', 'url', 'sort_order'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
