<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarRange, Clock3, Filter, MapPin, Search, Ticket } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface PresentedImage {
    url: string;
    alt: string;
}

interface EventAgendaItem {
    id: string;
    type: string;
    status: string;
    user: { id: number; name: string } | null;
    presentation: {
        title: string;
        description: string;
        venue_name: string;
        images: PresentedImage[];
        location: { label: string; city: string | null; country: string | null; region: string | null };
        time: {
            starts_at_local_iso: string | null;
            date_label: string | null;
            time_label: string | null;
            range_label: string | null;
        };
        pricing: { currency: string; min_price: number | null };
    };
}

interface AgendaGroup {
    date: string;
    events: EventAgendaItem[];
}

const props = defineProps<{
    filters: { status: string | null; from: string; to: string | null; location: string | null };
    locationSuggestions: string[];
    statuses: string[];
}>();

const form = reactive({
    status: props.filters.status ?? 'published',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    location: props.filters.location ?? '',
});

const events = ref<EventAgendaItem[]>([]);
const page = ref(0);
const hasMore = ref(true);
const total = ref<number | null>(null);
const loading = ref(false);
const loadedOnce = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const visibleEvents = computed(() =>
    events.value.filter((event) => {
        const localIso = event.presentation.time.starts_at_local_iso;

        if (!localIso) {
            return true;
        }

        const localDate = localIso.slice(0, 10);

        if (form.from && localDate < form.from) {
            return false;
        }

        if (form.to && localDate > form.to) {
            return false;
        }

        return true;
    }),
);

const groups = computed<AgendaGroup[]>(() => {
    const grouped = new Map<string, EventAgendaItem[]>();

    for (const event of visibleEvents.value) {
        const date = event.presentation.time.date_label ?? 'Date to be announced';
        grouped.set(date, [...(grouped.get(date) ?? []), event]);
    }

    return Array.from(grouped.entries()).map(([date, items]) => ({
        date,
        events: items,
    }));
});

const resultLabel = computed(() => {
    if (total.value !== null) {
        return `${total.value.toLocaleString()} scheduled ${total.value === 1 ? 'event' : 'events'}`;
    }

    if (!loadedOnce.value) {
        return 'Loading agenda';
    }

    return `${visibleEvents.value.length.toLocaleString()} loaded ${visibleEvents.value.length === 1 ? 'event' : 'events'}`;
});

const typeSummary = computed(() => {
    const counts = new Map<string, number>();

    for (const event of visibleEvents.value) {
        counts.set(event.type, (counts.get(event.type) ?? 0) + 1);
    }

    return Array.from(counts.entries())
        .sort((a, b) => b[1] - a[1])
        .slice(0, 6);
});

const locationSummary = computed(() => {
    const counts = new Map<string, number>();

    for (const event of visibleEvents.value) {
        counts.set(event.presentation.location.label, (counts.get(event.presentation.location.label) ?? 0) + 1);
    }

    return Array.from(counts.entries())
        .sort((a, b) => b[1] - a[1])
        .slice(0, 6);
});

