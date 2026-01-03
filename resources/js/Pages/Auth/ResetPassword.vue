<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, Mail, Lock, ShieldCheck, ArrowRight, RefreshCcw } from 'lucide-vue-next';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="min-h-[85vh] bg-slate-50/50 flex items-center justify-center px-4 py-12">
            <div class="w-full max-w-md">
                
                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-slate-900 rounded-[2rem] text-white shadow-xl mb-6">
                        <KeyRound class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">New Credentials</h1>
                    <p class="text-slate-500 font-medium">Please choose a strong password to secure your account.</p>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    <form @submit.prevent="submit" class="p-8 md:p-10 space-y-6">
                        
                        <div class="space-y-2 opacity-60">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Identity</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400">
                                    <Mail class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-100 border-none rounded-2xl font-bold text-slate-500 cursor-not-allowed shadow-none"
                                    v-model="form.email"
                                    required
                                    readonly
                                    autocomplete="username"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">New Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <Lock class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="password"
                                    type="password"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    v-model="form.password"
                                    required
                                    autofocus
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Confirm Password</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <ShieldCheck class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                <RefreshCcw v-if="form.processing" class="w-5 h-5 animate-spin" />
                                <span v-else>Update Password</span>
                                <ArrowRight v-if="!form.processing" class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>

                    <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex items-center justify-center gap-2">
                        <ShieldCheck class="w-4 h-4 text-emerald-500" />
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Enhanced Password Protection Active</span>
                    </div>
                </div>
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