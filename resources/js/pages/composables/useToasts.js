import { ref } from 'vue'

export function useToasts() {
  const toasts = ref([])
  let toastIdCounter = 0

  function showToast(message, type = 'info', duration = 4000) {
    const id = toastIdCounter++
    toasts.value.push({ id, message, type })
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id)
    }, duration)
  }

  return { toasts, showToast }
}