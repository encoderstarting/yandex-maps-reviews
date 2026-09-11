<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'source_url',
    'normalized_url',
    'external_id',
    'name',
    'rating',
    'ratings_count',
    'reviews_count',
    'last_synced_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    /** @return HasOne<SyncRun, $this> */
    public function latestSyncRun(): HasOne
    {
        return $this->hasOne(SyncRun::class)->latestOfMany();
    }

    /** @return HasMany<OrganizationSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrganizationSnapshot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }
}
