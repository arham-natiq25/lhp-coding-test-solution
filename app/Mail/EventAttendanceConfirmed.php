<?php

namespace App\Mail;

use App\Models\EventAttendee;
use App\Support\EventPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventAttendanceConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public readonly array $eventPresentation;

    public function __construct(public readonly EventAttendee $attendee)
    {
        $this->attendee->loadMissing('event');
        $this->eventPresentation = app(EventPresenter::class)->forListing($this->attendee->event);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are on the attendee list',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-attendance-confirmed',
        );
    }
}