async function loadMore() {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;
    const params = new URLSearchParams({ page: String(page.value + 1) });

    if (form.status) params.set('status', form.status);
    if (form.from) params.set('from', form.from);
    if (form.to) params.set('to', form.to);
    if (form.location) params.set('location', form.location);
    params.set('sort', 'asc');

    try {
        const response = await fetch(`/events/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();

        events.value.push(...payload.data);
        page.value = payload.current_page;
        hasMore.value = payload.has_more;
        total.value = payload.total;
        loadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    events.value = [];
    page.value = 0;
    hasMore.value = true;
    total.value = null;
    loadedOnce.value = false;
    loadMore();
}

function clearFilters() {
    form.status = 'published';
    form.from = '';
    form.to = '';
    form.location = '';
    applyFilters();
}

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

function priceLabel(event: EventAgendaItem) {
    const price = event.presentation.pricing.min_price;

    if (price === null || price === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: event.presentation.pricing.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(price);
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '500px' },
    );

    if (sentinel.value) {
        observer.observe(sentinel.value);
    }

    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events Visual 2" />

    <div class="min-h-full bg-background">
        <section class="border-b bg-muted/30">
            <div class="mx-auto grid max-w-7xl gap-5 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_auto] lg:px-8">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Event Visual 2</p>
                    <h1 class="text-3xl font-semibold tracking-normal">Agenda timeline</h1>
                </div>
                <div class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm text-muted-foreground shadow-sm">
                    <CalendarRange class="size-4" />
                    <span>{{ resultLabel }}</span>
                </div>
            </div>
        </section>

        <main class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[290px_1fr] lg:px-8">
            <aside class="h-fit rounded-lg border bg-background p-3 shadow-sm lg:sticky lg:top-4">
                <div class="mb-3 flex items-center gap-2 text-sm font-medium">
                    <Filter class="size-4" />
                    Filters
                </div>

                <form class="grid gap-3" @submit.prevent="applyFilters">
                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        Status
                        <select v-model="form.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground">
                            <option value="">All</option>
                            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </label>

                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        From
                        <input v-model="form.from" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" />
                    </label>

                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        To
                        <input v-model="form.to" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" />
                    </label>

                    <label class="grid gap-1 text-xs font-medium text-muted-foreground">
                        Location
                        <input
                            v-model="form.location"
                            list="visual-two-location-suggestions"
                            type="search"
                            placeholder="City, country, or region"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                        />
                        <datalist id="visual-two-location-suggestions">
                            <option v-for="suggestion in locationSuggestions" :key="suggestion" :value="suggestion" />
                        </datalist>
                    </label>

                    <div class="grid grid-cols-2 gap-2">
                        <Button type="submit" class="gap-2">
                            <Search class="size-4" />
                            Apply
                        </Button>
                        <Button type="button" variant="outline" @click="clearFilters">Reset</Button>
                    </div>
                </form>

                <div class="mt-5 border-t pt-4">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Loaded Types</p>
                    <div class="grid gap-2">
                        <div v-for="[type, count] in typeSummary" :key="type" class="flex items-center justify-between text-sm">
                            <span>{{ type }}</span>
                            <Badge variant="secondary">{{ count }}</Badge>
                        </div>
                        <p v-if="typeSummary.length === 0" class="text-sm text-muted-foreground">Waiting for events</p>
                    </div>
                </div>

                <div class="mt-5 border-t pt-4">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Loaded Locations</p>
                    <div class="grid gap-2">
                        <div v-for="[location, count] in locationSummary" :key="location" class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate">{{ location }}</span>
                            <Badge variant="outline">{{ count }}</Badge>
                        </div>
                        <p v-if="locationSummary.length === 0" class="text-sm text-muted-foreground">No locations yet</p>
                    </div>
                </div>
            </aside>

            <section class="min-w-0">
                <div class="overflow-hidden rounded-lg border bg-background shadow-sm">
                    <div class="grid grid-cols-[96px_1fr] border-b bg-muted/40 px-4 py-3 text-xs font-medium uppercase text-muted-foreground sm:grid-cols-[130px_1fr]">
                        <span>Local Time</span>
                        <span>Event</span>
                    </div>

                    <div v-for="group in groups" :key="group.date">
                        <div class="sticky top-0 z-10 border-b bg-background/95 px-4 py-3 backdrop-blur">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="font-semibold">{{ group.date }}</h2>
                                <span class="text-sm text-muted-foreground">{{ group.events.length }} {{ group.events.length === 1 ? 'event' : 'events' }}</span>
                            </div>
                        </div>

                        <article
                            v-for="event in group.events"
                            :key="event.id"
                            class="grid grid-cols-[96px_1fr] gap-3 border-b px-4 py-4 transition hover:bg-muted/30 sm:grid-cols-[130px_1fr]"
                        >
                            <div class="pt-1">
                                <div class="font-mono text-sm font-semibold">{{ event.presentation.time.time_label ?? 'TBA' }}</div>
                                <div class="mt-1 text-xs text-muted-foreground">{{ priceLabel(event) }}</div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-[88px_1fr_auto] sm:items-start">
                                <img
                                    :src="event.presentation.images[0]?.url"
                                    :alt="event.presentation.images[0]?.alt"
                                    class="hidden aspect-square rounded-md border object-cover sm:block"
                                />

                                <div class="min-w-0">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge>
                                        <Badge variant="secondary">{{ event.type }}</Badge>
                                    </div>
                                    <h3 class="text-base font-semibold leading-snug">{{ event.presentation.title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">{{ event.presentation.description }}</p>
                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
                                        <span class="inline-flex items-center gap-1.5">
                                            <MapPin class="size-4" />
                                            {{ event.presentation.location.label }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <Clock3 class="size-4" />
                                            {{ event.presentation.venue_name }}
                                        </span>
                                    </div>
                                </div>

                                <Button as-child variant="outline" size="sm" class="sm:mt-8">
                                    <Link :href="`/events/${event.id}`">Details</Link>
                                </Button>
                            </div>
                        </article>
                    </div>

                    <div v-if="loading" class="grid gap-0">
                        <div v-for="index in 6" :key="index" class="grid grid-cols-[96px_1fr] gap-3 border-b px-4 py-4 sm:grid-cols-[130px_1fr]">
                            <div class="h-5 animate-pulse rounded bg-muted"></div>
                            <div class="space-y-3">
                                <div class="h-5 w-2/3 animate-pulse rounded bg-muted"></div>
                                <div class="h-4 w-full animate-pulse rounded bg-muted"></div>
                                <div class="h-4 w-1/2 animate-pulse rounded bg-muted"></div>
                            </div>
                        </div>
                    </div>

                    <div v-if="loadedOnce && visibleEvents.length === 0 && !loading" class="p-10 text-center">
                        <Ticket class="mx-auto mb-3 size-8 text-muted-foreground" />
                        <p class="text-lg font-medium">No agenda items found</p>
                        <p class="mt-1 text-sm text-muted-foreground">Adjust the filters to rebuild the schedule.</p>
                    </div>
                </div>

                <div ref="sentinel" class="h-8"></div>

                <div v-if="loadedOnce && !hasMore && visibleEvents.length > 0" class="py-6 text-center text-sm text-muted-foreground">
                    End of agenda
                </div>
            </section>
        </main>
    </div>
</template>
