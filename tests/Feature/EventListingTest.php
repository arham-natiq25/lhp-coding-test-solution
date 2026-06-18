<?php

use App\Mail\EventAttendanceConfirmed;
use App\Mail\EventReminder;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('renders the events listing shell without authentication', function () {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->has('statuses', 4)
            ->has('locationSuggestions')
            ->where('filters.from', '2023-01-01')
            ->where('filters.to', null)
            ->where('filters.location', null)
        );
});

it('returns a json page of events with load stats for lazy loading', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    Event::factory()->for($user)->create([
        'type' => 'concert',
        'status' => 'published',
        'created_time' => 1_700_000_000,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'payload' => [
            'name' => 'Midnight Jazz Festival',
            'description' => 'A late-night city concert.',
            'category' => 'concert',
            'organizer' => ['name' => 'Organizer 42'],
            'venue' => ['name' => 'The Grand Hall', 'capacity' => '1200'],
            'schedule' => ['starts_at' => '1700000000', 'ends_at' => '1700007200'],
            'pricing' => ['currency' => 'USD', 'min_price' => '25.50'],
        ],
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'has_more',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('total', null)
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('data.0.type', 'concert')
        ->assertJsonPath('data.0.created_time', 1_700_000_000)
        ->assertJsonPath('data.0.latitude', 40.7128)
        ->assertJsonPath('data.0.user.name', 'Ada Lovelace')
        ->assertJsonPath('data.0.presentation.title', 'Midnight Jazz Festival')
        ->assertJsonPath('data.0.presentation.description', 'A late-night city concert.')
        ->assertJsonPath('data.0.presentation.venue_name', 'The Grand Hall')
        ->assertJsonPath('data.0.presentation.starts_at_iso', '2023-11-14T22:13:20+00:00')
        ->assertJsonPath('data.0.presentation.ends_at_timestamp', 1_700_007_200)
        ->assertJsonPath('data.0.presentation.time.timezone', 'America/New_York')
        ->assertJsonPath('data.0.presentation.time.timezone_abbr', 'EST')
        ->assertJsonPath('data.0.presentation.time.starts_at_local_iso', '2023-11-14T17:13:20-05:00')
        ->assertJsonPath('data.0.presentation.time.ends_at_local_iso', '2023-11-14T19:13:20-05:00')
        ->assertJsonPath('data.0.presentation.time.date_label', 'Tue, Nov 14, 2023')
        ->assertJsonPath('data.0.presentation.time.time_label', '5:13 PM EST')
        ->assertJsonPath('data.0.presentation.time.range_label', 'Tue, Nov 14, 2023, 5:13 PM - 7:13 PM EST')
        ->assertJsonPath('data.0.presentation.coordinates.latitude', 40.7128)
        ->assertJsonPath('data.0.presentation.location.label', 'New York, United States')
        ->assertJsonPath('data.0.presentation.location.city', 'New York')
        ->assertJsonPath('data.0.presentation.location.country', 'United States')
        ->assertJsonPath('data.0.presentation.location.region', 'North America')
        ->assertJsonPath('data.0.presentation.location.timezone', 'America/New_York')
        ->assertJsonPath('data.0.presentation.pricing.min_price', 25.5)
        ->assertJsonCount(2, 'data.0.presentation.images')
        ->assertJsonPath('data.0.presentation.images.0.url', asset('images/events/live-stage.svg'))
        ->assertJsonPath('data.0.presentation.images.1.url', asset('images/events/city-gathering.svg'))
        ->assertJsonPath('data.0.presentation.images.0.alt', 'Midnight Jazz Festival event image');
});

it('serves local image assets for presented events', function () {
    expect(public_path('images/events/live-stage.svg'))->toBeFile()
        ->and(public_path('images/events/conference-hall.svg'))->toBeFile()
        ->and(public_path('images/events/workshop-table.svg'))->toBeFile()
        ->and(public_path('images/events/city-gathering.svg'))->toBeFile();
});

it('filters the data endpoint by status', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['status' => 'published']);
    Event::factory()->for($user)->create(['status' => 'cancelled']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('filters the data endpoint by date range', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'created_time' => strtotime('2024-01-15 12:00:00 UTC'),
        'payload' => ['name' => 'January Event'],
    ]);
    Event::factory()->for($user)->create([
        'created_time' => strtotime('2024-02-15 12:00:00 UTC'),
        'payload' => ['name' => 'February Event'],
    ]);

    $this->getJson(route('events.data', [
        'from' => '2024-01-01',
        'to' => '2024-01-31',
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.presentation.title', 'January Event');
});

it('returns ascending results when requested for agenda views', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'created_time' => strtotime('2024-02-15 12:00:00 UTC'),
        'payload' => ['name' => 'Later Event'],
    ]);
    Event::factory()->for($user)->create([
        'created_time' => strtotime('2024-01-15 12:00:00 UTC'),
        'payload' => ['name' => 'Earlier Event'],
    ]);

    $this->getJson(route('events.data', ['sort' => 'asc']))
        ->assertOk()
        ->assertJsonPath('data.0.presentation.title', 'Earlier Event')
        ->assertJsonPath('data.1.presentation.title', 'Later Event');
});

