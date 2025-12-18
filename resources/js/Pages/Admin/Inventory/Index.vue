<template>
  <AuthenticatedLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Inventory</h1>
        <Link class="btn-primary" :href="route('admin.inventory.create')">New Item</Link>
      </div>

      <div class="card">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="text-left p-3">Name</th>
              <th class="text-left p-3">SKU</th>
              <th class="text-left p-3">Qty</th>
              <th class="text-left p-3">Unit</th>
              <th class="text-right p-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items.data" :key="item.id" class="border-b">
              <td class="p-3"><Link :href="route('admin.inventory.show', item.id)" class="btn-secondary">{{ item.name }}</Link> <span v-if="item.low_stock" class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-600 rounded-full">
            Low Stock
        </span></td>
              <td class="p-3">{{ item.sku }}</td>
              <td class="p-3 font-semibold">{{ item.quantity }}</td>
              <td class="p-3">{{ item.unit ?? '-' }}</td>
              <td class="p-3 text-right space-x-2">
                <Link :href="route('admin.inventory.edit', item.id)" class="btn-secondary">Edit</Link>
                <button class="btn-danger" @click="openUse(item)">Use</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :links="items.links" />

      <!-- Use Item Modal -->
      <Modal :show="!!usingItem" @close="usingItem = null">
            <template #title>
                Use Inventory
            </template>

            <form @submit.prevent="submitUse">
                <div class="space-y-4">
                <TextInput
                    type="number"
                    min="1"
                    v-model="form.quantity"
                    placeholder="Quantity"
                />

                <Textarea
                    v-model="form.reason"
                    placeholder="Reason (optional)"
                />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="btn-secondary"
                    @click="usingItem = null"
                >
                    Cancel
                </button>

                <PrimaryButton :disabled="form.processing">
                    Confirm
                </PrimaryButton>
                </div>
            </form>
        </Modal>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { ref } from 'vue'

const props = defineProps({ items: Object })

console.log('Items Prop:', props.items)

const usingItem = ref(null)

const form = useForm({
  quantity: '',
  reason: ' '
})

function openUse(item) {
  usingItem.value = item
  form.reset()
}

function submitUse() {
  form.post(route('admin.inventory.useItem', usingItem.value.id), {
    onSuccess: () => usingItem.value = null
  })
}
</script>
