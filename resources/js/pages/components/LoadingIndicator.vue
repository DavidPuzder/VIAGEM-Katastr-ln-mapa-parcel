<script setup>
defineProps({
  isFetching: { type: Boolean, default: false },
  parcelCount: { type: Number, default: 0 },
})
</script>

<template>
  <transition name="fade">
    <div v-if="isFetching || parcelCount > 0" class="loading-indicator">
      <template v-if="isFetching">
        <span class="spinner"></span> Aktualizuji parcely…
      </template>
      <template v-else>
        {{ parcelCount }} {{ parcelCount === 1 ? 'parcela' : 'parcel' }} v tomto výřezu
      </template>
    </div>
  </transition>
</template>

<style scoped>
.loading-indicator {
  position: absolute;
  bottom: 20px;
  left: 20px;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.9);
  color: #333;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.spinner {
  width: 12px;
  height: 12px;
  border: 2px solid #ccc;
  border-top-color: #e67e22;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
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
  .loading-indicator {
    left: 50%;
    transform: translateX(-50%);
    bottom: 12px;
  }
}
</style>