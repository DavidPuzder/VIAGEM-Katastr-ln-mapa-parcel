const STORAGE_KEY = 'parcel_map_position'
export const DEFAULT_CENTER = [50.4372, 15.3516]
export const DEFAULT_ZOOM = 15

export function resolveInitialPosition() {
  if (typeof window === 'undefined') {
    return { center: DEFAULT_CENTER, zoom: DEFAULT_ZOOM, urlParcel: null }
  }

  const urlParams = new URLSearchParams(window.location.search)
  const urlLat = parseFloat(urlParams.get('lat'))
  const urlLng = parseFloat(urlParams.get('lng'))
  const urlParcel = urlParams.get('parcel')

  if (!isNaN(urlLat) && !isNaN(urlLng)) {
    return { center: [urlLat, urlLng], zoom: 17, urlParcel }
  }

  try {
    const savedPosition = JSON.parse(localStorage.getItem(STORAGE_KEY))
    if (savedPosition?.lat && savedPosition?.lng && savedPosition?.zoom) {
      return { center: [savedPosition.lat, savedPosition.lng], zoom: savedPosition.zoom, urlParcel: null }
    }
  } catch {
    // ignore corrupted storage
  }

  return { center: DEFAULT_CENTER, zoom: DEFAULT_ZOOM, urlParcel: null }
}

export function useMapPosition() {
  function savePosition(map) {
    if (!map) return
    const c = map.getCenter()
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      lat: c.lat,
      lng: c.lng,
      zoom: map.getZoom(),
    }))
  }

  return { savePosition }
}