<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningVisitRequest;
use App\Models\Client;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ScreeningVisitController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        $visits = $client->visits()
            ->with([
                'cervicalScreening',
                'breastScreening',
                'colorectalScreening',
                'liverScreening',
                'prostateScreening',
            ])
            ->latest('visitDate')
            ->get();

        return response()->json([
            'visits' => $visits,
        ]);
    }


    public function indexAll(Request $request): JsonResponse
{
    $facilityId = auth('api')->user()->facilityId;

    $query = ScreeningVisit::with([
        'client',
        'cervicalScreening',
        'breastScreening',
        'colorectalScreening',
        'liverScreening',
        'prostateScreening',
    ])->where('facilityId', $facilityId);

    if ($request->filled('visitType')) {
        $query->where('visitType', $request->visitType);
    }

    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('notes', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('fullName', 'like', "%{$search}%")
                              ->orWhere('phoneNumber', 'like', "%{$search}%");
              });
        });
    }

    $visits = $query->latest('visitDate')->paginate(10);

    return response()->json($visits);
}

    public function store(StoreScreeningVisitRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        $visit = ScreeningVisit::create([
            ...$request->validated(),
            'clientId' => $client->clientId,
            'facilityId' => $client->facilityId,
            'createdBy' => auth('api')->id(),
        ]);

        return response()->json([
            'message' => 'Screening visit created successfully',
            'visit' => $visit,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $visit->load([
            'client',
            'cervicalScreening',
            'breastScreening',
            'colorectalScreening',
            'liverScreening',
            'prostateScreening',
        ]);

        return response()->json([
            'visit' => $visit,
        ]);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $client->facility_id !== $user->facility_id) {
            abort(403, 'You cannot access this client');
        }
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}