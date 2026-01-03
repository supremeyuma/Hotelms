<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailOpen, Send, LogOut, CheckCircle2, Inbox } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verify Email" />

        <div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-slate-50/50">
            <div class="w-full max-w-md">
                
                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-white rounded-[2rem] text-indigo-600 shadow-xl shadow-slate-200/50 mb-6">
                        <MailOpen class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Check Your Inbox</h1>
                    <p class="text-slate-500 font-medium px-6">
                        We've sent a verification link to your email. Please click it to confirm your account.
                    </p>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    
                    <Transition
                        enter-active-class="transform transition duration-500 ease-out"
                        enter-from-class="translate-y-[-10px] opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                    >
                        <div v-if="verificationLinkSent" class="p-6 bg-emerald-50 border-b border-emerald-100 flex items-center gap-3">
                            <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                            <p class="text-xs font-bold text-emerald-800 leading-tight">
                                A fresh verification link has been sent to your email address.
                            </p>
                        </div>
                    </Transition>

                    <div class="p-8 md:p-10 text-center">
                        <div class="mb-8 flex justify-center">
                            <div class="relative">
                                <Inbox class="w-16 h-16 text-slate-100" />
                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-indigo-500 rounded-full animate-ping"></div>
                                <div class="absolute -top-1 -right-1 w-4 h-4 bg-indigo-600 rounded-full border-2 border-white"></div>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                <Send class="w-5 h-5 group-hover:-translate-y-1 group-hover:translate-x-1 transition-transform" />
                                Resend Email
                            </button>
                        </form>
                    </div>

                    <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex items-center justify-center">
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex items-center gap-2 text-xs font-black text-slate-400 uppercase tracking-widest hover:text-rose-600 transition-colors"
                        >
                            <LogOut class="w-4 h-4" />
                            Sign Out & Exit
                        </Link>
                    </div>
                </div>

                <p class="text-center mt-8 text-slate-400 text-[10px] font-bold uppercase tracking-widest leading-relaxed px-10">
                    Can't find the email? Check your <span class="text-slate-600">spam</span> or <span class="text-slate-600">promotions</span> folder.
                </p>
            </div>
        </div>
    </GuestLayout>
</template>