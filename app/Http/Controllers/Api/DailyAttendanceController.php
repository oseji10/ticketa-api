<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Event;
use App\Services\DailyAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyAttendanceController extends Controller
{
    public function __construct(
        protected DailyAttendanceService $dailyAttendanceService
    ) {
    }

    protected function ensureScannerAccess(): void
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, ['admin', 'scanner', 'supervisor'])) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin, scanner, or supervisor can mark attendance.',
            ], 403));
        }
    }

    public function scan(Request $request, Event $event): JsonResponse
    {
        $this->ensureScannerAccess();

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:255'],
            'scanSource' => ['nullable', 'string', 'max:50'],
            'attendanceDate' => ['nullable', 'date'],
        ]);

        $result = $this->dailyAttendanceService->markAttendance(
            event: $event,
            token: $validated['token'],
            deviceName: $validated['deviceName'] ?? null,
            scanSource: $validated['scanSource'] ?? 'barcode',
            attendanceDate: $validated['attendanceDate'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function index(Request $request, Event $event): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $search = trim((string) $request->query('search', ''));

        $query = DailyAttendance::with(['attendee', 'pass', 'marker'])
            ->where('eventId', $event->eventId)
            ->whereDate('attendanceDate', $date);

        if ($search !== '') {
            $query->whereHas('attendee', function ($q) use ($search) {
                $q->where('phone', $search)
                    ->orWhere('uniqueId', $search)
                    ->orWhere('uniqueId', 'LIKE', "%{$search}")
                    ->orWhereRaw("CONCAT(COALESCE(firstName, ''), ' ', COALESCE(lastName, '')) LIKE ?", ["%{$search}%"]);
            });
        }

        $records = $query->latest('attendanceId')->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Attendance records retrieved successfully.',
            'data' => $records,
        ]);
    }

    public function summary(Request $request, Event $event): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $presentCount = DailyAttendance::where('eventId', $event->eventId)
            ->whereDate('attendanceDate', $date)
            ->count();

        $registeredCount = $event->passes()
            ->whereNotNull('attendeeId')
            ->count();

        $absentCount = max($registeredCount - $presentCount, 0);

        return response()->json([
            'success' => true,
            'message' => 'Attendance summary retrieved successfully.',
            'data' => [
                'eventId' => $event->eventId,
                'attendanceDate' => $date,
                'registeredCount' => $registeredCount,
                'presentCount' => $presentCount,
                'absentCount' => $absentCount,
            ],
        ]);
    }
}