<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $code
 * @property TicketStatus $ticket_status
 * @property string|null $ticket_path
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 */
#[Fillable(['name', 'email', 'code', 'ticket_status', 'ticket_path', 'checked_in_at'])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory, HasUlids;

    /**
     * The ULID is the participant's only key to their ticket, so it is the
     * route key; the numeric id never leaves the server.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_status' => TicketStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }
}
