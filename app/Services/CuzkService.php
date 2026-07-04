<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CuzkService
{
    protected string $wfsUrl = 'https://services.cuzk.gov.cz/wfs/inspire-cp-wfs.asp';
    protected array $bbox;
    protected int $tileCacheTtl;
    protected float $gridSize;

    public function __construct()
    {
        $this->bbox = config('cuzk.jicin_bbox');
        $this->tileCacheTtl = config('cuzk.cache_ttl_minutes', 1440);
        $this->gridSize = 0.005;
    }

    public function isWithinAllowedArea(float $lat, float $lng): bool
    {
        return $lat >= $this->bbox['min_lat'] && $lat <= $this->bbox['max_lat']
            && $lng >= $this->bbox['min_lng'] && $lng <= $this->bbox['max_lng'];
    }

    public function getParcelsInBounds(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $maxSpan = 0.05;
        if (($maxLat - $minLat) > $maxSpan || ($maxLng - $minLng) > $maxSpan) {
            $centerLat = ($minLat + $maxLat) / 2;
            $centerLng = ($minLng + $maxLng) / 2;
            $minLat = $centerLat - $maxSpan / 2;
            $maxLat = $centerLat + $maxSpan / 2;
            $minLng = $centerLng - $maxSpan / 2;
            $maxLng = $centerLng + $maxSpan / 2;
        }

        $tiles = $this->getGridTiles($minLat, $minLng, $maxLat, $maxLng);

        $missingTiles = [];
        $cachedResults = [];

        foreach ($tiles as $tile) {
            $key = $this->tileCacheKey($tile);
            $cached = Cache::get($key);
            if ($cached !== null) {
                $cachedResults[$key] = $cached;
            } else {
                $missingTiles[$key] = $tile;
            }
        }

        if (!empty($missingTiles)) {
            $fetched = $this->fetchTilesInParallel($missingTiles);
            $cachedResults = array_merge($cachedResults, $fetched);
        }

        $allFeatures = [];
        $seenIds = [];

        foreach ($cachedResults as $tileFeatures) {
            foreach ($tileFeatures as $feature) {
                $id = $feature['properties']['inspire_id'] ?? null;
                if ($id && !isset($seenIds[$id]) && $this->featureIntersectsBbox($feature, $minLat, $minLng, $maxLat, $maxLng)) {
                    $seenIds[$id] = true;
                    $allFeatures[] = $feature;
                }
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $allFeatures,
        ];
    }

    protected function featureIntersectsBbox(array $feature, float $minLat, float $minLng, float $maxLat, float $maxLng): bool
    {
        $coords = $feature['geometry']['coordinates'][0] ?? [];
        if (empty($coords)) {
            return false;
        }

        foreach ($coords as $point) {
            [$lng, $lat] = $point;
            if ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng) {
                return true;
            }
        }

        return false;
    }

    /**
     * KLÍČOVÁ OPRAVA VÝKONU: první stránka VŠECH chybějících dlaždic se
     * stahuje najednou přes Http::pool (paralelně). Až u dlaždic, které mají
     * přesně $pageSize výsledků (téměř nikdy u ~500m dlaždic), se doplňkové
     * stránky dotahují sekvenčně - ale to je extrémně vzácný případ.
     */
    protected function fetchTilesInParallel(array $missingTiles): array
    {
        $results = [];
        $lockedKeys = [];
        $toFetch = [];

        foreach ($missingTiles as $key => $tile) {
            $lock = Cache::lock("lock_{$key}", 10);
            if ($lock->get()) {
                $lockedKeys[$key] = $lock;
                $toFetch[$key] = $tile;
            } else {
                $lock2 = Cache::lock("lock_{$key}", 10);
                $lock2->block(5);
                $results[$key] = Cache::get($key, []);
                $lock2->release();
            }
        }

        if (!empty($toFetch)) {
            $pageSize = 1000;

            // Krok 1: první stránka VŠECH dlaždic paralelně
            $responses = Http::pool(fn ($pool) => collect($toFetch)->map(
                fn ($tile, $key) => $pool->as($key)->timeout(12)->retry(2, 200)->get($this->wfsUrl, [
                    'SERVICE' => 'WFS',
                    'VERSION' => '2.0.0',
                    'REQUEST' => 'GetFeature',
                    'TYPENAMES' => 'cp:CadastralParcel',
                    'SRSNAME' => 'EPSG:4326',
                    'BBOX' => "{$tile['minLng']},{$tile['minLat']},{$tile['maxLng']},{$tile['maxLat']},EPSG:4326",
                    'COUNT' => $pageSize,
                    'STARTINDEX' => 0,
                ])
            )->toArray());

            $needsMorePages = [];

            foreach ($toFetch as $key => $tile) {
                $response = $responses[$key] ?? null;
                $features = ($response && $response->successful())
                    ? $this->gmlToFeatures($response->body())
                    : [];

                $results[$key] = $features;

                if (count($features) === $pageSize) {
                    $needsMorePages[$key] = $tile;
                }
            }

            // Krok 2: doplňkové stránky jen pro dlaždice, které dosáhly limitu
            // (u dlaždic ~500m² je toto v praxi téměř nikdy potřeba)
            if (!empty($needsMorePages)) {
                foreach ($needsMorePages as $key => $tile) {
                    $extra = $this->fetchRemainingPages($tile, $pageSize);
                    $results[$key] = array_merge($results[$key], $extra);
                }
            }

            foreach ($results as $key => $features) {
                if (isset($toFetch[$key])) {
                    Cache::put($key, $features, now()->addMinutes($this->tileCacheTtl));
                }
            }

            foreach ($lockedKeys as $lock) {
                $lock->release();
            }
        }

        return $results;
    }

    protected function fetchRemainingPages(array $tile, int $pageSize): array
    {
        $bbox = "{$tile['minLng']},{$tile['minLat']},{$tile['maxLng']},{$tile['maxLat']},EPSG:4326";
        $allFeatures = [];
        $startIndex = $pageSize;
        $maxPages = 9;

        for ($page = 0; $page < $maxPages; $page++) {
            $response = Http::timeout(12)->retry(2, 200)->get($this->wfsUrl, [
                'SERVICE' => 'WFS',
                'VERSION' => '2.0.0',
                'REQUEST' => 'GetFeature',
                'TYPENAMES' => 'cp:CadastralParcel',
                'SRSNAME' => 'EPSG:4326',
                'BBOX' => $bbox,
                'COUNT' => $pageSize,
                'STARTINDEX' => $startIndex,
            ]);

            if (!$response->successful()) {
                break;
            }

            $features = $this->gmlToFeatures($response->body());
            $allFeatures = array_merge($allFeatures, $features);

            if (count($features) < $pageSize) {
                break;
            }

            $startIndex += $pageSize;
        }

        return $allFeatures;
    }

    protected function tileCacheKey(array $tile): string
    {
        return "parcel_tile_" . round($tile['minLat'], 5) . "_" . round($tile['minLng'], 5);
    }

    protected function getGridTiles(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $tiles = [];
        $startLat = floor($minLat / $this->gridSize) * $this->gridSize;
        $startLng = floor($minLng / $this->gridSize) * $this->gridSize;

        for ($lat = $startLat; $lat < $maxLat; $lat += $this->gridSize) {
            for ($lng = $startLng; $lng < $maxLng; $lng += $this->gridSize) {
                $tiles[] = [
                    'minLat' => round($lat, 5),
                    'minLng' => round($lng, 5),
                    'maxLat' => round($lat + $this->gridSize, 5),
                    'maxLng' => round($lng + $this->gridSize, 5),
                ];
            }
        }

        return $tiles;
    }

    public function getParcelByPoint(float $lat, float $lng): ?array
    {
        $tile = [
            'minLat' => floor($lat / $this->gridSize) * $this->gridSize,
            'minLng' => floor($lng / $this->gridSize) * $this->gridSize,
            'maxLat' => floor($lat / $this->gridSize) * $this->gridSize + $this->gridSize,
            'maxLng' => floor($lng / $this->gridSize) * $this->gridSize + $this->gridSize,
        ];

        $key = $this->tileCacheKey($tile);
        $features = Cache::get($key);

        if ($features === null) {
            $results = $this->fetchTilesInParallel([$key => $tile]);
            $features = $results[$key] ?? [];
        }

        foreach ($features as $feature) {
            $coords = $feature['geometry']['coordinates'][0] ?? [];
            if ($this->pointInPolygon($lng, $lat, $coords)) {
                return $feature['properties'];
            }
        }

        return null;
    }

    protected function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    protected function gmlToFeatures(string $xml): array
    {
        $features = [];

        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            $xpath = new \DOMXPath($dom);

            $namespaces = [
                'cp' => 'urn:x-inspire:specification:gmlas:CadastralParcels:3.0',
                'gml' => 'http://www.opengis.net/gml/3.2',
                'base' => 'urn:x-inspire:specification:gmlas:BaseTypes:3.2',
            ];
            foreach ($namespaces as $prefix => $uri) {
                $xpath->registerNamespace($prefix, $uri);
            }

            foreach ($xpath->query('//cp:CadastralParcel') as $node) {
                $label = $xpath->query('.//cp:label', $node)->item(0)?->nodeValue;
                $reference = $xpath->query('.//cp:nationalCadastralReference', $node)->item(0)?->nodeValue;
                $area = $xpath->query('.//cp:areaValue', $node)->item(0)?->nodeValue;
                $localId = $xpath->query('.//base:localId', $node)->item(0)?->nodeValue;
                $validFrom = $xpath->query('.//cp:beginLifespanVersion', $node)->item(0)?->nodeValue;

                $posList = $xpath->query('.//gml:posList', $node)->item(0)?->nodeValue;
                if (!$posList) {
                    continue;
                }

                $coords = $this->parsePosList($posList);
                if (empty($coords)) {
                    continue;
                }

                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [$coords],
                    ],
                    'properties' => [
                        'inspire_id' => $localId,
                        'parcelni_cislo' => $label,
                        'katastralni_uzemi' => $reference,
                        'vymera' => $area,
                        'platne_od' => $validFrom ? substr($validFrom, 0, 10) : null,
                    ],
                ];
            }
        } catch (\Exception $e) {
            return [];
        }

        return $features;
    }

    protected function parsePosList(string $posList): array
    {
        $numbers = array_map('floatval', preg_split('/\s+/', trim($posList)));
        $coords = [];

        for ($i = 0; $i < count($numbers) - 1; $i += 2) {
            $coords[] = [round($numbers[$i], 6), round($numbers[$i + 1], 6)];
        }

        return $coords;
    }
}