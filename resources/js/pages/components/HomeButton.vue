<script setup>
defineEmits(['click'])
</script>

<template>
  <button class="home-btn" @click="$emit('click')" title="Návrat na Jičín" aria-label="Návrat na Jičín">
    🏠
  </button>
</template>

<style scoped>
.home-btn {
  position: absolute;
  top: 100px;
  left: 20px;
  z-index: 1000;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: none;
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  font-size: 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.15s ease;
}

.home-btn:hover {
  transform: scale(1.08);
}

@media (max-width: 640px) {
  .home-btn {
    top: 90px;
  }
}
</style>