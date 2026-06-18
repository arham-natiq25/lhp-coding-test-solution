<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock3, MapPin, Receipt, Tag, UserRound, Users } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PresentedImage {
    url: string;
    alt: string;
}

interface EventDetail {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    latitude: number | null;
    longitude: number | null;
    user: { id: number; name: string } | null;
    attendee_count: number;
    attendees: Array<{ id: number; name: string; email: string; created_at: string | null }>;
    payload: Record<string, unknown>;
    presentation: {
        title: string;
        description: string;
        category: string;
        organizer_name: string | null;
        venue_name: string;
        capacity: number | null;
        images: PresentedImage[];
        starts_at_iso: string | null;
        ends_at_iso: string | null;
        time: {
            timezone: string;
            timezone_abbr: string | null;
            date_label: string | null;
            time_label: string | null;
            range_label: string | null;
        };
        coordinates: { latitude: number | null; longitude: number | null };
        location: {
            label: string;
            city: string | null;
            country: string | null;
            region: string | null;
            timezone: string;
            distance_km: number | null;
        };
        pricing: { currency: string; min_price: number | null };
    };
}

const props = defineProps<{ event: EventDetail }>();

const attendeeForm = useForm({
    name: '',
    email: '',
});

const prettyPayload = computed(() => JSON.stringify(props.event.payload, null, 2));
const heroImage = computed(() => props.event.presentation.images[0]?.url ?? '/images/events/city-gathering.svg');
const organizerName = computed(() => props.event.presentation.organizer_name ?? props.event.user?.name ?? 'Independent organizer');