it('filters the data endpoint by readable location text', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'payload' => ['name' => 'New York Event'],
    ]);
    Event::factory()->for($user)->create([
        'latitude' => 34.0522,
        'longitude' => -118.2437,
        'payload' => ['name' => 'Los Angeles Event'],
    ]);

    $this->getJson(route('events.data', ['location' => 'New York']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.presentation.title', 'New York Event')
        ->assertJsonPath('data.0.presentation.location.label', 'New York, United States');
});

it('filters the data endpoint by combined city and country text', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => 43.6532,
        'longitude' => -79.3832,
        'payload' => ['name' => 'Toronto Event'],
    ]);
    Event::factory()->for($user)->create([
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'payload' => ['name' => 'New York Event'],
    ]);

    $this->getJson(route('events.data', ['location' => 'Toronto Canada']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.presentation.title', 'Toronto Event')
        ->assertJsonPath('data.0.presentation.location.label', 'Toronto, Canada');
});

it('filters the data endpoint when the location query contains a small typo', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => 43.6532,
        'longitude' => -79.3832,
        'payload' => ['name' => 'Toronto Event'],
    ]);
    Event::factory()->for($user)->create([
        'latitude' => 34.0522,
        'longitude' => -118.2437,
        'payload' => ['name' => 'Los Angeles Event'],
    ]);

    $this->getJson(route('events.data', ['location' => 'Toronto Candana']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.presentation.title', 'Toronto Event')
        ->assertJsonPath('data.0.presentation.location.label', 'Toronto, Canada');
});

it('returns no events when the location filter has no known match', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    $this->getJson(route('events.data', ['location' => 'Atlantis']))
        ->assertOk()
        ->assertJsonPath('total', null)
        ->assertJsonCount(0, 'data');
});

it('falls back gracefully when an event has no coordinates', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonPath('data.0.presentation.coordinates.latitude', null)
        ->assertJsonPath('data.0.presentation.coordinates.longitude', null)
        ->assertJsonPath('data.0.presentation.location.label', 'Location to be announced')
        ->assertJsonPath('data.0.presentation.location.city', null)
        ->assertJsonPath('data.0.presentation.location.country', null)
        ->assertJsonPath('data.0.presentation.location.region', null)
        ->assertJsonPath('data.0.presentation.location.timezone', 'UTC')
        ->assertJsonPath('data.0.presentation.location.distance_km', null)
        ->assertJsonPath('data.0.presentation.time.timezone', 'UTC')
        ->assertJsonPath('data.0.presentation.time.timezone_abbr', 'UTC');
});

