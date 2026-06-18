@php
    $presentation = $eventPresentation['presentation'];
    $title = $presentation['title'];
    $venue = $presentation['venue_name'];
    $location = $presentation['location']['label'];
    $time = $presentation['time']['range_label'];
    $timezone = $presentation['time']['timezone'];
    $when = $reminderType === '3_days' ? 'in 3 days' : 'in 24 hours';
@endphp

<p>Hello {{ $attendee->name }},</p>

<p>This is a reminder that <strong>{{ $title }}</strong> is coming up {{ $when }}.</p>

<p>
    <strong>Venue:</strong> {{ $venue }}<br>
    <strong>Location:</strong> {{ $location }}<br>
    @if ($time)
        <strong>Date:</strong> {{ $time }}<br>
        <strong>Timezone:</strong> {{ $timezone }}
    @endif
</p>

<p>We hope to see you there.</p>
