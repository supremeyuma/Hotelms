<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FeedbackForm from '@/Components/Feedback/FeedbackForm.vue'

defineProps({
  formConfig: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <GuestLayout>
    <FeedbackForm
      :config="formConfig"
      title="Share Feedback"
      subtitle="Guests, visitors, and team members can send feedback here. Anonymous submissions are supported by default."
      helper="Use this channel for compliments, complaints, and suggestions that should reach hotel management."
    />
  </GuestLayout>
</template>
