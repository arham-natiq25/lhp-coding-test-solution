<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Search, Ticket, Users } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface PresentedImage {
    url: string;
    alt: string;
}

interface EventCard {
    id: string;
    type: string;
    status: string;
    user: { id: number; name: string } | null;
    presentation: {
        title: string;
        description: string;
        venue_name: string;
        images: PresentedImage[];
        location: { label: string; city: string | null; country: string | null };
        time: { date_label: string | null; time_label: string | null; range_label: string | null };
        pricing: { currency: string; min_price: number | null };
    };
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

const events = ref<EventCard[]>([]);
const page = ref(0);
const hasMore = ref(true);
const total = ref<number | null>(null);
const loading = ref(false);
const loadedOnce = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const heroImage = computed(() => events.value[0]?.presentation.images[0]?.url ?? '/images/events/city-gathering.svg');

const resultLabel = computed(() => {
    if (total.value !== null) {
        return `${total.value.toLocaleString()} ${total.value === 1 ? 'event' : 'events'}`;
    }

    if (!loadedOnce.value) {
        return 'Finding events';
    }

    return `${events.value.length.toLocaleString()}+ loaded ${events.value.length === 1 ? 'event' : 'events'}`;
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

function priceLabel(event: EventCard) {
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
    <Head title="Events Visual 1" />

    <div class="min-h-full bg-background">
        <section class="relative overflow-hidden border-b">
            <img
                :src="heroImage"
                alt=""
                class="absolute inset-0 h-full w-full scale-105 object-cover opacity-20 blur-sm"
            />
            <div class="absolute inset-0 bg-background/85"></div>

            <div class="relative mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Event Visual 1</p>
                        <h1 class="text-3xl font-semibold tracking-normal">Discover events</h1>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Ticket class="size-4" />
                        <span>{{ resultLabel }}</span>
                    </div>
                </div>

                <form class="grid gap-3 rounded-lg border bg-background/95 p-3 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.4fr_auto_auto]" @submit.prevent="applyFilters">
                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                        Status
                        <select v-model="form.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground">
                            <option value="">All</option>
                            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </label>

                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                        From
                        <input v-model="form.from" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" />
                    </label>

                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                        To
                        <input v-model="form.to" type="date" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" />
                    </label>

                    <label class="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                        Location
                        <input
                            v-model="form.location"
                            list="visual-one-location-suggestions"
                            type="search"
                            placeholder="City, country, or region"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                        />
                        <datalist id="visual-one-location-suggestions">
                            <option v-for="suggestion in locationSuggestions" :key="suggestion" :value="suggestion" />
                        </datalist>
                    </label>

                    <Button type="submit" class="mt-auto gap-2">
                        <Search class="size-4" />
                        Search
                    </Button>
                    <Button type="button" variant="outline" class="mt-auto" @click="clearFilters">Reset</Button>
                </form>
            </div>
        </section>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="event in events"
                    :key="event.id"
                    class="group overflow-hidden rounded-lg border bg-card text-card-foreground shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="relative aspect-[16/9] overflow-hidden bg-muted">
                        <img
                            :src="event.presentation.images[0]?.url"
                            :alt="event.presentation.images[0]?.alt"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        />
                        <div class="absolute left-3 top-3 flex flex-wrap gap-2">
                            <Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge>
                            <Badge variant="secondary">{{ event.type }}</Badge>
                        </div>
                        <div class="absolute bottom-3 right-3 rounded-md bg-background/90 px-2 py-1 text-xs font-medium shadow-sm">
                            {{ priceLabel(event) }}
                        </div>
                    </div>

                    <div class="flex min-h-80 flex-col gap-4 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="line-clamp-2 text-lg font-semibold leading-snug">{{ event.presentation.title }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ event.presentation.venue_name }}</p>
                            </div>
                            <div class="grid shrink-0 grid-cols-2 gap-1">
                                <img
                                    v-for="image in event.presentation.images.slice(0, 2)"
                                    :key="image.url"
                                    :src="image.url"
                                    :alt="image.alt"
                                    class="size-10 rounded-md border object-cover"
                                />
                            </div>
                        </div>

                        <p class="line-clamp-3 text-sm leading-6 text-muted-foreground">{{ event.presentation.description }}</p>

                        <div class="mt-auto grid gap-2 text-sm">
                            <div class="flex items-center gap-2">
                                <CalendarDays class="size-4 text-muted-foreground" />
                                <span>{{ event.presentation.time.range_label ?? 'Time to be announced' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <MapPin class="size-4 text-muted-foreground" />
                                <span>{{ event.presentation.location.label }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <Users class="size-4" />
                                <span>{{ event.user?.name ?? 'Independent organizer' }}</span>
                            </div>
                        </div>

                        <Button as-child variant="outline" class="w-full">
                            <Link :href="`/events/${event.id}`">View details</Link>
                        </Button>
                    </div>
                </article>
            </div>

            <div v-if="loading" class="grid gap-4 pt-4 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="index in 6" :key="index" class="h-96 animate-pulse rounded-lg border bg-muted"></div>
            </div>

            <div v-if="loadedOnce && events.length === 0 && !loading" class="rounded-lg border border-dashed p-10 text-center">
                <p class="text-lg font-medium">No events found</p>
                <p class="mt-1 text-sm text-muted-foreground">Try a different date range, status, or location.</p>
            </div>

            <div ref="sentinel" class="h-8"></div>

            <div v-if="loadedOnce && !hasMore && events.length > 0" class="py-6 text-center text-sm text-muted-foreground">
                End of results
            </div>
        </main>
    </div>
</template>
