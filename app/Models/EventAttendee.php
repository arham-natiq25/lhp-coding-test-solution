<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property int $event_id
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $reminder_3_days_sent_at
 * @property Carbon|null $reminder_24_hours_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 */
#[Fillable(['event_id', 'name', 'email', 'confirmation_sent_at', 'reminder_3_days_sent_at', 'reminder_24_hours_sent_at'])]
class EventAttendee extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmation_sent_at' => 'datetime',
            'reminder_3_days_sent_at' => 'datetime',
            'reminder_24_hours_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
