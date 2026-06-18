<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface EventRow {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    user: { id: number; name: string } | null;
    presentation: {
        title: string;
        location: { label: string };
        time: { range_label: string | null };
    };
}

const props = defineProps<{
    filters: { status: string | null; from: string; to: string | null; location: string | null };
    locationSuggestions: string[];
    statuses: string[];
}>();

const form = reactive({
    status: props.filters.status ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    location: props.filters.location ?? '',
});

const rows = ref<EventRow[]>([]);
const page = ref(0);
const hasMore = ref(true);
const total = ref<number | null>(null);
const loadedBytes = ref(0);
const loadedMs = ref(0);
const loading = ref(false);
const hasLoadedOnce = ref(false);

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const loadedSize = computed(() => {
    const kb = loadedBytes.value / 1024;
    return kb < 1024 ? `${kb.toFixed(1)} KB` : `${(kb / 1024).toFixed(2)} MB`;
});

const loadedSeconds = computed(() => (loadedMs.value / 1000).toFixed(1));

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

        rows.value.push(...payload.data);
        page.value = payload.current_page;
        hasMore.value = payload.has_more;
        total.value = payload.total;
        loadedBytes.value += payload.stats.bytes;
        loadedMs.value += payload.stats.ms;
        hasLoadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    rows.value = [];
    page.value = 0;
    hasMore.value = true;
    total.value = null;
    loadedBytes.value = 0;
    loadedMs.value = 0;
    hasLoadedOnce.value = false;
    loadMore();
}

const statusVariant = (status: string) => {
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
};

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '400px' },
    );
    if (sentinel.value) {
        observer.observe(sentinel.value);
    }
    loadMore();
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold">Events</h1>
            <p class="text-sm text-muted-foreground">
                {{ total !== null ? `${total.toLocaleString()} total events` : '—' }}
            </p>
        </div>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="status">Status</label>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="from">From</label>
                <input
                    id="from"
                    v-model="form.from"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="to">To</label>
                <input
                    id="to"
                    v-model="form.to"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
            </div>
            <div class="flex min-w-56 flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="location">Location</label>
                <input
                    id="location"
                    v-model="form.location"
                    list="event-location-suggestions"
                    type="search"
                    placeholder="City, country, or region"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                />
                <datalist id="event-location-suggestions">
                    <option v-for="suggestion in locationSuggestions" :key="suggestion" :value="suggestion" />
                </datalist>
            </div>
            <Button type="button" @click.prevent="applyFilters">Filter</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="border-b bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">Title</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Location</th>
                        <th class="px-3 py-2 font-medium">User</th>
                        <th class="px-3 py-2 font-medium">Time</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="event in rows" :key="event.id" class="border-b last:border-0">
                        <td class="px-3 py-2 font-mono text-xs">{{ event.id }}</td>
                        <td class="px-3 py-2 font-medium">{{ event.presentation.title }}</td>
                        <td class="px-3 py-2">{{ event.type }}</td>
                        <td class="px-3 py-2">
                            <Badge :variant="statusVariant(event.status)">{{ event.status }}</Badge>
                        </td>
                        <td class="px-3 py-2">{{ event.presentation.location.label }}</td>
                        <td class="px-3 py-2">{{ event.user?.name ?? '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ event.presentation.time.range_label ?? event.created_time }}</td>
                        <td class="px-3 py-2 text-right">
                            <Link :href="`/events/${event.id}`" class="text-primary hover:underline">View</Link>
                        </td>
                    </tr>
                    <tr v-if="!loading && hasLoadedOnce && rows.length === 0">
                        <td colspan="8" class="px-3 py-8 text-center text-muted-foreground">No events found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div ref="sentinel"></div>

        <div class="py-2 text-sm text-gray-400">
            <span v-if="loading">loading...</span>
            <span v-else-if="hasLoadedOnce">Loaded {{ loadedSize }} in {{ loadedSeconds }}s</span>
        </div>
    </div>
</template>
