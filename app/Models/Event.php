<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $venue
 * @property Carbon $starts_at
 * @property int $capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['name', 'description', 'venue', 'starts_at', 'capacity'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // The slug is the public URL, so it is set once and never changes.
        static::creating(function (Event $event): void {
            $event->slug ??= Str::slug($event->name).'-'.Str::lower(Str::random(5));
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Dashboard numbers, shared by the page's first render and its SSE stream.
     *
     * @return array{registered: int, checked_in: int, capacity: int, latest: array<int, array{name: string, checked_in_at: string}>}
     */
    public function stats(): array
    {
        $checkedIn = $this->registrations()->whereNotNull('checked_in_at');

        return [
            'registered' => $this->registrations()->count(),
            'checked_in' => (clone $checkedIn)->count(),
            'capacity' => $this->capacity,
            'latest' => $checkedIn->latest('checked_in_at')->latest('id')->limit(10)->get(['name', 'checked_in_at'])
                ->map(fn (Registration $r): array => [
                    'name' => $r->name,
                    'checked_in_at' => $r->checked_in_at?->toIso8601String() ?? '',
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }
}
