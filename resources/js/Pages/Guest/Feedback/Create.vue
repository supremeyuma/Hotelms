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
      title="Stay Feedback"
      subtitle="Tell management what is going well or what needs attention during your stay. Anonymous submissions are supported."
      helper="If you want the team to contact you about this message, turn anonymity off before submitting."
    />
  </GuestLayout>
</template>
