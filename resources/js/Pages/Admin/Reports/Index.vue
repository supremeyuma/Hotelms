<template>
<AuthenticatedLayout>
<div class="space-y-6">
<h1 class="text-2xl font-bold">Reports Dashboard</h1>


<div class="grid grid-cols-4 gap-4">
<Stat label="Occupancy" :value="summary.occupancy + '%'" />
<Stat label="Revenue" :value="'₦' + summary.revenue" />
<Stat label="ADR" :value="'₦' + summary.adr" />
<Stat label="RevPAR" :value="'₦' + summary.revpar" />
</div>


<Chart title="Occupancy Trend" endpoint="/admin/reports/occupancy" />
<Chart title="Revenue Trend" endpoint="/admin/reports/revenue" />
</div>
</AuthenticatedLayout>
</template>


<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Chart from '@/Components/Chart.vue'
import Stat from '@/Components/Stat.vue'


defineProps({ summary:Object })
</script>