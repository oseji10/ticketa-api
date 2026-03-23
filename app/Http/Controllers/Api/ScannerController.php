<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerController extends Controller
{
    public function __construct(
        protected EventRedemptionService $eventRedemptionService
    ) {
    }

    public function redeem(Request $request): JsonResponse
    {
        $scanner = auth()->user();

        if (!$scanner) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->eventRedemptionService->redeem(
            token: trim($validated['token']),
            scanner: $scanner,
            deviceName: $validated['deviceName'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}