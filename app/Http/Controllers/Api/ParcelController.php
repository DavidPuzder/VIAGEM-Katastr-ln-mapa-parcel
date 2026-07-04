<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CuzkService;
use Illuminate\Http\Request;

class ParcelController extends Controller
{
    public function __construct(protected CuzkService $cuzkService) {}

    public function info(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        if (!$this->cuzkService->isWithinAllowedArea($validated['lat'], $validated['lng'])) {
            return response()->json(['error' => 'Lokalita je mimo povolenou oblast (okres Jičín).'], 422);
        }

        $info = $this->cuzkService->getParcelByPoint($validated['lat'], $validated['lng']);

        if (!$info) {
            return response()->json(['error' => 'Na tomto místě nebyla nalezena žádná parcela.'], 404);
        }

        return response()->json($info);
    }

    public function inBounds(Request $request)
    {
        $validated = $request->validate([
            'minLat' => 'required|numeric',
            'minLng' => 'required|numeric',
            'maxLat' => 'required|numeric',
            'maxLng' => 'required|numeric',
        ]);

        $centerLat = ($validated['minLat'] + $validated['maxLat']) / 2;
        $centerLng = ($validated['minLng'] + $validated['maxLng']) / 2;

        if (!$this->cuzkService->isWithinAllowedArea($centerLat, $centerLng)) {
            return response()->json(['type' => 'FeatureCollection', 'features' => []]);
        }

        $geojson = $this->cuzkService->getParcelsInBounds(
            $validated['minLat'],
            $validated['minLng'],
            $validated['maxLat'],
            $validated['maxLng']
        );

        return response()->json($geojson)
            ->header('Cache-Control', 'public, max-age=60');
    }
}