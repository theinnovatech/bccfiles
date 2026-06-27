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
                    <p class="text-sm font-medium text-[#5b7fbf]">{{ card.label }}</p>
                    <UiBadge variant="secondary">{{ card.badge }}</UiBadge>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-[#002a7a]">
                    <AnimatedCounter :value="card.value" :active="loaded" />
                </p>
                <p class="mt-1 text-xs text-[#5b7fbf]">{{ card.hint }}</p>
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
                    <div v-else class="flex h-[300px] items-center justify-center text-sm text-[#5b7fbf]">Loading chart...</div>
                </UiCard>
            </div>
            <div class="dashboard-fade-in" style="animation-delay: 680ms">
                <UiCard title="Monthly Issuance" description="Total items issued per month this year.">
                    <apexchart
                        v-if="loaded"
                        type="bar"
                        height="280"
                        class="w-full min-w-0"
                        :options="monthlyOptions"
                        :series="monthlySeries"
                    />
                    <div v-else class="flex h-[300px] items-center justify-center text-sm text-[#5b7fbf]">Loading chart...</div>
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
                            class="flex items-start justify-between gap-3 border-b border-[#eef2fa] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#002a7a]">{{ movement.item?.item_name }}</p>
                                <p class="text-xs text-[#5b7fbf]">{{ movement.transaction_type }} · {{ movement.quantity }} units</p>
                            </div>
                            <UiBadge variant="outline">{{ movement.transaction_type }}</UiBadge>
                        </li>
                        <li v-if="loaded && !recentMovements.length" class="py-8 text-center text-sm text-[#5b7fbf]">No recent movements.</li>
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
                            class="flex items-start justify-between gap-3 border-b border-[#eef2fa] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#002a7a]">{{ issue.issuance_number }}</p>
                                <p class="text-xs text-[#5b7fbf]">{{ issue.request?.department?.name }}</p>
                            </div>
                        </li>
                        <li v-if="loaded && !recentIssuance.length" class="py-8 text-center text-sm text-[#5b7fbf]">No recent issuances.</li>
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
                            class="flex items-start justify-between gap-3 border-b border-[#eef2fa] pb-3 last:border-0 last:pb-0"
                        >
                            <div>
                                <p class="text-sm font-medium text-[#002a7a]">{{ item.item_name }}</p>
                                <p class="text-xs text-[#5b7fbf]">Min: {{ item.minimum_stock }}</p>
                            </div>
                            <UiBadge variant="destructive">{{ item.current_stock }} left</UiBadge>
                        </li>
                        <li v-if="loaded && !lowStockItems.length" class="py-8 text-center text-sm text-[#5b7fbf]">All items are sufficiently stocked.</li>
                    </ul>
                </UiCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import UiCard from '../components/ui/UiCard.vue';
import UiBadge from '../components/ui/UiBadge.vue';
import UiButton from '../components/ui/UiButton.vue';
import AnimatedCounter from '../components/ui/AnimatedCounter.vue';
import api from '../services/api';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

function goTo(url) {
    window.location.href = url;
}

function viewAllStockMovements() {
    if (auth.isDepartmentUser) {
        goTo('/items');
        return;
    }

    goTo('/activity-logs?module=Stock');
}

function viewAllIssuance() {
    if (auth.isDepartmentUser) {
        goTo('/requests');
        return;
    }

    goTo('/issuance');
}

const loaded = ref(false);
const summary = ref({});
const charts = ref({ inventory_status: {}, monthly_issuance: [], low_stock_items: [] });
const recentMovements = ref([]);
const recentIssuance = ref([]);

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
    { label: 'Pending Requests', value: summary.value.pending_requests ?? 0, badge: 'Open', hint: 'Awaiting approval' },
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
    colors: ['#0038A8', '#FCD116', '#CE1126'],
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

const monthlySeries = computed(() => [{
    name: 'Issuance',
    data: (charts.value.monthly_issuance || []).map((row) => row.total),
}]);

const monthlyOptions = computed(() => ({
    chart: {
        toolbar: { show: false },
        fontFamily: 'Inter, sans-serif',
        animations: chartAnimations,
    },
    colors: ['#0038A8'],
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '45%',
        },
    },
    dataLabels: { enabled: false },
    grid: { borderColor: '#c8d6ef', strokeDashArray: 4 },
    xaxis: {
        categories: (charts.value.monthly_issuance || []).map((row) => `M${row.month}`),
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { style: { colors: '#5b7fbf' } } },
}));

const lowStockItems = computed(() => charts.value.low_stock_items || []);

onMounted(async () => {
    const [summaryRes, chartsRes, movementsRes, issuanceRes] = await Promise.all([
        api.get('/dashboard/summary'),
        api.get('/dashboard/charts'),
        api.get('/dashboard/recent-movements'),
        api.get('/dashboard/recent-issuance'),
    ]);

    summary.value = summaryRes.data;
    charts.value = chartsRes.data;
    recentMovements.value = movementsRes.data;
    recentIssuance.value = issuanceRes.data;

    await nextTick();
    loaded.value = true;
});
</script>
