<?php

namespace App\Models;

use App\SyncStatus;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'status',
    'progress',
    'processed_reviews',
    'error_code',
    'error_message',
    'started_at',
    'finished_at',
])]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'progress' => 'integer',
            'processed_reviews' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
