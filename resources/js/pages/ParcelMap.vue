<script setup>
import { LMap, LTileLayer, LPolygon } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'

import { resolveInitialPosition, useMapPosition } from './composables/useMapPosition'
import { resolvePendingParcelFromStorage, useSelectedParcel } from './composables/useSelectedParcel'
import { useToasts } from './composables/useToasts'
import { useParcelLayer, MIN_ZOOM_PARCELS } from './composables/useParcelLayer'

import MapLegend from './components/MapLegend.vue'
import ParcelInfoPanel from './components/ParcelInfoPanel.vue'
import ToastContainer from './components/ToastContainer.vue'
import HomeButton from './components/HomeButton.vue'
import ZoomHintButton from './components/ZoomHintButton.vue'
import LoadingIndicator from './components/LoadingIndicator.vue'

const DEFAULT_CENTER = [50.4372, 15.3516]
const DEFAULT_ZOOM = 15
const TARGET_ZOOM = 16
const jicinBounds = [[50.28, 15.05], [50.55, 15.55]]

const urlParams = new URLSearchParams(window.location.search)
let pendingParcelIdFromUrl = urlParams.get('parcel')
let pendingParcelIdFromStorage = resolvePendingParcelFromStorage()

const initialPosition = resolveInitialPosition()
if (initialPosition.urlParcel) {
  pendingParcelIdFromUrl = initialPosition.urlParcel
  pendingParcelIdFromStorage = null
}

const zoom = ref(initialPosition.zoom)
const center = ref(initialPosition.center)
const mapRef = ref(null)

const { savePosition } = useMapPosition()
const { toasts, showToast } = useToasts()
const { selectedParcelId, parcelInfo, select, close } = useSelectedParcel()

const {
  isFetching,
  parcelCount,
  hasNoLayerYet,
  scheduleLoadParcels,
  loadParcels,
  setFeatureSelectedStyle,
  trySelectPendingParcel,
  destroy: destroyParcelLayer,
} = useParcelLayer({
  selectedParcelId,
  onSelect: (feature, layer, applyStyleFn) => select(feature, layer, applyStyleFn),
  showToast,
})

const mapOptions = {
  preferCanvas: true,
  zoomAnimation: true,
  markerZoomAnimation: false,
}

const maskPolygon = computed(() => [
  [
    [90, -180],
    [90, 180],
    [-90, 180],
    [-90, -180],
  ],
  [
    [jicinBounds[1][0], jicinBounds[0][1]],
    [jicinBounds[1][0], jicinBounds[1][1]],
    [jicinBounds[0][0], jicinBounds[1][1]],
    [jicinBounds[0][0], jicinBounds[0][1]],
  ],
])

function handleResize() {
  mapRef.value?.leafletObject?.invalidateSize()
}

function handleKeydown(e) {
  if (e.key === 'Escape' && parcelInfo.value) {
    closeInfoPanel()
  }
}

onMounted(() => {
  window.addEventListener('resize', handleResize)
  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  window.removeEventListener('keydown', handleKeydown)
  destroyParcelLayer()
})

function resolvePendingSelection(trySelectFn) {
  if (pendingParcelIdFromUrl) {
    trySelectFn(pendingParcelIdFromUrl, (feature) => select(feature, null, () => {}))
    pendingParcelIdFromUrl = null
  } else if (pendingParcelIdFromStorage && !selectedParcelId.value) {
    trySelectFn(pendingParcelIdFromStorage, (feature) => select(feature, null, () => {}))
  }
}

function onMapReady() {
  const map = mapRef.value.leafletObject

  map.on('moveend', () => {
    scheduleLoadParcels(map, false, resolvePendingSelection)
    savePosition(map)
  })

  map.on('zoomend', () => {
    scheduleLoadParcels(map, true, resolvePendingSelection)
    savePosition(map)
  })

  map.on('click', onMapBackgroundClick)
  loadParcels(map, resolvePendingSelection)
}

function onMapBackgroundClick(e) {
  const { lat, lng } = e.latlng
  const withinBounds =
    lat >= jicinBounds[0][0] && lat <= jicinBounds[1][0] &&
    lng >= jicinBounds[0][1] && lng <= jicinBounds[1][1]

  if (!withinBounds) {
    showToast('Data jsou dostupná jen pro okres Jičín.', 'info')
  }
}

function goToJicin() {
  mapRef.value?.leafletObject?.setView(DEFAULT_CENTER, DEFAULT_ZOOM, { animate: true })
}

function zoomToParcelLevel() {
  mapRef.value?.leafletObject?.setZoom(TARGET_ZOOM, { animate: true })
}

function closeInfoPanel() {
  close(setFeatureSelectedStyle)
}

function handleCopyFailed(text) {
  showToast(`Kopírování se nezdařilo. Číslo parcely: ${text}`, 'error', 6000)
}
</script>

<template>
  <div class="map-container">
    <l-map
      ref="mapRef"
      v-model:zoom="zoom"
      :center="center"
      :max-zoom="19"
      :min-zoom="11"
      :max-bounds="jicinBounds"
      :options="mapOptions"
      @ready="onMapReady"
    >
      <l-tile-layer
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        layer-type="base"
        name="OpenStreetMap"
        attribution="&copy; OpenStreetMap contributors"
        :max-zoom="19"
        :max-native-zoom="19"
        :options="{ keepBuffer: 3, updateWhenZooming: false, updateWhenIdle: true }"
      />

      <l-polygon
        :lat-lngs="maskPolygon"
        fill-color="#333333"
        :fill-opacity="0.55"
        color="transparent"
        :interactive="false"
      />
    </l-map>

    <HomeButton @click="goToJicin" />
    <MapLegend />
    <LoadingIndicator :is-fetching="isFetching" :parcel-count="parcelCount" />
    <ParcelInfoPanel :parcel-info="parcelInfo" @close="closeInfoPanel" @copy-failed="handleCopyFailed" />
    <ZoomHintButton :visible="hasNoLayerYet" @click="zoomToParcelLevel" />
    <ToastContainer :toasts="toasts" />
  </div>
</template>

<style scoped>
.map-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  width: 100vw;
  height: 100vh;
}
</style>

<style>
.parcel-tooltip {
  font-size: 12px;
  line-height: 1.4;
  padding: 4px 8px;
}

.leaflet-tile {
  transition: opacity 0.2s ease-in;
}

.leaflet-interactive:focus,
path.leaflet-interactive:focus {
  outline: none !important;
}
</style>