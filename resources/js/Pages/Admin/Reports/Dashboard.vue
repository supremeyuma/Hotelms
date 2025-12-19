<!-- resources/js/Pages/Admin/Reports/Dashboard.vue -->
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import KpiCard from '@/Components/KpiCard.vue'
import TrendChart from '@/Components/TrendChart.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  kpis: Object,
})
</script>

<template>
  <AuthenticatedLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold text-gray-800">
        Reports Dashboard
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <Link href="/admin/reports/revenue" class="block transition hover:opacity-80">
            <KpiCard
          label="Total Revenue"
          :value="'₦' + kpis.revenue.revenue"
          hint="Confirmed bookings"
        />
        </Link>

        <Link href="/admin/reports/occupancy" class="block transition hover:opacity-80">
        <KpiCard
          label="Occupancy Rate"
          :value="kpis.occupancy.occupancy + '%'"
          hint="Today"
        />
        </Link>

        <Link href="/admin/reports/staff" class="block transition hover:opacity-80">
        <KpiCard
          label="Active Staff"
          :value="kpis.staff.active_staff"
          hint="All departments"
        />
        </Link>

        <Link href="/admin/reports/inventory" class="block transition hover:opacity-80">
        <KpiCard
          label="Inventory Usage"
          :value="kpis.inventory.usage"
          hint="All time"
        />
        </Link>
    </div>
    

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <TrendChart
          title="Revenue Trend"
          endpoint="/admin/reports/charts/revenue"
        />

        <TrendChart
          title="Occupancy Trend"
          endpoint="/admin/reports/charts/occupancy"
        />
      </div>
    </div>
  </AuthenticatedLayout>
</template>
