<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldAlert, Lock, ArrowRight, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="min-h-[80vh] flex items-center justify-center px-4 py-12 bg-slate-50/50">
            <div class="w-full max-w-md">
                
                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-indigo-50 text-indigo-600 rounded-[2rem] mb-6 shadow-sm">
                        <ShieldAlert class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Secure Area</h1>
                    <p class="text-slate-500 font-medium px-6">
                        For your protection, please confirm your password before accessing this sensitive area.
                    </p>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
                    <form @submit.prevent="submit" class="p-8 md:p-10 space-y-6">
                        
                        <div class="space-y-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Password</label>
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
                                    autofocus
                                    placeholder="••••••••"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                Confirm Access
                                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>

                    <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex items-center justify-center gap-2">
                        <ShieldCheck class="w-4 h-4 text-emerald-500" />
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">End-to-end Encrypted Session</span>
                    </div>
                </div>

                <p class="text-center mt-8">
                    <button 
                        @click="window.history.back()" 
                        class="text-slate-400 font-bold text-sm hover:text-slate-600 transition-colors"
                    >
                        Go back to safety
                    </button>
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