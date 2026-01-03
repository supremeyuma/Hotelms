<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Lock, Mail, ArrowRight, ShieldCheck, UserCircle } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-slate-50/50">
            <div class="w-full max-w-md">
                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-slate-900 rounded-[2rem] text-white shadow-xl mb-6">
                        <UserCircle class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Welcome Back</h1>
                    <p class="text-slate-500 font-medium">Please enter your credentials to access your portal</p>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    <div v-if="status" class="p-4 bg-emerald-50 text-emerald-700 text-sm font-bold text-center border-b border-emerald-100">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="p-8 md:p-10 space-y-6">
                        <div class="space-y-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label>
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
                                    placeholder="name@example.com"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between items-center ml-1">
                                <label class="text-xs font-black text-slate-400 uppercase tracking-widest">Password</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-[10px] font-black text-indigo-600 uppercase tracking-widest hover:text-slate-900 transition-colors"
                                >
                                    Forgot?
                                </Link>
                            </div>
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
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <label class="flex items-center cursor-pointer group">
                                <Checkbox 
                                    name="remember" 
                                    v-model:checked="form.remember" 
                                    class="rounded-lg border-slate-200 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="ms-2 text-xs font-bold text-slate-500 group-hover:text-slate-700 transition-colors">Remember this device</span>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                Sign In
                                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>
                    
                    <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex items-center justify-center gap-2">
                        <ShieldCheck class="w-4 h-4 text-emerald-500" />
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Secure Multi-factor Authentication</span>
                    </div>
                </div>

                <p class="text-center mt-8 text-slate-400 text-xs font-medium">
                    Don't have an account? 
                    <Link :href="route('register')" class="text-indigo-600 font-bold hover:underline ml-1">Create one here</Link>
                </p>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
/* Ensure TextInput doesn't override our boutique style */
:deep(input) {
    outline: none !important;
    box-shadow: none !important;
}
</style>