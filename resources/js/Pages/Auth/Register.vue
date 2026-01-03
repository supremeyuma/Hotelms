<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
  User, 
  Mail, 
  Lock, 
  UserPlus, 
  ArrowRight, 
  ShieldCheck,
  CheckCircle2
} from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account" />

        <div class="min-h-screen bg-slate-50/50 py-12 px-4 flex items-center justify-center">
            <div class="w-full max-w-lg">
                
                <div class="text-center mb-10">
                    <div class="inline-flex p-4 bg-indigo-600 rounded-[2rem] text-white shadow-xl shadow-indigo-100 mb-6">
                        <UserPlus class="w-10 h-10" />
                    </div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Join the Club</h1>
                    <p class="text-slate-500 font-medium">Create an account to manage your bookings and enjoy exclusive offers.</p>
                </div>

                <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-2xl shadow-slate-200/50 overflow-hidden">
                    <form @submit.prevent="submit" class="p-8 md:p-10 space-y-6">
                        
                        <div class="space-y-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Full Name</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <User class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="name"
                                    type="text"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    placeholder="John Doe"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                    <Mail class="w-5 h-5" />
                                </div>
                                <TextInput
                                    id="email"
                                    type="email"
                                    v-model="form.email"
                                    required
                                    placeholder="john@example.com"
                                    class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Password</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                        <Lock class="w-5 h-5" />
                                    </div>
                                    <TextInput
                                        id="password"
                                        type="password"
                                        v-model="form.password"
                                        required
                                        placeholder="••••••••"
                                        class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none text-sm"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Confirm</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-600 transition-colors">
                                        <ShieldCheck class="w-5 h-5" />
                                    </div>
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        v-model="form.password_confirmation"
                                        required
                                        placeholder="••••••••"
                                        class="block w-full pl-12 pr-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl focus:bg-white focus:border-indigo-600 focus:ring-0 transition-all font-bold text-slate-700 shadow-none text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="form.errors.password" />

                        <div class="pt-4">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full group flex items-center justify-center gap-3 py-5 bg-slate-900 text-white rounded-3xl font-black text-lg hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 active:scale-[0.98] disabled:opacity-50"
                            >
                                Create Account
                                <ArrowRight class="w-5 h-5 group-hover:translate-x-1 transition-transform" />
                            </button>
                        </div>
                    </form>

                    <div class="bg-slate-50 px-8 py-6 border-t border-slate-100 flex items-center justify-center gap-6">
                        <div class="flex items-center gap-1.5 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <CheckCircle2 class="w-4 h-4 text-emerald-500" /> Fast Setup
                        </div>
                        <div class="w-1.5 h-1.5 rounded-full bg-slate-200"></div>
                        <div class="flex items-center gap-1.5 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <ShieldCheck class="w-4 h-4 text-indigo-400" /> Secure Data
                        </div>
                    </div>
                </div>

                <p class="text-center mt-8 text-slate-400 text-sm font-bold">
                    Already part of the family? 
                    <Link :href="route('login')" class="text-indigo-600 hover:text-indigo-700 underline underline-offset-4 ml-1">
                        Sign In here
                    </Link>
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