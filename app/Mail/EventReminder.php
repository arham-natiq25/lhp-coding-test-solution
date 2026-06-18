<?php

namespace App\Mail;

use App\Models\EventAttendee;
use App\Support\EventPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public readonly array $eventPresentation;

    public function __construct(
        public readonly EventAttendee $attendee,
        public readonly string $reminderType,
    ) {
        $this->attendee->loadMissing('event');
        $this->eventPresentation = app(EventPresenter::class)->forListing($this->attendee->event);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminderType === '3_days'
                ? 'Your event is coming up in 3 days'
                : 'Your event is coming up in 24 hours',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-reminder',
        );
    }
}
