<script setup>
defineProps({
  visible: { type: Boolean, default: false },
})
defineEmits(['click'])
</script>

<template>
  <transition name="fade">
    <button v-if="visible" class="zoom-hint" @click="$emit('click')">
      Přibližte mapu pro zobrazení parcel
    </button>
  </transition>
</template>

<style scoped>
.zoom-hint {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 12px 20px;
  min-height: 44px;
  border-radius: 22px;
  font-size: 14px;
  border: none;
  cursor: pointer;
  transition: background 0.2s ease;
}

.zoom-hint:hover {
  background: rgba(0, 0, 0, 0.85);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 640px) {
  .zoom-hint {
    bottom: 70px;
  }
}
</style>