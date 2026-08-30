<script setup>
import { computed, onMounted } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import AOS from 'aos'
import { Mail, Phone, MapPin, Send, MessageSquare } from 'lucide-vue-next'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'

onMounted(() => {
  AOS.init({ duration: 1000, once: true })
})

const page = usePage()

const form = useForm({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: '',
})

const flash = computed(() => page.props.flash?.success ?? null)

const phone = computed(() => page.props.settings?.hotel_phone ?? null)
const email = computed(() => page.props.settings?.contact_email ?? null)
const whatsapp = computed(() => page.props.settings?.site_whatsapp ?? null)

const submitForm = () => {
  form.post(route('public.contact.submit'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}
</script>

<template>
  <PublicLayout>
    <Head title="Contact | MooreLife Resort" />

    <section class="pt-32 pb-20 bg-slate-50 border-b border-slate-100">
      <div class="container mx-auto px-6">
        <div class="max-w-4xl" data-aos="fade-up">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 mb-6">
            <Mail class="w-3.5 h-3.5 text-indigo-600" />
            <span class="text-xs uppercase font-semibold tracking-wide text-indigo-600">Get in Touch</span>
          </div>
          <h1 class="text-5xl md:text-7xl font-black text-slate-950 tracking-tighter mb-6">
            Contact Us.
          </h1>
          <p class="text-xl text-slate-500 font-light max-w-2xl leading-relaxed">
            Questions, reservations, or special requests — our concierge team is here to help.
          </p>
        </div>
      </div>
    </section>

    <section class="py-24 bg-white">
      <div class="container mx-auto px-6">
        <div class="grid lg:grid-cols-12 gap-20">

          <div class="lg:col-span-7" data-aos="fade-right">
            <div class="p-10 rounded-2xl bg-white border border-slate-200 shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
              <div v-if="flash" class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-800 text-sm">
                {{ flash }}
              </div>

              <form @submit.prevent="submitForm" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput
                      id="name"
                      v-model="form.name"
                      type="text"
                      class="mt-1 block w-full"
                      required
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                  </div>
                  <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                      id="email"
                      v-model="form.email"
                      type="email"
                      class="mt-1 block w-full"
                      required
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <InputLabel for="phone" value="Phone (optional)" />
                    <TextInput
                      id="phone"
                      v-model="form.phone"
                      type="tel"
                      class="mt-1 block w-full"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                  </div>
                  <div>
                    <InputLabel for="subject" value="Subject" />
                    <TextInput
                      id="subject"
                      v-model="form.subject"
                      type="text"
                      class="mt-1 block w-full"
                      required
                    />
                    <InputError class="mt-2" :message="form.errors.subject" />
                  </div>
                </div>

                <div>
                  <InputLabel for="message" value="Message" />
                  <Textarea
                    id="message"
                    v-model="form.message"
                    placeholder="How can we help?"
                  />
                  <InputError class="mt-2" :message="form.errors.message" />
                </div>

                <div>
                  <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-8 py-4 rounded-full text-xs uppercase font-semibold tracking-wide bg-indigo-600 text-white transition-all disabled:opacity-60 disabled:cursor-not-allowed hover:bg-indigo-700"
                  >
                    <Send class="w-4 h-4" />
                    <span v-if="form.processing">Sending...</span>
                    <span v-else>Send Message</span>
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div class="lg:col-span-5" data-aos="fade-left">
            <div class="sticky top-32 space-y-8">
              <div class="p-10 rounded-2xl bg-slate-50 border border-slate-100 space-y-8">
                <div v-if="phone" class="flex items-start gap-4">
                  <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0">
                    <Phone class="w-5 h-5 text-indigo-600" />
                  </div>
                  <div>
                    <h4 class="text-sm font-black text-slate-950">Phone</h4>
                    <a :href="`tel:${phone}`" class="text-sm text-slate-500 font-light hover:text-indigo-600 transition-colors">{{ phone }}</a>
                  </div>
                </div>

                <div v-if="email" class="flex items-start gap-4">
                  <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0">
                    <Mail class="w-5 h-5 text-indigo-600" />
                  </div>
                  <div>
                    <h4 class="text-sm font-black text-slate-950">Email</h4>
                    <a :href="`mailto:${email}`" class="text-sm text-slate-500 font-light hover:text-indigo-600 transition-colors break-all">{{ email }}</a>
                  </div>
                </div>

                <div class="flex items-start gap-4">
                  <div class="w-11 h-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0">
                    <MapPin class="w-5 h-5 text-indigo-600" />
                  </div>
                  <div>
                    <h4 class="text-sm font-black text-slate-950">Location</h4>
                    <p class="text-sm text-slate-500 font-light">{{ content('footer.location') }}</p>
                  </div>
                </div>
              </div>

              <div v-if="whatsapp" class="p-10 rounded-2xl bg-indigo-600 text-white space-y-4">
                <MessageSquare class="w-8 h-8 text-indigo-300" />
                <h4 class="text-lg font-black">Chat with Concierge</h4>
                <p class="text-indigo-100 text-sm font-light leading-relaxed opacity-80">
                  Prefer messaging? Reach our concierge directly on WhatsApp for instant assistance.
                </p>
                <a
                  :href="`https://wa.me/${whatsapp.replace(/[^0-9]/g, '')}`"
                  target="_blank"
                  class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-white text-indigo-700 text-xs uppercase font-semibold tracking-wide hover:bg-indigo-50 transition-colors"
                >
                  Start Chat
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  </PublicLayout>
</template>
