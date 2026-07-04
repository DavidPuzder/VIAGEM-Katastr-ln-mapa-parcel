<template>
  <div class="legend">
    <div class="legend-item">
      <span class="legend-swatch" style="background: transparent; border-color: #888888;"></span>
      Parcela
    </div>
    <div class="legend-item">
      <span class="legend-swatch" style="background: #f39c12; border-color: #e67e22;"></span>
      Najeto myší
    </div>
    <div class="legend-item">
      <span class="legend-swatch" style="background: #3b82f6; border-color: #2563eb;"></span>
      Vybráno
    </div>
  </div>
</template>

<style scoped>
.legend {
  position: absolute;
  bottom: 20px;
  right: 20px;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.95);
  padding: 10px 14px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  font-size: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.legend-swatch {
  width: 16px;
  height: 16px;
  border: 2px solid;
  border-radius: 3px;
  flex-shrink: 0;
}

@media (max-width: 640px) {
  .legend {
    bottom: auto;
    top: 20px;
    right: 20px;
    font-size: 11px;
    padding: 8px 10px;
  }
}
</style>