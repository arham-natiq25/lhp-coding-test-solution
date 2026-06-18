<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Support\Carbon;

class EventPresenter
{
    private const IMAGE_CATALOG = [
        'concert' => ['live-stage.svg', 'city-gathering.svg'],
        'festival' => ['city-gathering.svg', 'live-stage.svg'],
        'conference' => ['conference-hall.svg', 'workshop-table.svg'],
        'meetup' => ['city-gathering.svg', 'workshop-table.svg'],
        'workshop' => ['workshop-table.svg', 'conference-hall.svg'],
        'sports' => ['city-gathering.svg', 'live-stage.svg'],
        'networking' => ['conference-hall.svg', 'city-gathering.svg'],
        'exhibition' => ['workshop-table.svg', 'city-gathering.svg'],
    ];

    public function __construct(private readonly EventLocationResolver $locations)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function forListing(Event $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'status' => $event->status,
            'created_time' => $event->created_time,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'user' => $this->user($event),
            'presentation' => $this->presentation($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDetail(Event $event): array
    {
        return [
            ...$this->forListing($event),
            'attendees' => $this->attendees($event),
            'attendee_count' => $event->relationLoaded('attendees')
                ? $event->attendees->count()
                : $event->attendees()->count(),
            'payload' => $event->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentation(Event $event): array
    {
        $payload = $event->payload ?? [];
        $venueName = $this->stringValue(data_get($payload, 'venue.name')) ?? 'Venue to be announced';
        $startsAt = $this->timestamp(data_get($payload, 'schedule.starts_at')) ?? $event->created_time;
        $endsAt = $this->timestamp(data_get($payload, 'schedule.ends_at'));
        $location = $this->locations->resolve($event->latitude, $event->longitude);
        $time = $this->time($startsAt, $endsAt, $location['timezone']);

        return [
            'title' => $this->stringValue(data_get($payload, 'name')) ?? $this->fallbackTitle($event),
            'description' => $this->stringValue(data_get($payload, 'description')) ?? $this->fallbackDescription($event, $venueName),
            'category' => $this->stringValue(data_get($payload, 'category')) ?? $event->type,
            'organizer_name' => $this->stringValue(data_get($payload, 'organizer.name')),
            'venue_name' => $venueName,
            'capacity' => $this->integerValue(data_get($payload, 'venue.capacity')),
            'images' => $this->images($event),
            'starts_at_timestamp' => $startsAt,
            'ends_at_timestamp' => $endsAt,
            'starts_at_iso' => $this->isoDate($startsAt),
            'ends_at_iso' => $this->isoDate($endsAt),
            'time' => $time,
            'coordinates' => [
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
            ],
            'location' => $location,
            'pricing' => [
                'currency' => $this->stringValue(data_get($payload, 'pricing.currency')) ?? 'USD',
                'min_price' => $this->floatValue(data_get($payload, 'pricing.min_price')),
            ],
        ];
    }

    /**
     * @return array<int, array{url: string, alt: string}>
     */
    private function images(Event $event): array
    {
        $filenames = self::IMAGE_CATALOG[$event->type] ?? ['city-gathering.svg', 'live-stage.svg'];
        $title = $this->stringValue(data_get($event->payload ?? [], 'name')) ?? $this->fallbackTitle($event);

        return array_map(
            fn (string $filename): array => [
                'url' => asset("images/events/{$filename}"),
                'alt' => "{$title} event image",
            ],
            $filenames,
        );
    }

    /**
     * @return array<int, array{id: int, name: string, email: string, created_at: string|null}>
     */
    private function attendees(Event $event): array
    {
        if (! $event->relationLoaded('attendees')) {
            return [];
        }

        return $event->attendees
            ->sortBy('created_at')
            ->map(fn (EventAttendee $attendee): array => [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
                'created_at' => $attendee->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{timezone: string, timezone_abbr: string|null, starts_at_local_iso: string|null, ends_at_local_iso: string|null, date_label: string|null, time_label: string|null, range_label: string|null}
     */
    private function time(?int $startsAt, ?int $endsAt, string $timezone): array
    {
        if ($startsAt === null) {
            return [
                'timezone' => $timezone,
                'timezone_abbr' => null,
                'starts_at_local_iso' => null,
                'ends_at_local_iso' => null,
                'date_label' => null,
                'time_label' => null,
                'range_label' => null,
            ];
        }

        $start = Carbon::createFromTimestampUTC($startsAt)->setTimezone($timezone);
        $end = $endsAt === null ? null : Carbon::createFromTimestampUTC($endsAt)->setTimezone($timezone);

        return [
            'timezone' => $timezone,
            'timezone_abbr' => $start->format('T'),
            'starts_at_local_iso' => $start->toIso8601String(),
            'ends_at_local_iso' => $end?->toIso8601String(),
            'date_label' => $start->format('D, M j, Y'),
            'time_label' => $start->format('g:i A T'),
            'range_label' => $this->timeRangeLabel($start, $end),
        ];
    }

    private function timeRangeLabel(Carbon $start, ?Carbon $end): string
    {
        if ($end === null) {
            return $start->format('D, M j, Y, g:i A T');
        }

        if ($start->isSameDay($end)) {
            return "{$start->format('D, M j, Y, g:i A')} - {$end->format('g:i A T')}";
        }

        return "{$start->format('D, M j, Y, g:i A')} - {$end->format('D, M j, Y, g:i A T')}";
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function user(Event $event): ?array
    {
        if (! $event->relationLoaded('user') || $event->user === null) {
            return null;
        }

        return [
            'id' => $event->user->id,
            'name' => $event->user->name,
        ];
    }

    private function fallbackTitle(Event $event): string
    {
        return str($event->type)->replace('_', ' ')->title()->append(' Event')->toString();
    }

    private function fallbackDescription(Event $event, string $venueName): string
    {
        return sprintf('A %s event hosted at %s.', str_replace('_', ' ', $event->type), $venueName);
    }

    private function isoDate(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return Carbon::createFromTimestampUTC($timestamp)->toIso8601String();
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function integerValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function floatValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