it('shows an event detail page with its payload', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user)->create([
        'type' => 'conference',
        'created_time' => 1_700_000_000,
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'payload' => [
            'name' => 'Global Tech Summit',
            'description' => 'A practical conference for builders.',
            'category' => 'conference',
            'organizer' => ['name' => 'Organizer 99'],
            'location' => ['lat' => 1.5, 'lng' => 2.5],
            'venue' => ['name' => 'Skyline Pavilion', 'capacity' => '900'],
            'schedule' => ['starts_at' => '1700000000', 'ends_at' => '1700007200'],
            'pricing' => ['currency' => 'USD', 'min_price' => '125'],
        ],
    ]);
    EventAttendee::create([
        'event_id' => $event->id,
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.payload.name', 'Global Tech Summit')
            ->where('event.presentation.title', 'Global Tech Summit')
            ->where('event.presentation.description', 'A practical conference for builders.')
            ->where('event.presentation.venue_name', 'Skyline Pavilion')
            ->where('event.presentation.organizer_name', 'Organizer 99')
            ->where('event.presentation.capacity', 900)
            ->where('event.presentation.location.label', 'New York, United States')
            ->where('event.presentation.time.range_label', 'Tue, Nov 14, 2023, 5:13 PM - 7:13 PM EST')
            ->where('event.presentation.pricing.min_price', 125)
            ->where('event.attendee_count', 1)
            ->where('event.attendees.0.name', 'Grace Hopper')
            ->where('event.attendees.0.email', 'grace@example.com')
            ->has('event.presentation.images', 2)
        );
});

