import { ref } from 'vue'
import L from 'leaflet'
import { sanitizeGeoJson } from './useGeoJsonSanitize'
import axios from 'axios'

export const MIN_ZOOM_PARCELS = 15
const DEBOUNCE_MS = 350

export const parcelStyle = {
  color: '#888888',
  weight: 1,
  fillOpacity: 0,
  opacity: 0.6,
}

export const parcelStyleHover = {
  color: '#e67e22',
  weight: 2,
  fillColor: '#f39c12',
  fillOpacity: 0.25,
  opacity: 1,
}

export const parcelStyleSelected = {
  color: '#2563eb',
  weight: 3,
  fillColor: '#3b82f6',
  fillOpacity: 0.3,
  opacity: 1,
}

/**
 * Composable spravující celý životní cyklus GeoJSON vrstvy s parcelami:
 * fetch dat, debounce, zrušení předchozích requestů, bezpečné odstraňování
 * vrstvy z mapy a validaci geometrie. Vrstvu udržuje mimo Vue reaktivitu
 * (plain proměnná), protože ji spravujeme přímo přes Leaflet API.
 */
export function useParcelLayer({ selectedParcelId, onSelect, showToast }) {
  const isFetching = ref(false)
  const parcelCount = ref(0)
  const hasNoLayerYet = ref(true)

  let currentGeoJsonLayer = null
  let loadTimeout = null
  let abortController = null
  let lastRequestKey = ''
  let lastCompletedKey = ''

  function getFeatureStyle(feature) {
    return feature.properties.inspire_id === selectedParcelId.value
      ? parcelStyleSelected
      : parcelStyle
  }

  function removeCurrentLayer(map) {
    if (currentGeoJsonLayer && map) {
      try {
        if (map.hasLayer(currentGeoJsonLayer)) {
          map.removeLayer(currentGeoJsonLayer)
        }
      } catch {
        // vrstva už neexistuje – ignorujeme
      }
    }
    currentGeoJsonLayer = null
  }

  function setFeatureSelectedStyle(id) {
    if (!currentGeoJsonLayer) return
    currentGeoJsonLayer.eachLayer((l) => {
      if (l.feature?.properties?.inspire_id === id) {
        l.setStyle(parcelStyle)
      }
    })
  }

  function onEachFeature(feature, layer) {
    const props = feature.properties

    layer.bindTooltip(
      `<strong>Parcela ${props.parcelni_cislo ?? '—'}</strong><br>${props.vymera ?? '—'} m²`,
      { sticky: true, direction: 'top', className: 'parcel-tooltip', opacity: 0.95 }
    )

    layer.on('mouseover', () => {
      if (props.inspire_id !== selectedParcelId.value) {
        layer.setStyle(parcelStyleHover)
        layer.bringToFront()
      }
    })

    layer.on('mouseout', () => {
      if (props.inspire_id !== selectedParcelId.value) {
        layer.setStyle(parcelStyle)
      }
    })

    layer.on('click', () => {
      const previousId = selectedParcelId.value
      if (previousId && previousId !== props.inspire_id) {
        setFeatureSelectedStyle(previousId)
      }
      onSelect(feature, layer, (l) => {
        l.setStyle(parcelStyleSelected)
        l.bringToFront()
      })
    })
  }

  function renderGeoJsonLayer(map, sanitizedData) {
    removeCurrentLayer(map)

    if (!sanitizedData.features.length) {
      hasNoLayerYet.value = true
      return
    }

    try {
      currentGeoJsonLayer = L.geoJSON(sanitizedData, {
        style: getFeatureStyle,
        onEachFeature,
      })
      currentGeoJsonLayer.addTo(map)
      hasNoLayerYet.value = false
    } catch (err) {
      console.error('Chyba při vykreslování GeoJSON vrstvy', err)
      currentGeoJsonLayer = null
      hasNoLayerYet.value = true
    }
  }

  function round(n) {
    return Math.round(n * 1e5) / 1e5
  }

  function trySelectPendingParcel(id, applySelection) {
    if (!currentGeoJsonLayer) return false
    let found = false

    currentGeoJsonLayer.eachLayer((l) => {
      if (l.feature?.properties?.inspire_id === id) {
        applySelection(l.feature, l)
        l.setStyle(parcelStyleSelected)
        found = true
      }
    })

    return found
  }

  async function loadParcels(map, pendingCallback) {
    if (!map) return

    const currentZoom = map.getZoom()
    if (currentZoom < MIN_ZOOM_PARCELS) {
      removeCurrentLayer(map)
      parcelCount.value = 0
      hasNoLayerYet.value = true
      lastRequestKey = ''
      lastCompletedKey = ''
      return
    }

    const bounds = map.getBounds()
    const params = {
      minLat: round(bounds.getSouth()),
      minLng: round(bounds.getWest()),
      maxLat: round(bounds.getNorth()),
      maxLng: round(bounds.getEast()),
    }

    const requestKey = JSON.stringify(params)

    if (requestKey === lastCompletedKey && !isFetching.value) return
    if (requestKey === lastRequestKey && isFetching.value) return

    lastRequestKey = requestKey
    abortController?.abort()
    abortController = new AbortController()
    const thisRequestController = abortController

    isFetching.value = true

    try {
      const response = await axios.get('/api/parcels/in-bounds', {
        params,
        signal: thisRequestController.signal,
      })

      const sanitized = sanitizeGeoJson(response.data)
      parcelCount.value = sanitized.features.length
      renderGeoJsonLayer(map, sanitized)
      lastCompletedKey = requestKey

      pendingCallback?.(trySelectPendingParcel)
    } catch (error) {
      if (error.name !== 'CanceledError' && error.code !== 'ERR_CANCELED') {
        console.error('Chyba při načítání parcel', error)
        showToast('Nepodařilo se načíst data o parcelách. Zkuste to prosím znovu.', 'error')
      }
    } finally {
      if (thisRequestController === abortController) {
        isFetching.value = false
      }
    }
  }

  function scheduleLoadParcels(map, immediate, pendingCallback) {
    clearTimeout(loadTimeout)
    if (immediate) {
      loadParcels(map, pendingCallback)
    } else {
      loadTimeout = setTimeout(() => loadParcels(map, pendingCallback), DEBOUNCE_MS)
    }
  }

  function destroy() {
    clearTimeout(loadTimeout)
    abortController?.abort()
  }

  return {
    isFetching,
    parcelCount,
    hasNoLayerYet,
    scheduleLoadParcels,
    loadParcels,
    removeCurrentLayer,
    setFeatureSelectedStyle,
    trySelectPendingParcel,
    destroy,
  }
}