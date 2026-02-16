<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeTechRequest;
use App\Services\TechnologyLookupService;
use Illuminate\Http\JsonResponse;

class TechController extends Controller
{
    public function analyze(AnalyzeTechRequest $request, TechnologyLookupService $lookupService): JsonResponse
    {
        $analysis = $lookupService->resolvePrimary((string) $request->string('url'));

        return response()->json([
            'ok' => true,
            'analysis' => $analysis,
        ]);
    }
}
