<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Mail, ArrowRight, LifeBuoy, ChevronLeft, CheckCircle2 } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-slate-50/50">
            <div class="w-full max-w-md">
                
                <Link 
                    :href="route('login')" 
                    class="inline-flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-indigo-600 transition-colors mb-8 group"
                >
                    <ChevronLeft class="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                    Back to Sign In
                </Link>

                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-white rounded-[2rem] text-indigo-600 shadow-xl shadow-slate-200/50 mb-6">
                        <LifeBuoy class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Reset Password</h1>
                    <p class="text-slate-500 font-medium px-4">
                        No problem. Enter your email and we'll send a secure link to get you back into your account.
                    </p>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    
                    <Transition
                        enter-active-class="transform transition duration-500 ease-out"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                    >
                        <div v-if="status" class="p-6 bg-emerald-50 border-b border-emerald-100 flex items-center gap-3">
                            <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
                            <p class="text-sm font-bold text-emerald-800 leading-tight">{{ status }}</p>
                        </div>
                    </Transition>

                    <form @submit.prevent="submit" class="p-8 md:p-10 space-y-8">
                        <div class="space-y-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Account Email</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <Mail class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    v-model="form.email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="your@email.com"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                Send Reset Link
                                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>
                </div>

                <p class="text-center mt-8 text-slate-400 text-xs font-medium">
                    Having trouble? <a href="mailto:support@hotel.com" class="text-indigo-600 font-bold hover:underline">Contact Concierge</a>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
:deep(input) {
    outline: none !important;
    box-shadow: none !important;
}
</style>