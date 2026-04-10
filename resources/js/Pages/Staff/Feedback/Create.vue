<script setup>
import BaseStaffLayout from '@/Layouts/Staff/BaseStaffLayout.vue'
import FeedbackForm from '@/Components/Feedback/FeedbackForm.vue'

defineProps({
  formConfig: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <BaseStaffLayout>
    <FeedbackForm
      :config="formConfig"
      title="Staff Feedback"
      subtitle="Use this channel to raise concerns, flag operational issues, or share suggestions without exposing your identity unless you choose to."
      helper="If you switch anonymity off, your saved contact details let management follow up directly."
    />
  </BaseStaffLayout>
</template>
