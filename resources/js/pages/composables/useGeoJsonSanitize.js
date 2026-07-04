/**
 * Rekurzivně ověří, že souřadnice nemají prázdné vnořené "ringy" ani NaN hodnoty.
 */
function hasValidCoordinates(coords, depth) {
  if (!Array.isArray(coords) || coords.length === 0) return false

  if (depth === 0) {
    return typeof coords[0] === 'number' && typeof coords[1] === 'number' &&
      !isNaN(coords[0]) && !isNaN(coords[1])
  }

  return coords.every((c) => hasValidCoordinates(c, depth - 1))
}

const GEOMETRY_DEPTH = {
  Point: 0,
  MultiPoint: 1,
  LineString: 1,
  MultiLineString: 2,
  Polygon: 2,
  MultiPolygon: 3,
}

function isValidFeature(feature) {
  if (!feature || feature.type !== 'Feature') return false
  const geom = feature.geometry
  if (!geom || typeof geom !== 'object') return false

  const depth = GEOMETRY_DEPTH[geom.type]
  if (depth === undefined) return false

  return hasValidCoordinates(geom.coordinates, depth)
}

export function sanitizeGeoJson(data) {
  if (!data || data.type !== 'FeatureCollection' || !Array.isArray(data.features)) {
    return { type: 'FeatureCollection', features: [] }
  }

  const validFeatures = data.features.filter(isValidFeature)
  const skipped = data.features.length - validFeatures.length

  if (skipped > 0) {
    console.warn(`Přeskočeno ${skipped} neplatných parcel (chybná geometrie).`)
  }

  return { type: 'FeatureCollection', features: validFeatures }
}