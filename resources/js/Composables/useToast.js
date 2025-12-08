import { ref } from 'vue';

export function useToast() {
  const messages = ref([]);

  function push(message, type = 'info', timeout = 4000) {
    const id = Date.now();
    messages.value.push({ id, message, type });
    setTimeout(() => dismiss(id), timeout);
  }

  function dismiss(id) {
    messages.value = messages.value.filter(m => m.id !== id);
  }

  return { messages, push, dismiss };
}
