import { ref } from 'vue'

const STORAGE_KEY = 'parcel_map_selected'

export function resolvePendingParcelFromStorage() {
  try {
    return localStorage.getItem(STORAGE_KEY) || null
  } catch {
    return null
  }
}

export function useSelectedParcel() {
  const selectedParcelId = ref(null)
  const parcelInfo = ref(null)

  function saveSelectedParcel(id) {
    if (id) {
      localStorage.setItem(STORAGE_KEY, id)
    } else {
      localStorage.removeItem(STORAGE_KEY)
    }
  }

  function updateShareUrl(feature) {
    const coords = feature.geometry.coordinates[0]
    const lng = coords.reduce((sum, c) => sum + c[0], 0) / coords.length
    const lat = coords.reduce((sum, c) => sum + c[1], 0) / coords.length

    const url = new URL(window.location.href)
    url.searchParams.set('parcel', feature.properties.inspire_id ?? '')
    url.searchParams.set('lat', lat.toFixed(6))
    url.searchParams.set('lng', lng.toFixed(6))
    window.history.replaceState({}, '', url)
  }

  function clearShareUrl() {
    const url = new URL(window.location.href)
    url.searchParams.delete('parcel')
    url.searchParams.delete('lat')
    url.searchParams.delete('lng')
    window.history.replaceState({}, '', url)
  }

  function select(feature, layer, applyStyleFn) {
    selectedParcelId.value = feature.properties.inspire_id
    parcelInfo.value = feature.properties
    applyStyleFn(layer)
    updateShareUrl(feature)
    saveSelectedParcel(feature.properties.inspire_id)
  }

  function close(clearLayerStyleFn) {
    if (selectedParcelId.value) {
      clearLayerStyleFn(selectedParcelId.value)
    }
    selectedParcelId.value = null
    parcelInfo.value = null
    clearShareUrl()
    saveSelectedParcel(null)
  }

  return { selectedParcelId, parcelInfo, saveSelectedParcel, select, close }
}