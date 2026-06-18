@php
    $presentation = $eventPresentation['presentation'];
    $title = $presentation['title'];
    $venue = $presentation['venue_name'];
    $location = $presentation['location']['label'];
    $time = $presentation['time']['range_label'];
    $timezone = $presentation['time']['timezone'];
@endphp

<p>Hello {{ $attendee->name }},</p>

<p>You are on the attendee list for <strong>{{ $title }}</strong>.</p>

<p>
    <strong>Venue:</strong> {{ $venue }}<br>
    <strong>Location:</strong> {{ $location }}<br>
    @if ($time)
        <strong>Date:</strong> {{ $time }}<br>
        <strong>Timezone:</strong> {{ $timezone }}
    @endif
</p>

<p>We will send reminders as the event gets closer.</p>
