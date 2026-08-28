<template>
    <div>
        <div class="shadcn-page-header dashboard-fade-in" style="animation-delay: 0ms">
            <h1 class="shadcn-page-title">Dashboard</h1>
            <p class="shadcn-page-description">Overview of supply unit inventory and activity.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="(card, index) in statCards"
                :key="card.label"
                class="shadcn-stat-card dashboard-fade-in"
                :style="{ animationDelay: `${80 + index * 70}ms` }"
            >
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-[#4a6490]">{{ card.label }}</p>
                    <UiBadge variant="secondary">{{ card.badge }}</UiBadge>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-[#00164d]">
                    <AnimatedCounter :value="card.value" :active="loaded" />
                </p>
                <p class="mt-1 text-xs text-[#4a6490]">{{ card.hint }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-2">
            <div class="dashboard-fade-in" style="animation-delay: 580ms">
                <UiCard title="Inventory Status" description="Distribution of stock levels across all items.">
                    <apexchart
                        v-if="loaded"
                        type="donut"
                        height="280"
                        class="w-full min-w-0"
                        :options="inventoryOptions"
                        :series="inventorySeries"
                    />
                    <div v-else class="flex h-[300px] items-center justify-center text-sm text-[#4a6490]">Loading chart...</div>
                </UiCard>
            </div>
            <div class="dashboard-fade-in" style="animation-delay: 680ms">
                <UiCard>
                    <template #header>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="shadcn-card-title">Monthly Issuance</h3>
                                <p class="shadcn-card-description">Total items issued per month.</p>
                                <p class="mt-2 text-sm font-semibold text-[#00164d]">
                                    Currently showing: {{ monthlyPeriodLabel }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-end gap-2">
                                <label class="space-y-1">
                                    <span class="block text-xs font-medium text-[#4a6490]">Month</span>
                                    <select v-model="monthlyMonth" class="shadcn-input min-w-[9.5rem]">
                                        <option value="">All months</option>
                                        <option
                                            v-for="month in monthOptions"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </option>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="block text-xs font-medium text-[#4a6490]">Year</span>
                                    <select v-model.number="monthlyYear" class="shadcn-input min-w-[6.5rem]">
                                        <option
                                            v-for="year in monthlyYears"
                                            :key="year"
                                            :value="year"
                                        >
                                            {{ year }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </template>
                    <apexchart
                        v-if="loaded"
                        :key="monthlyChartKey"
                        type="bar"
                        height="280"
                        class="w-full min-w-0"
                        :options="monthlyOptions"
                        :series="monthlySeries"
                    />
                    <div v-else class="flex h-[300px] items-center justify-center text-sm text-[#4a6490]">Loading chart...</div>
                </UiCard>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <div class="dashboard-fade-in" style="animation-delay: 780ms">
                <UiCard>
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="shadcn-card-title">Recent Stock Movements</h3>
                                <p class="shadcn-card-description">Latest inventory transactions.</p>
                            </div>
                            <UiButton variant="outline" size="sm" @click="viewAllStockMovements">
                                View All
                            </UiButton>
                        </div>
                    </template>
                    <ul class="space-y-3">
                        <li
                            v-for="movement in recentMovements"
                            :key="movement.id"
                            class="flex items-start justify-between gap-3 border-b border-[#e2e8f2] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#00164d]">{{ movement.item?.item_name }}</p>
                                <p class="text-xs text-[#4a6490]">{{ movement.transaction_type }} · {{ movement.quantity }} units</p>
                            </div>
                            <UiBadge variant="outline">{{ movement.transaction_type }}</UiBadge>
                        </li>
                        <li v-if="loaded && !recentMovements.length" class="py-8 text-center text-sm text-[#4a6490]">No recent movements.</li>
                    </ul>
                </UiCard>
            </div>

            <div class="dashboard-fade-in" style="animation-delay: 880ms">
                <UiCard>
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="shadcn-card-title">Recent Issuance</h3>
                                <p class="shadcn-card-description">Latest supply issuances to departments.</p>
                            </div>
                            <UiButton variant="outline" size="sm" @click="viewAllIssuance">
                                View All
                            </UiButton>
                        </div>
                    </template>
                    <ul class="space-y-3">
                        <li
                            v-for="issue in recentIssuance"
                            :key="issue.id"
                            class="flex items-start justify-between gap-3 border-b border-[#e2e8f2] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#00164d]">{{ issue.issuance_number }}</p>
                                <p class="text-xs text-[#4a6490]">{{ issue.department?.name || issue.department_name || '—' }}</p>
                            </div>
                        </li>
                        <li v-if="loaded && !recentIssuance.length" class="py-8 text-center text-sm text-[#4a6490]">No recent issuances.</li>
                    </ul>
                </UiCard>
            </div>

            <div class="dashboard-fade-in" style="animation-delay: 980ms">
                <UiCard>
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="shadcn-card-title">Low Stock Alert</h3>
                                <p class="shadcn-card-description">Items at or below minimum stock level.</p>
                            </div>
                            <UiButton variant="outline" size="sm" @click="goTo('/items?stockStatus=low_stock')">
                                View All
                            </UiButton>
                        </div>
                    </template>
                    <ul class="space-y-3">
                        <li
                            v-for="item in lowStockItems"
                            :key="item.id"
                            class="flex items-start justify-between gap-3 border-b border-[#e2e8f2] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#00164d]">{{ item.item_name }}</p>
                                <p class="text-xs text-[#4a6490]">Min: {{ item.minimum_stock }}</p>
                            </div>
                            <UiBadge variant="destructive">{{ item.current_stock }} left</UiBadge>
                        </li>
                        <li v-if="loaded && !lowStockItems.length" class="py-8 text-center text-sm text-[#4a6490]">All items are sufficiently stocked.</li>
                    </ul>
                </UiCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import UiCard from '../components/ui/UiCard.vue';
import UiBadge from '../components/ui/UiBadge.vue';
import UiButton from '../components/ui/UiButton.vue';
import AnimatedCounter from '../components/ui/AnimatedCounter.vue';
import api from '../services/api';
function goTo(url) {
    window.location.href = url;
}

function viewAllStockMovements() {
    goTo('/activity-logs?module=Stock');
}

function viewAllIssuance() {
    goTo('/issuance');
}

const loaded = ref(false);
const summary = ref({});
const charts = ref({
    inventory_status: {},
    monthly_issuance: [],
    monthly_issuance_years: [],
    monthly_issuance_period: '',
    low_stock_items: [],
});
const recentMovements = ref([]);
const recentIssuance = ref([]);
const monthlyYear = ref(new Date().getFullYear());
const monthlyMonth = ref('');

const monthOptions = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const chartAnimations = {
    enabled: true,
    easing: 'easeinout',
    speed: 900,
    animateGradually: {
        enabled: true,
        delay: 120,
    },
    dynamicAnimation: {
        enabled: true,
        speed: 400,
    },
};

const statCards = computed(() => [
    { label: 'Total Items', value: summary.value.total_items ?? 0, badge: 'Items', hint: 'Registered in inventory' },
    { label: 'Available Stocks', value: summary.value.available_stocks ?? 0, badge: 'Units', hint: 'Total units on hand' },
    { label: 'Low Stock', value: summary.value.low_stock_items ?? 0, badge: 'Alert', hint: 'At or below minimum' },
    { label: 'Out of Stock', value: summary.value.out_of_stock ?? 0, badge: 'Critical', hint: 'Zero quantity items' },
    { label: "Today's Issuance", value: summary.value.todays_issuance ?? 0, badge: 'Out', hint: 'Issued today' },
    { label: "Today's Received", value: summary.value.todays_received ?? 0, badge: 'In', hint: 'Received today' },
    { label: "Today's Issuance Records", value: summary.value.todays_issuance_records ?? 0, badge: 'Records', hint: 'Issuances recorded today' },
    { label: 'Registered Equipment', value: summary.value.registered_equipments ?? 0, badge: 'Assets', hint: 'Equipment on record' },
]);

const inventorySeries = computed(() => {
    const s = charts.value.inventory_status || {};
    return [s.in_stock || 0, s.low_stock || 0, s.out_of_stock || 0];
});

const inventoryOptions = computed(() => ({
    chart: {
        animations: chartAnimations,
        fontFamily: 'Inter, sans-serif',
    },
    labels: ['In Stock', 'Low Stock', 'Out of Stock'],
    colors: ['#001F6B', '#FCD116', '#CE1126'],
    legend: { position: 'bottom', fontFamily: 'Inter, sans-serif' },
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    plotOptions: {
        pie: {
            donut: { size: '65%' },
            expandOnClick: false,
        },
    },
}));

const monthlyRows = computed(() => charts.value.monthly_issuance || []);

const monthlyYears = computed(() => {
    const years = [...(charts.value.monthly_issuance_years || [])]
        .map((year) => Number(year))
        .filter((year) => Number.isFinite(year));

    if (!years.includes(Number(monthlyYear.value))) {
        years.push(Number(monthlyYear.value));
    }

    return [...new Set(years)].sort((a, b) => b - a);
});

const monthlyPeriodLabel = computed(() => {
    if (charts.value.monthly_issuance_period) {
        return charts.value.monthly_issuance_period;
    }

    const year = monthlyYear.value;
    if (monthlyMonth.value === '' || monthlyMonth.value == null) {
        return `January – December ${year}`;
    }

    const month = monthOptions.find((row) => row.value === Number(monthlyMonth.value));
    return month ? `${month.label} ${year}` : String(year);
});

const monthlyChartKey = computed(
    () => `${monthlyYear.value}-${monthlyMonth.value || 'all'}-${monthlyRows.value.length}`,
);

const monthlySeries = computed(() => [{
    name: 'Issuance',
    data: monthlyRows.value.map((row) => row.total),
}]);

const monthlyOptions = computed(() => ({
    chart: {
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
        animations: chartAnimations,
    },
    colors: ['#001F6B'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: monthlyRows.value.length === 1 ? '28%' : '45%',
        },
    },
    dataLabels: { enabled: false },
    grid: { borderColor: '#a8b8d4', strokeDashArray: 4 },
    xaxis: {
        categories: monthlyRows.value.map((row) => row.month_name || monthOptions[(row.month || 1) - 1]?.label),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
            rotate: monthlyRows.value.length > 6 ? -45 : 0,
            hideOverlappingLabels: false,
            trim: false,
            style: { colors: '#4a6490', fontSize: '12px' },
        },
    },
    yaxis: { labels: { style: { colors: '#4a6490' } } },
}));

const lowStockItems = computed(() => charts.value.low_stock_items || []);

async function loadCharts() {
    const params = { year: monthlyYear.value };
    if (monthlyMonth.value !== '' && monthlyMonth.value != null) {
        params.month = monthlyMonth.value;
    }

    const { data } = await api.get('/dashboard/charts', { params });
    charts.value = data;

    if (data.monthly_issuance_year) {
        monthlyYear.value = data.monthly_issuance_year;
    }
}

watch([monthlyYear, monthlyMonth], async () => {
    if (!loaded.value) {
        return;
    }

    try {
        await loadCharts();
    } catch {
        // Keep the last successful chart if a filter refresh fails.
    }
});

onMounted(async () => {
    const [summaryRes, movementsRes, issuanceRes] = await Promise.all([
        api.get('/dashboard/summary'),
        api.get('/dashboard/recent-movements'),
        api.get('/dashboard/recent-issuance'),
        loadCharts(),
    ]);

    summary.value = summaryRes.data;
    recentMovements.value = movementsRes.data;
    recentIssuance.value = issuanceRes.data;

    await nextTick();
    loaded.value = true;
});
</script>
