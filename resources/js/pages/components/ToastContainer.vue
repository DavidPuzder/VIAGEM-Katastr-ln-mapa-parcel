<script setup>
defineProps({
  toasts: { type: Array, required: true },
})
</script>

<template>
  <div class="toast-container">
    <transition-group name="toast">
      <div v-for="toast in toasts" :key="toast.id" class="toast" :class="`toast-${toast.type}`">
        {{ toast.message }}
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-container {
  position: absolute;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2000;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.toast {
  background: rgba(30, 30, 30, 0.92);
  color: white;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
  max-width: 90vw;
  text-align: center;
}

.toast-error {
  background: rgba(185, 28, 28, 0.92);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>