const priceLabel = computed(() => {
    const price = props.event.presentation.pricing.min_price;

    if (price === null || price === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.event.presentation.pricing.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(price);
});

function statusVariant(status: string) {
    switch (status) {
        case 'published':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'sold_out':
            return 'secondary';
        default:
            return 'outline';
    }
}

function registerInterest() {
    attendeeForm.post(`/events/${props.event.id}/attendees`, {
        preserveScroll: true,
        onSuccess: () => attendeeForm.reset(),
    });
}
</script>

<template>
    <Head :title="event.presentation.title" />

    <div class="min-h-full bg-background">
        <section class="border-b">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:px-8">
                <div class="space-y-5">
                    <Button as-child variant="ghost" class="-ml-3 gap-2">
                        <Link href="/events">
                            <ArrowLeft class="size-4" />
                            Back to events
                        </Link>
                    </Button>

                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge>
                            <Badge variant="secondary">{{ event.type }}</Badge>
                            <Badge variant="outline">{{ event.presentation.category }}</Badge>
                        </div>

                        <h1 class="max-w-4xl text-3xl font-semibold leading-tight tracking-normal sm:text-4xl">
                            {{ event.presentation.title }}
                        </h1>

                        <p class="max-w-3xl text-base leading-7 text-muted-foreground">
                            {{ event.presentation.description }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 self-start rounded-lg border bg-card p-4 text-sm shadow-sm">
                    <div class="flex items-start gap-3">
                        <Users class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">{{ event.attendee_count.toLocaleString() }} interested</p>
                            <p class="text-muted-foreground">People on the attendee list</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <CalendarDays class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">{{ event.presentation.time.range_label ?? 'Time to be announced' }}</p>
                            <p class="text-muted-foreground">{{ event.presentation.time.timezone }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <MapPin class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">{{ event.presentation.location.label }}</p>
                            <p class="text-muted-foreground">{{ event.presentation.venue_name }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Receipt class="mt-0.5 size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">{{ priceLabel }}</p>
                            <p class="text-muted-foreground">Starting price</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <main class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-8">
            <div class="space-y-6">
                <div class="h-64 overflow-hidden rounded-lg border bg-muted sm:h-80 lg:h-[360px]">
                    <img :src="heroImage" :alt="event.presentation.images[0]?.alt" class="h-full w-full object-cover" />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="image in event.presentation.images"
                        :key="image.url"
                        class="h-40 overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            :src="image.url"
                            :alt="image.alt"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </div>

                <section class="rounded-lg border bg-card p-5">
                    <h2 class="text-lg font-semibold">About this event</h2>
                    <p class="mt-3 leading-7 text-muted-foreground">{{ event.presentation.description }}</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-md border p-3">
                            <div class="mb-1 flex items-center gap-2 text-sm font-medium">
                                <Clock3 class="size-4 text-muted-foreground" />
                                Date and time
                            </div>
                            <p class="text-sm text-muted-foreground">{{ event.presentation.time.range_label ?? 'Time to be announced' }}</p>
                        </div>

                        <div class="rounded-md border p-3">
                            <div class="mb-1 flex items-center gap-2 text-sm font-medium">
                                <Tag class="size-4 text-muted-foreground" />
                                Category
                            </div>
                            <p class="text-sm text-muted-foreground">{{ event.presentation.category }}</p>
                        </div>

                        <div class="rounded-md border p-3">
                            <div class="mb-1 flex items-center gap-2 text-sm font-medium">
                                <Users class="size-4 text-muted-foreground" />
                                Capacity
                            </div>
                            <p class="text-sm text-muted-foreground">{{ event.presentation.capacity?.toLocaleString() ?? 'To be announced' }}</p>
                        </div>

                        <div class="rounded-md border p-3">
                            <div class="mb-1 flex items-center gap-2 text-sm font-medium">
                                <UserRound class="size-4 text-muted-foreground" />
                                Organizer
                            </div>
                            <p class="text-sm text-muted-foreground">{{ organizerName }}</p>
                        </div>
                    </div>
                </section>

                <details class="rounded-lg border bg-card p-5">
                    <summary class="cursor-pointer text-lg font-semibold">Source payload</summary>
                    <pre class="mt-3 max-h-72 overflow-auto rounded-md bg-muted p-4 text-xs">{{ prettyPayload }}</pre>
                </details>
            </div>

            <aside class="h-fit space-y-4 lg:sticky lg:top-4">
                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-semibold">Register interest</h2>
                    <form class="mt-3 grid gap-3" @submit.prevent="registerInterest">
                        <div class="grid gap-2">
                            <Label for="attendee-name">Name</Label>
                            <Input
                                id="attendee-name"
                                v-model="attendeeForm.name"
                                name="name"
                                autocomplete="name"
                                placeholder="Full name"
                            />
                            <InputError :message="attendeeForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="attendee-email">Email</Label>
                            <Input
                                id="attendee-email"
                                v-model="attendeeForm.email"
                                name="email"
                                type="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                            />
                            <InputError :message="attendeeForm.errors.email" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="attendeeForm.processing">
                            {{ attendeeForm.processing ? 'Registering...' : 'Join attendee list' }}
                        </Button>

                        <p v-if="attendeeForm.recentlySuccessful" class="text-sm font-medium text-green-600">
                            You are on the attendee list.
                        </p>
                    </form>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold">Attendees</h2>
                        <Badge variant="secondary">{{ event.attendee_count }}</Badge>
                    </div>
                    <div class="mt-3 grid gap-2">
                        <div v-for="attendee in event.attendees" :key="attendee.id" class="rounded-md border p-3 text-sm">
                            <p class="font-medium">{{ attendee.name }}</p>
                            <p class="text-muted-foreground">{{ attendee.email }}</p>
                        </div>
                        <p v-if="event.attendees.length === 0" class="text-sm text-muted-foreground">
                            No attendees yet.
                        </p>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-semibold">Location</h2>
                    <div class="mt-3 space-y-3 text-sm">
                        <p class="font-medium">{{ event.presentation.venue_name }}</p>
                        <p class="text-muted-foreground">{{ event.presentation.location.label }}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                            <div class="rounded-md border p-2">
                                <p>Latitude</p>
                                <p class="mt-1 font-mono text-foreground">{{ event.presentation.coordinates.latitude ?? 'n/a' }}</p>
                            </div>
                            <div class="rounded-md border p-2">
                                <p>Longitude</p>
                                <p class="mt-1 font-mono text-foreground">{{ event.presentation.coordinates.longitude ?? 'n/a' }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-semibold">Event details</h2>
                    <dl class="mt-3 grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Status</dt>
                            <dd><Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Type</dt>
                            <dd class="font-medium">{{ event.type }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Price</dt>
                            <dd class="font-medium">{{ priceLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Timezone</dt>
                            <dd class="font-medium">{{ event.presentation.time.timezone_abbr ?? event.presentation.time.timezone }}</dd>
                        </div>
                    </dl>
                </section>

                <Button as-child class="w-full">
                    <Link href="/events-visual-1">Browse more events</Link>
                </Button>
            </aside>
        </main>
    </div>
</template>
