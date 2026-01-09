<script setup>
import FrontDeskLayout from '@/Layouts/Staff/FrontDeskLayout.vue'
import { useForm, Link, Head } from '@inertiajs/vue3'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import {
    User,
    Calendar,
    ChevronLeft,
    Save,
    DoorOpen,
    StickyNote
} from 'lucide-vue-next'

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
})


console.log('Rooms:', props.rooms)


const form = useForm({
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    room_id: '',
    check_in: '',
    check_out: '',
    notes: '',
})

function submit() {
    form.post(route('frontdesk.bookings.store'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <FrontDeskLayout>
        <Head title="Create Reservation" />

        <div class="min-h-screen bg-slate-50/50 p-6 md:p-12">
            <div class="max-w-3xl mx-auto">

                <Link
                    :href="route('frontdesk.bookings.index')"
                    class="inline-flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-indigo-600 mb-8"
                >
                    <ChevronLeft class="w-4 h-4" />
                    Back to Reservations
                </Link>

                <h1 class="text-3xl font-black text-slate-900 mb-8">
                    New Reservation
                </h1>

                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100">
                    <form @submit.prevent="submit" class="p-8 md:p-12 space-y-8">

                        <!-- Guest Name -->
                        <div>
                            <label class="label">
                                <User class="icon" /> Guest Name
                            </label>
                            <TextInput v-model="form.guest_name" class="input" />
                            <InputError :message="form.errors.guest_name" />
                        </div>

                        <!-- Contact -->
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="label">Email</label>
                                <TextInput v-model="form.guest_email" />
                            </div>
                            <div>
                                <label class="label">Phone</label>
                                <TextInput v-model="form.guest_phone" />
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="label">
                                    <Calendar class="icon" /> Check-In
                                </label>
                                <input type="date" v-model="form.check_in" class="input" />
                            </div>

                            <div>
                                <label class="label">Check-Out</label>
                                <input type="date" v-model="form.check_out" class="input" />
                            </div>
                        </div>

                        <!-- Room -->
                        <div>
                            <label class="label">
                                <DoorOpen class="icon" /> Room
                            </label>
                            <select v-model="form.room_id" class="input">
                                <option value="">Auto-assign</option>
                                <option v-for="room in rooms" :key="room.id" :value="room.id">
                                    {{ room.name }} ({{ room.room_type.title }})
                                </option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="label">
                                <StickyNote class="icon" /> Notes
                            </label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="input"
                            />
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-4 pt-6">
                            <button
                                class="flex-1 btn-primary"
                                :disabled="form.processing"
                            >
                                <Save class="w-5 h-5" />
                                Create Booking
                            </button>

                            <Link
                                :href="route('frontdesk.bookings.index')"
                                class="btn-secondary"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </FrontDeskLayout>
</template>

<style scoped>
.label {
    @apply text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2 mb-2;
}
.input {
    @apply w-full px-5 py-4 bg-slate-50 border-2 border-slate-50 rounded-2xl font-bold
           focus:bg-white focus:border-indigo-600 transition;
}
.btn-primary {
    @apply flex items-center justify-center gap-3 py-5 bg-slate-900 text-white
           rounded-3xl font-black text-lg hover:bg-indigo-600 transition;
}
.btn-secondary {
    @apply flex items-center justify-center py-5 bg-slate-100 text-slate-600
           rounded-3xl font-black text-lg hover:bg-slate-200;
}
.icon {
    @apply w-3 h-3;
}
</style>
