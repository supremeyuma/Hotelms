<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import ManagerLayout from '@/Layouts/Staff/ManagerLayout.vue'
import {
  Building2,
  CheckCircle2,
  CreditCard,
  ImageUp,
  Link2,
  Mail,
  MapPinned,
  Phone,
  RefreshCw,
  Save,
  Sparkles,
  Store,
  MessageCircle,
} from 'lucide-vue-next'

const props = defineProps({
  settings: {
    type: Object,
    required: true,
  },
})

const page = usePage()

const form = useForm({
  site_name: props.settings.site_name || '',
  contact_email: props.settings.contact_email || '',
  contact_phone: props.settings.contact_phone || '',
  hotel_phone: props.settings.hotel_phone || props.settings.contact_phone || '',
  hotel_address: props.settings.hotel_address || '',
  map_embed_url: props.settings.map_embed_url || '',
  site_whatsapp: props.settings.site_whatsapp || '',
  payment_provider_flutterwave_enabled: Boolean(props.settings.payment_provider_flutterwave_enabled),
  payment_provider_paystack_enabled: Boolean(props.settings.payment_provider_paystack_enabled),
  payment_default_provider: props.settings.payment_default_provider || 'flutterwave',
  logo: null,
  banner: null,
})

const localLogoPreview = ref(null)
const localBannerPreview = ref(null)

const logoPreview = computed(() => {
  return localLogoPreview.value || (props.settings.logo ? `/storage/${props.settings.logo}` : null)
})

const bannerPreview = computed(() => {
  return localBannerPreview.value || (props.settings.banner ? `/storage/${props.settings.banner}` : null)
})

const completionStats = computed(() => {
  const fields = [
    form.site_name,
    form.contact_email,
    form.hotel_phone,
    form.hotel_address,
    form.site_whatsapp,
    form.map_embed_url,
  ]

  const completed = fields.filter((value) => String(value || '').trim() !== '').length

  return {
    completed,
    total: fields.length,
    percentage: Math.round((completed / fields.length) * 100),
  }
})

const healthCards = computed(() => [
  {
    label: 'Profile completeness',
    value: `${completionStats.value.percentage}%`,
    hint: `${completionStats.value.completed} of ${completionStats.value.total} operational fields configured`,
    icon: Sparkles,
  },
  {
    label: 'Contact channels',
    value: [form.contact_email, form.hotel_phone, form.site_whatsapp].filter(Boolean).length,
    hint: 'Email, phone, and WhatsApp visibility',
    icon: MessageCircle,
  },
  {
    label: 'Gateways enabled',
    value: [
      form.payment_provider_flutterwave_enabled,
      form.payment_provider_paystack_enabled,
    ].filter(Boolean).length,
    hint: 'Available payment gateways',
    icon: CreditCard,
  },
])

function updateFile(field, event) {
  const [file] = event.target.files || []
  form[field] = file || null

  if (field === 'logo') {
    if (localLogoPreview.value) {
      URL.revokeObjectURL(localLogoPreview.value)
    }

    localLogoPreview.value = file ? URL.createObjectURL(file) : null
  }

  if (field === 'banner') {
    if (localBannerPreview.value) {
      URL.revokeObjectURL(localBannerPreview.value)
    }

    localBannerPreview.value = file ? URL.createObjectURL(file) : null
  }
}

function submit() {
  form.put(route('admin.settings.update'), {
    forceFormData: true,
    preserveScroll: true,
  })
}

onBeforeUnmount(() => {
  if (localLogoPreview.value) {
    URL.revokeObjectURL(localLogoPreview.value)
  }

  if (localBannerPreview.value) {
    URL.revokeObjectURL(localBannerPreview.value)
  }
})
</script>

