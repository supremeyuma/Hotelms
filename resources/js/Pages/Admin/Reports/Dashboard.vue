<script setup>
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import KpiCard from '@/Components/KpiCard.vue'
import TrendChart from '@/Components/TrendChart.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  mode: String,
  title: String,
  kpis: Object,
  links: Object,
  charts: Array,
})
</script>

<template>
  <ManagerLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold text-gray-800">
        {{ title }}
      </h1>

      <div v-if="mode === 'operations'" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Link :href="links.primary" class="block transition hover:opacity-80">
          <KpiCard
            label="Occupancy Rate"
            :value="kpis.occupancy.occupancy + '%'"
            hint="Today"
          />
        </Link>

        <Link :href="links.secondary" class="block transition hover:opacity-80">
          <KpiCard
            label="Active Staff"
            :value="kpis.staff.active_staff"
            hint="All departments"
          />
        </Link>

        <Link :href="links.tertiary" class="block transition hover:opacity-80">
          <KpiCard
            label="Inventory Usage"
            :value="kpis.inventory.usage"
            hint="Tracked movement"
          />
        </Link>
      </div>

      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Link :href="links.primary" class="block transition hover:opacity-80">
          <KpiCard
            label="Recognized Revenue"
            :value="'NGN ' + kpis.revenue.revenue"
            hint="Confirmed bookings"
          />
        </Link>

        <Link :href="links.secondary" class="block transition hover:opacity-80">
          <KpiCard
            label="Outstanding Balances"
            :value="kpis.outstanding.count"
            :hint="'NGN ' + kpis.outstanding.total + ' unsettled'"
          />
        </Link>

        <Link :href="links.tertiary" class="block transition hover:opacity-80">
          <KpiCard
            label="Open Periods"
            :value="kpis.periods.open"
            hint="Accounting periods"
          />
        </Link>

        <Link :href="links.quaternary" class="block transition hover:opacity-80">
          <KpiCard
            label="Daily Revenue"
            :value="'NGN ' + kpis.daily_revenue.total"
            hint="Posted today"
          />
        </Link>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <TrendChart
          v-for="chart in charts"
          :key="chart.title"
          :title="chart.title"
          :endpoint="chart.endpoint"
        />
      </div>
    </div>
  </ManagerLayout>
</template>
