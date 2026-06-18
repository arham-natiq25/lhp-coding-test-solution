<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\EventAttendee;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send event reminder emails 3 days and 24 hours before events start.';

    public function handle(): int
    {
        $now = now();

        $threeDayCount = $this->sendReminderBatch(
            reminderType: '3_days',
            sentColumn: 'reminder_3_days_sent_at',
            startsAfter: (int) $now->copy()->addDay()->timestamp,
            startsBefore: (int) $now->copy()->addDays(3)->timestamp,
        );

        $twentyFourHourCount = $this->sendReminderBatch(
            reminderType: '24_hours',
            sentColumn: 'reminder_24_hours_sent_at',
            startsAfter: (int) $now->timestamp,
            startsBefore: (int) $now->copy()->addDay()->timestamp,
        );

        $this->info("Sent {$threeDayCount} three-day reminders and {$twentyFourHourCount} twenty-four-hour reminders.");

        return self::SUCCESS;
    }

    private function sendReminderBatch(string $reminderType, string $sentColumn, int $startsAfter, int $startsBefore): int
    {
        $sent = 0;

        EventAttendee::query()
            ->with('event')
            ->whereNull($sentColumn)
            ->whereHas('event', function (Builder $query) use ($startsAfter, $startsBefore): void {
                $query
                    ->where('created_time', '>', $startsAfter)
                    ->where('created_time', '<=', $startsBefore);
            })
            ->chunkById(200, function ($attendees) use (&$sent, $reminderType, $sentColumn): void {
                foreach ($attendees as $attendee) {
                    Mail::to($attendee->email)->send(new EventReminder($attendee, $reminderType));

                    $attendee->forceFill([
                        $sentColumn => now(),
                    ])->save();

                    $sent++;
                }
            });

        return $sent;
    }
}
