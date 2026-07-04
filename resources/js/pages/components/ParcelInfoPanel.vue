<script setup>
import { ref } from 'vue'

const props = defineProps({
  parcelInfo: { type: Object, default: null },
})

const emit = defineEmits(['close'])
const copyFeedback = ref(false)

function fallbackCopy(text) {
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.left = '-9999px'
  textarea.style.top = '0'
  document.body.appendChild(textarea)
  textarea.focus()
  textarea.select()
  const success = document.execCommand('copy')
  document.body.removeChild(textarea)
  if (!success) throw new Error('execCommand failed')
}

async function copyParcelNumber() {
  const text = props.parcelInfo?.parcelni_cislo
  if (!text) return

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
    } else {
      fallbackCopy(text)
    }
    copyFeedback.value = true
    setTimeout(() => (copyFeedback.value = false), 1500)
  } catch {
    try {
      fallbackCopy(text)
      copyFeedback.value = true
      setTimeout(() => (copyFeedback.value = false), 1500)
    } catch {
      emit('copy-failed', text)
    }
  }
}
</script>

<template>
  <transition name="fade">
    <div v-if="parcelInfo" class="info-panel">
      <button class="close-btn" @click="emit('close')" aria-label="Zavřít">&times;</button>
      <h3>
        Parcela {{ parcelInfo.parcelni_cislo ?? '—' }}
        <button class="copy-btn" @click="copyParcelNumber" title="Zkopírovat číslo parcely" aria-label="Zkopírovat">
          {{ copyFeedback ? '✓' : '📋' }}
        </button>
      </h3>
      <p><strong>Katastrální území:</strong> {{ parcelInfo.katastralni_uzemi ?? '—' }}</p>
      <p><strong>Výměra:</strong> {{ parcelInfo.vymera ?? '—' }} m²</p>
      <p><strong>ID objektu:</strong> {{ parcelInfo.inspire_id ?? '—' }}</p>
      <p><strong>Platné od:</strong> {{ parcelInfo.platne_od ?? '—' }}</p>
    </div>
  </transition>
</template>

<style scoped>
.info-panel {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 1000;
  background: white;
  padding: 16px 20px;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
  max-width: 320px;
}

.info-panel h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 0;
}

.copy-btn {
  border: none;
  background: none;
  cursor: pointer;
  font-size: 14px;
  min-width: 28px;
  min-height: 28px;
  border-radius: 4px;
}

.copy-btn:hover {
  background: #f0f0f0;
}

.close-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 44px;
  height: 44px;
  border: none;
  background: none;
  font-size: 22px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
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
  .info-panel {
    top: auto;
    bottom: 0;
    left: 0;
    right: 0;
    max-width: 100%;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.25);
    padding-bottom: 24px;
  }
}
</style>