it('registers an attendee for an event', function () {
    Mail::fake();

    $event = Event::factory()->for(User::factory())->create();

    $this->from(route('events.show', $event))
        ->post(route('events.attendees.store', $event), [
            'name' => 'Ada Lovelace',
            'email' => 'ADA@example.com',
        ])
        ->assertRedirect(route('events.show', $event));

    $this->assertDatabaseHas('event_attendees', [
        'event_id' => $event->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $attendee = EventAttendee::where('email', 'ada@example.com')->first();

    expect($attendee->confirmation_sent_at)->not->toBeNull();

    Mail::assertSent(EventAttendanceConfirmed::class, function (EventAttendanceConfirmed $mail) use ($attendee) {
        return $mail->hasTo('ada@example.com')
            && $mail->attendee->is($attendee);
    });
});

it('renders confirmation emails with the event local timezone', function () {
    $event = Event::factory()->for(User::factory())->create([
        'created_time' => strtotime('2027-06-18 03:33:00 UTC'),
        'latitude' => 37.3382,
        'longitude' => -121.8863,
        'payload' => [
            'name' => 'Annual Synthwave Retreat',
            'venue' => ['name' => 'The Grand Warehouse'],
            'schedule' => [
                'starts_at' => (string) strtotime('2027-06-18 03:33:00 UTC'),
                'ends_at' => (string) strtotime('2027-06-19 22:36:00 UTC'),
            ],
        ],
    ]);
    $attendee = EventAttendee::create([
        'event_id' => $event->id,
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);

    $html = (new EventAttendanceConfirmed($attendee))->render();

    expect($html)
        ->toContain('Annual Synthwave Retreat')
        ->toContain('San Jose, United States')
        ->toContain('Thu, Jun 17, 2027, 8:33 PM')
        ->toContain('PDT')
        ->toContain('America/Los_Angeles')
        ->not->toContain('GMT+0000');
});

it('prevents duplicate attendee emails for the same event', function () {
    Mail::fake();

    $event = Event::factory()->for(User::factory())->create();
    EventAttendee::create([
        'event_id' => $event->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $this->from(route('events.show', $event))
        ->post(route('events.attendees.store', $event), [
            'name' => 'Ada Again',
            'email' => 'ADA@example.com',
        ])
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHasErrors('email');

    expect(EventAttendee::where('event_id', $event->id)->count())->toBe(1);
    Mail::assertNothingSent();
});

it('allows the same attendee email on different events', function () {
    Mail::fake();

    $firstEvent = Event::factory()->for(User::factory())->create();
    $secondEvent = Event::factory()->for(User::factory())->create();

    EventAttendee::create([
        'event_id' => $firstEvent->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $this->post(route('events.attendees.store', $secondEvent), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])->assertRedirect();

    expect(EventAttendee::where('email', 'ada@example.com')->count())->toBe(2);
    Mail::assertSent(EventAttendanceConfirmed::class);
});

it('sends three day reminder emails for upcoming attendees', function () {
    Mail::fake();
    Carbon::setTestNow('2026-06-18 12:00:00');

    try {
        $event = Event::factory()->for(User::factory())->create([
            'created_time' => now()->addDays(3)->timestamp,
            'payload' => ['name' => 'Three Day Event'],
        ]);
        $attendee = EventAttendee::create([
            'event_id' => $event->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        Artisan::call('events:send-reminders');

        Mail::assertSent(EventReminder::class, function (EventReminder $mail) use ($attendee) {
            return $mail->hasTo('ada@example.com')
                && $mail->attendee->is($attendee)
                && $mail->reminderType === '3_days';
        });

        $attendee->refresh();

        expect($attendee->reminder_3_days_sent_at)->not->toBeNull()
            ->and($attendee->reminder_24_hours_sent_at)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('sends twenty four hour reminder emails for upcoming attendees', function () {
    Mail::fake();
    Carbon::setTestNow('2026-06-18 12:00:00');

    try {
        $event = Event::factory()->for(User::factory())->create([
            'created_time' => now()->addDay()->timestamp,
            'payload' => ['name' => 'Tomorrow Event'],
        ]);
        $attendee = EventAttendee::create([
            'event_id' => $event->id,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        Artisan::call('events:send-reminders');

        Mail::assertSent(EventReminder::class, function (EventReminder $mail) use ($attendee) {
            return $mail->hasTo('grace@example.com')
                && $mail->attendee->is($attendee)
                && $mail->reminderType === '24_hours';
        });

        $attendee->refresh();

        expect($attendee->reminder_24_hours_sent_at)->not->toBeNull()
            ->and($attendee->reminder_3_days_sent_at)->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('renders reminder emails with the event local timezone', function () {
    $event = Event::factory()->for(User::factory())->create([
        'created_time' => strtotime('2027-06-18 03:33:00 UTC'),
        'latitude' => 37.3382,
        'longitude' => -121.8863,
        'payload' => [
            'name' => 'Annual Synthwave Retreat',
            'venue' => ['name' => 'The Grand Warehouse'],
            'schedule' => [
                'starts_at' => (string) strtotime('2027-06-18 03:33:00 UTC'),
                'ends_at' => (string) strtotime('2027-06-19 22:36:00 UTC'),
            ],
        ],
    ]);
    $attendee = EventAttendee::create([
        'event_id' => $event->id,
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);

    $html = (new EventReminder($attendee, '24_hours'))->render();

    expect($html)
        ->toContain('Annual Synthwave Retreat')
        ->toContain('San Jose, United States')
        ->toContain('Thu, Jun 17, 2027, 8:33 PM')
        ->toContain('PDT')
        ->toContain('America/Los_Angeles')
        ->not->toContain('GMT+0000');
});

it('does not resend reminder emails that were already sent', function () {
    Mail::fake();
    Carbon::setTestNow('2026-06-18 12:00:00');

    try {
        $event = Event::factory()->for(User::factory())->create([
            'created_time' => now()->addDays(2)->timestamp,
            'payload' => ['name' => 'Already Reminded Event'],
        ]);
        EventAttendee::create([
            'event_id' => $event->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'reminder_3_days_sent_at' => now(),
        ]);

        Artisan::call('events:send-reminders');

        Mail::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the first event visual with filter props', function () {
    $this->get(route('events.visual1', [
        'status' => 'published',
        'from' => '2024-01-01',
        'to' => '2024-01-31',
        'location' => 'New York',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualOne')
            ->has('statuses', 4)
            ->has('locationSuggestions')
            ->where('filters.status', 'published')
            ->where('filters.from', '2024-01-01')
            ->where('filters.to', '2024-01-31')
            ->where('filters.location', 'New York')
        );
});

it('renders the second event visual with filter props', function () {
    $this->get(route('events.visual2', [
        'status' => 'sold_out',
        'from' => '2024-02-01',
        'to' => '2024-02-29',
        'location' => 'Europe',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/VisualTwo')
            ->has('statuses', 4)
            ->has('locationSuggestions')
            ->where('filters.status', 'sold_out')
            ->where('filters.from', '2024-02-01')
            ->where('filters.to', '2024-02-29')
            ->where('filters.location', 'Europe')
        );
});

it('renders the dashboard without authentication', function () {
    $this->get(route('dashboard'))->assertOk();
});
