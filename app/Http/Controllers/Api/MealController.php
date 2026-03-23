<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMealRequest;
use App\Http\Requests\UpdateMealRequest;
use App\Models\Meal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Meal::query()->withCount([
            'tickets',
            'tickets as redeemed_tickets_count' => fn ($q) => $q->where('status', 'redeemed'),
            'tickets as unused_tickets_count' => fn ($q) => $q->where('status', 'unused'),
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('mealDate')) {
            $query->whereDate('mealDate', $request->mealDate);
        }

        $meals = $query
            ->latest('mealDate')
            ->latest('mealId')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $meals,
        ]);
    }

    public function store(StoreMealRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $meal = Meal::create([
            'title' => $request->title,
            'slug' => $this->generateUniqueSlug($request->title),
            'description' => $request->description,
            'mealDate' => $request->mealDate,
            'startTime' => $request->startTime,
            'endTime' => $request->endTime,
            'location' => $request->location,
            'status' => 'draft',
            'ticketCount' => $request->ticketCount,
            'redeemedCount' => 0,
            'createdBy' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal created successfully.',
            'data' => $meal,
        ], 201);
    }

    public function show(Meal $meal): JsonResponse
    {
        $meal->loadCount([
            'tickets',
            'tickets as redeemed_tickets_count' => fn ($q) => $q->where('status', 'redeemed'),
            'tickets as unused_tickets_count' => fn ($q) => $q->where('status', 'unused'),
            'tickets as void_tickets_count' => fn ($q) => $q->where('status', 'void'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $meal,
        ]);
    }

    public function update(UpdateMealRequest $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
        $data = $request->validated();

        if (isset($data['title']) && $data['title'] !== $meal->title) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $meal->id);
        }

        $meal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Meal updated successfully.',
            'data' => $meal->fresh(),
        ]);
    }

    public function destroy(Request $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($meal->tickets()->where('status', 'redeemed')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a meal with redeemed tickets.',
            ], 422);
        }

        $meal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meal deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, Meal $meal): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,closed,cancelled'],
        ]);

        $meal->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal status updated successfully.',
            'data' => $meal->fresh(),
        ]);
    }

    private function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (
            Meal::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}