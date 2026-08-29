<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import BaseStaffLayout from '@/Layouts/Staff/BaseStaffLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import { ShieldCheck, UserRound } from 'lucide-vue-next'

const props = defineProps({
  user: Object,
})

const roles = computed(() => (props.user?.roles ?? []).map((role) => role.name).join(', ') || 'Staff')
const department = computed(() => props.user?.staffProfile?.position || props.user?.department?.name || 'General')

const profileForm = useForm({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  password: '',
})

const submitProfile = () => {
  profileForm.patch(route('staff.profile.update'), {
    preserveScroll: true,
  })
}

const actionCodeForm = useForm({
  action_code: '',
})

const submitActionCode = () => {
  actionCodeForm.post(route('staff.profile.action_code'), {
    preserveScroll: true,
  })
}

const changePassword = ref(false)
</script>

<template>
  <BaseStaffLayout>
    <div class="mx-auto max-w-4xl">
      <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-900 md:text-3xl">My Profile</h1>
        <p class="mt-1 text-sm font-medium text-slate-500">Manage your account details and staff security code.</p>
      </div>

      <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-6 flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
              <UserRound class="h-7 w-7" />
            </div>
            <div>
              <h2 class="text-xl font-black text-slate-900">{{ user?.name }}</h2>
              <p class="text-sm font-medium text-slate-500">{{ user?.email }}</p>
              <div class="mt-2 flex flex-wrap gap-2">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-700">{{ roles }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-700">{{ department }}</span>
              </div>
            </div>
          </div>

          <form @submit.prevent="submitProfile" class="max-w-md space-y-4">
            <div class="space-y-2">
              <InputLabel for="profile-name" value="Name" />
              <TextInput id="profile-name" type="text" class="mt-1 block w-full" v-model="profileForm.name" />
              <InputError :message="profileForm.errors.name" class="mt-2" />
            </div>

            <div class="space-y-2">
              <InputLabel for="profile-email" value="Email" />
              <TextInput id="profile-email" type="email" class="mt-1 block w-full" v-model="profileForm.email" />
              <InputError :message="profileForm.errors.email" class="mt-2" />
            </div>

            <div class="space-y-2">
              <button
                type="button"
                class="text-sm font-bold text-indigo-600 hover:text-indigo-700"
                @click="changePassword = !changePassword"
              >
                {{ changePassword ? 'Hide password change' : 'Change password' }}
              </button>

              <template v-if="changePassword">
                <TextInput id="profile-password" type="password" class="mt-1 block w-full" v-model="profileForm.password" autocomplete="new-password" />
                <InputError :message="profileForm.errors.password" class="mt-2" />
              </template>
            </div>

            <button
              type="submit"
              class="inline-flex items-center rounded-xl bg-slate-900 px-6 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="profileForm.processing"
            >
              {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
            </button>
          </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-6 flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
              <ShieldCheck class="h-7 w-7" />
            </div>
            <div>
              <h2 class="text-xl font-black text-slate-900">Staff Action Code</h2>
              <p class="mt-1 text-sm font-medium text-slate-500">
                {{ user?.staff_profile?.action_code_configured ? 'A security code is configured for your account.' : 'Set a security code to complete restricted staff actions.' }}
              </p>
            </div>
          </div>

          <form @submit.prevent="submitActionCode" class="max-w-md space-y-4">
            <div class="space-y-2">
              <InputLabel for="action-code" value="Action code" />
              <TextInput id="action-code" type="password" class="mt-1 block w-full" v-model="actionCodeForm.action_code" autocomplete="new-password" />
              <p class="text-xs text-slate-500">At least 6 characters. Used to authorize sensitive staff actions.</p>
              <InputError :message="actionCodeForm.errors.action_code" class="mt-2" />
            </div>

            <button
              type="submit"
              class="inline-flex items-center rounded-xl bg-slate-900 px-6 py-3 text-xs font-black uppercase tracking-widest text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="actionCodeForm.processing"
            >
              {{ actionCodeForm.processing ? 'Saving...' : 'Update Action Code' }}
            </button>
          </form>
        </section>
      </div>
    </div>
  </BaseStaffLayout>
</template>