<template>
  <ManagerLayout>
    <Head title="Manager Settings" />

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-6 md:px-6 lg:px-8">
      <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 text-white shadow-[0_35px_90px_rgba(15,23,42,0.18)]">
        <div class="grid gap-8 p-6 md:grid-cols-[1.4fr,1fr] md:p-8">
          <div class="space-y-5">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.25em] text-emerald-200">
              <Store class="h-4 w-4" />
              Manager Settings
            </div>
            <div class="space-y-3">
              <h1 class="max-w-3xl text-3xl font-black tracking-tight md:text-4xl">
                Manage hotel settings.
              </h1>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
              <div
                v-for="card in healthCards"
                :key="card.label"
                class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"
              >
                <component :is="card.icon" class="mb-3 h-5 w-5 text-emerald-300" />
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-300">{{ card.label }}</p>
                <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-300">{{ card.hint }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-[1.75rem] border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-200">Public preview</p>
                <h2 class="mt-2 text-xl font-black">{{ form.site_name || 'Your hotel name' }}</h2>
              </div>
              <CheckCircle2 v-if="!form.isDirty" class="h-6 w-6 text-emerald-300" />
              <RefreshCw v-else class="h-6 w-6 text-amber-300" />
            </div>

            <div class="mt-5 space-y-4">
              <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/40">
                <div v-if="bannerPreview" class="h-40">
                  <img :src="bannerPreview" alt="Banner preview" class="h-full w-full object-cover" />
                </div>
                <div v-else class="flex h-40 items-center justify-center bg-white/5 text-sm text-slate-300">
                  No banner
                </div>
              </div>

              <div class="flex items-center gap-4 rounded-2xl border border-white/10 bg-black/20 p-4">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl bg-white/10">
                  <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="h-full w-full object-cover" />
                  <Building2 v-else class="h-7 w-7 text-slate-300" />
                </div>
                <div class="min-w-0">
                  <p class="truncate text-lg font-bold">{{ form.site_name || 'Hotel identity' }}</p>
                  <p class="truncate text-sm text-slate-300">{{ form.contact_email || 'contact@email.com' }}</p>
                  <p class="truncate text-sm text-slate-300">{{ form.hotel_phone || 'Primary phone number' }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div
        v-if="page.props.flash?.success || form.recentlySuccessful"
        class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800"
      >
        <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none" />
        <div>
          <p class="font-semibold">Settings saved.</p>
          <p class="text-sm text-emerald-700">
            {{ page.props.flash?.success || 'Changes applied.' }}
          </p>
        </div>
      </div>

      <form @submit.prevent="submit" class="grid gap-8 lg:grid-cols-[1.45fr,0.95fr]">
        <div class="space-y-8">
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
              <div class="rounded-2xl bg-slate-100 p-3 text-slate-700">
                <Building2 class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-900">Brand Identity</h2>
              </div>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
              <div class="space-y-2 md:col-span-2">
                <label for="site_name" class="text-sm font-bold text-slate-700">Hotel name</label>
                <input
                  id="site_name"
                  v-model="form.site_name"
                  type="text"
                  class="field-input"
                  placeholder="Moore Life Beach Resort"
                />
                <p v-if="form.errors.site_name" class="field-error">{{ form.errors.site_name }}</p>
              </div>

              <div class="space-y-3">
                <label class="text-sm font-bold text-slate-700">Logo</label>
                <label class="upload-card">
                  <div>
                    <p class="font-semibold text-slate-800">Upload logo</p>
                  </div>
                  <ImageUp class="h-5 w-5 text-slate-400" />
                  <input type="file" accept="image/*" class="hidden" @change="updateFile('logo', $event)" />
                </label>
                <p v-if="form.errors.logo" class="field-error">{{ form.errors.logo }}</p>
              </div>

              <div class="space-y-3">
                <label class="text-sm font-bold text-slate-700">Banner</label>
                <label class="upload-card">
                  <div>
                    <p class="font-semibold text-slate-800">Upload banner</p>
                  </div>
                  <ImageUp class="h-5 w-5 text-slate-400" />
                  <input type="file" accept="image/*" class="hidden" @change="updateFile('banner', $event)" />
                </label>
                <p v-if="form.errors.banner" class="field-error">{{ form.errors.banner }}</p>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
              <div class="rounded-2xl bg-emerald-50 p-3 text-emerald-700">
                <Phone class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-900">Guest Contact And Location</h2>
              </div>
            </div>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
              <div class="space-y-2">
                <label for="contact_email" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                  <Mail class="h-4 w-4" />
                  Contact email
                </label>
                <input
                  id="contact_email"
                  v-model="form.contact_email"
                  type="email"
                  class="field-input"
                  placeholder="info@hotel.com"
                />
                <p v-if="form.errors.contact_email" class="field-error">{{ form.errors.contact_email }}</p>
              </div>

              <div class="space-y-2">
                <label for="hotel_phone" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                  <Phone class="h-4 w-4" />
                  Primary phone
                </label>
                <input
                  id="hotel_phone"
                  v-model="form.hotel_phone"
                  type="text"
                  class="field-input"
                  placeholder="+234 800 000 0000"
                />
                <p v-if="form.errors.hotel_phone" class="field-error">{{ form.errors.hotel_phone }}</p>
              </div>

              <div class="space-y-2">
                <label for="contact_phone" class="text-sm font-bold text-slate-700">Legacy contact phone</label>
                <input
                  id="contact_phone"
                  v-model="form.contact_phone"
                  type="text"
                  class="field-input"
                  placeholder="Optional fallback phone"
                />
                <p v-if="form.errors.contact_phone" class="field-error">{{ form.errors.contact_phone }}</p>
              </div>

              <div class="space-y-2">
                <label for="site_whatsapp" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                  <MessageCircle class="h-4 w-4" />
                  WhatsApp line
                </label>
                <input
                  id="site_whatsapp"
                  v-model="form.site_whatsapp"
                  type="text"
                  class="field-input"
                  placeholder="+234 800 000 0000"
                />
                <p v-if="form.errors.site_whatsapp" class="field-error">{{ form.errors.site_whatsapp }}</p>
              </div>

              <div class="space-y-2 md:col-span-2">
                <label for="hotel_address" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                  <MapPinned class="h-4 w-4" />
                  Hotel address
                </label>
                <textarea
                  id="hotel_address"
                  v-model="form.hotel_address"
                  rows="3"
                  class="field-input min-h-[110px]"
                  placeholder="1 Marina Road, Victoria Island, Lagos"
                />
                <p v-if="form.errors.hotel_address" class="field-error">{{ form.errors.hotel_address }}</p>
              </div>

              <div class="space-y-2 md:col-span-2">
                <label for="map_embed_url" class="flex items-center gap-2 text-sm font-bold text-slate-700">
                  <Link2 class="h-4 w-4" />
                  Google Maps embed URL
                </label>
                <input
                  id="map_embed_url"
                  v-model="form.map_embed_url"
                  type="url"
                  class="field-input"
                  placeholder="https://www.google.com/maps/embed?pb=..."
                />
                <p v-if="form.errors.map_embed_url" class="field-error">{{ form.errors.map_embed_url }}</p>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
              <div class="rounded-2xl bg-indigo-50 p-3 text-indigo-700">
                <CreditCard class="h-5 w-5" />
              </div>
              <div>
                <h2 class="text-xl font-black text-slate-900">Payment Gateways</h2>
              </div>
            </div>

            <div class="mt-6 space-y-5">
              <div class="grid gap-4 md:grid-cols-2">
                <label class="gateway-card">
                  <div>
                    <p class="text-sm font-bold text-slate-900">Flutterwave</p>
                    <p class="text-sm text-slate-500">Enable Flutterwave checkout</p>
                  </div>
                  <input v-model="form.payment_provider_flutterwave_enabled" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                </label>

                <label class="gateway-card">
                  <div>
                    <p class="text-sm font-bold text-slate-900">Paystack</p>
                    <p class="text-sm text-slate-500">Enable Paystack checkout</p>
                  </div>
                  <input v-model="form.payment_provider_paystack_enabled" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                </label>
              </div>

              <div>
                <label for="payment_default_provider" class="text-sm font-bold text-slate-700">Default gateway</label>
                <select
                  id="payment_default_provider"
                  v-model="form.payment_default_provider"
                  class="field-input mt-2"
                >
                  <option value="flutterwave">Flutterwave</option>
                  <option value="paystack">Paystack</option>
                </select>
              </div>

              <p v-if="form.errors.payment_providers" class="field-error">{{ form.errors.payment_providers }}</p>
              <p v-if="form.errors.payment_default_provider" class="field-error">{{ form.errors.payment_default_provider }}</p>
            </div>
          </section>
        </div>

        <aside class="space-y-8">
          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Readiness</p>
            <h2 class="mt-2 text-xl font-black text-slate-900">Guest-facing details</h2>

            <div class="mt-6 space-y-3">
              <div class="readiness-item">
                <Mail class="h-4 w-4" />
                <div>
                  <p class="font-semibold text-slate-800">Email</p>
                  <p class="text-sm text-slate-500">{{ form.contact_email || 'Not configured yet' }}</p>
                </div>
              </div>
              <div class="readiness-item">
                <Phone class="h-4 w-4" />
                <div>
                  <p class="font-semibold text-slate-800">Phone</p>
                  <p class="text-sm text-slate-500">{{ form.hotel_phone || form.contact_phone || 'Not configured yet' }}</p>
                </div>
              </div>
              <div class="readiness-item">
                <MessageCircle class="h-4 w-4" />
                <div>
                  <p class="font-semibold text-slate-800">WhatsApp</p>
                  <p class="text-sm text-slate-500">{{ form.site_whatsapp || 'Not configured yet' }}</p>
                </div>
              </div>
              <div class="readiness-item">
                <MapPinned class="h-4 w-4" />
                <div>
                  <p class="font-semibold text-slate-800">Address</p>
                  <p class="text-sm text-slate-500">{{ form.hotel_address || 'Not configured yet' }}</p>
                </div>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-400">Quick checks</p>
            <div class="mt-5 space-y-4">
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Brand assets</p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ logoPreview ? 'Logo ready' : 'Logo missing' }} and
                  {{ bannerPreview ? 'banner ready' : 'banner missing' }}.
                </p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Maps link</p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ form.map_embed_url ? 'Saved' : 'Missing' }}
                </p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Submit state</p>
                <p class="mt-1 text-sm text-slate-500">
                  Locked while saving.
                </p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Payment gateways</p>
                <p class="mt-1 text-sm text-slate-500">
                  {{
                    [
                      form.payment_provider_flutterwave_enabled ? 'Flutterwave' : null,
                      form.payment_provider_paystack_enabled ? 'Paystack' : null,
                    ].filter(Boolean).join(', ') || 'None'
                  }}
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-[2rem] border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-emerald-300">Publish</p>
            <h2 class="mt-2 text-xl font-black">Save manager settings</h2>

            <button
              type="submit"
              :disabled="form.processing"
              class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-5 py-4 text-sm font-black text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <RefreshCw v-if="form.processing" class="h-5 w-5 animate-spin" />
              <Save v-else class="h-5 w-5" />
              {{ form.processing ? 'Saving settings...' : 'Save settings' }}
            </button>
          </section>
        </aside>
      </form>
    </div>
  </ManagerLayout>
</template>

<style scoped>
.field-input {
  @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100;
}

.field-error {
  @apply text-sm font-medium text-rose-600;
}

.upload-card {
  @apply flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50;
}

.gateway-card {
  @apply flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4;
}

.readiness-item {
  @apply flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-slate-700;
}
</style>
