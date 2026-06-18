<?php

namespace App\Http\Controllers;

use App\Mail\EventAttendanceConfirmed;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Support\EventLocationResolver;
use App\Support\EventPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventPresenter $events,
        private readonly EventLocationResolver $locations,
    ) {
        //
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            ...$this->listingProps($request),
        ]);
    }

    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', [
            ...$this->listingProps($request),
        ]);
    }

    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', [
            ...$this->listingProps($request),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);
        $items = $events->getCollection()
            ->map(fn (Event $event) => $this->events->forListing($event))
            ->values();

        return response()->json([
            'data' => $items,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => [
                ...$stats,
                'bytes' => strlen((string) json_encode($items)),
            ],
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load(['user', 'attendees']);

        return Inertia::render('Events/Show', [
            'event' => $this->events->forDetail($event),
        ]);
    }

    public function storeAttendee(Request $request, Event $event): RedirectResponse
    {
        $request->merge([
            'email' => strtolower((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(EventAttendee::class, 'email')->where('event_id', $event->id),
            ],
        ]);

        $attendee = $event->attendees()->create($validated);

        Mail::to($attendee->email)->send(new EventAttendanceConfirmed($attendee));

        $attendee->forceFill([
            'confirmation_sent_at' => now(),
        ])->save();

        return back()->with('success', 'You are on the attendee list.');
    }

    /**
     * @return array{0: LengthAwarePaginator<int, Event>, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);
        $from = $this->dateTimestamp($request->input('from'), 'start');
        $to = $this->dateTimestamp($request->input('to'), 'end');

        $events = Event::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($from !== null, fn ($q) => $q->where('created_time', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_time', '<=', $to))
            ->when($request->filled('location'), fn (Builder $query) => $this->applyLocationFilter($query, (string) $request->input('location')))
            ->orderByDesc('created_time')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => 0,
        ];

        return [$events, $stats];
    }

    /**
     * @param  Builder<Event>  $query
     */
    private function applyLocationFilter(Builder $query, string $location): void
    {
        $anchors = $this->locations->search($location);

        if ($anchors === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($anchors): void {
            foreach ($anchors as $anchor) {
                $query->orWhere(function (Builder $query) use ($anchor): void {
                    $query
                        ->whereBetween('latitude', [$anchor['latitude_min'], $anchor['latitude_max']])
                        ->whereBetween('longitude', [$anchor['longitude_min'], $anchor['longitude_max']]);
                });
            }
        });
    }

    private function dateTimestamp(mixed $value, string $boundary): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $boundary === 'end'
            ? (int) $date->endOfDay()->timestamp
            : (int) $date->startOfDay()->timestamp;
    }

    /**
     * @return array{filters: array{status: mixed, from: mixed, to: mixed, location: mixed}, locationSuggestions: array<int, string>, statuses: array<int, string>}
     */
    private function listingProps(Request $request): array
    {
        return [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
                'to' => $request->input('to'),
                'location' => $request->input('location'),
            ],
            'locationSuggestions' => $this->locations->suggestions(),
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ];
    }
}
