<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\AttendanceWindowService;
use App\Services\DailyAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DailyAttendanceController extends Controller
{
    public function __construct(
        protected DailyAttendanceService $dailyAttendanceService,
        protected AttendanceWindowService $attendanceWindowService
    ) {
    }

    protected function ensureScannerAccess(): void
    {
        $user = Auth::user();

        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403));
        }
        
    }


    // public function scan(Request $request, Event $event): JsonResponse
    // {
    //     $this->ensureScannerAccess();

    //     $validated = $request->validate([
    //         // 'token' => ['required', 'string'],
    //         'deviceName' => ['nullable', 'string', 'max:255'],
    //         'scanSource' => ['nullable', 'string', 'max:50'],
    //         'attendanceDate' => ['nullable', 'date'],
    //     ]);

    //     $result = $this->dailyAttendanceService->markAttendance(
    //         event: $event,
    //         token: $validated['token'],
    //         deviceName: $validated['deviceName'] ?? null,
    //         scanSource: $validated['scanSource'] ?? 'barcode',
    //         attendanceDate: $validated['attendanceDate'] ?? null,
    //     );

    //     return response()->json($result, $result['success'] ? 200 : 422);
    // }


    //   public function scan(Request $request, Event $event): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'token' => ['required', 'string'],
    //         'deviceName' => ['nullable', 'string', 'max:255'],
    //         'scanSource' => ['nullable', 'string', 'max:50'],
    //         'attendanceDate' => ['required', 'date'],
    //     ]);

    //     $attendanceDate = Carbon::parse($validated['attendanceDate'])->toDateString();

    //     $this->attendanceWindowService->ensureAttendanceOpen($event, $attendanceDate);

    //     $pass = EventPass::with('attendee')
    //         ->where('eventId', $event->eventId)
    //         ->where(function ($query) use ($validated) {
    //             $query->where('passCode', $validated['token'])
    //                 ->orWhere('serialNumber', $validated['token']);
    //         })
    //         ->first();

    //     if (!$pass || !$pass->attendee) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invalid pass code.',
    //             'data' => [
    //                 'status' => 'invalid_pass',
    //                 'attendanceDate' => $attendanceDate,
    //             ],
    //         ], 404);
    //     }

    //     $existing = DailyAttendance::with(['attendee', 'pass'])
    //         ->where('eventId', $event->eventId)
    //         ->where('attendeeId', $pass->attendeeId)
    //         ->whereDate('attendanceDate', $attendanceDate)
    //         ->first();

    //     if ($existing) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Attendance has already been marked for this attendee today.',
    //             'data' => [
    //                 'status' => 'already_marked',
    //                 'attendanceId' => $existing->attendanceId,
    //                 'attendanceDate' => $existing->attendanceDate,
    //                 'markedAt' => optional($existing->created_at)?->toDateTimeString(),
    //                 'attendee' => [
    //                     'attendeeId' => $pass->attendee->attendeeId,
    //                     'name' => trim(($pass->attendee->firstName ?? '') . ' ' . ($pass->attendee->lastName ?? '')),
    //                     'uniqueId' => $pass->attendee->uniqueId,
    //                     'phone' => $pass->attendee->phone,
    //                     'passportUrl' => $pass->attendee->passportUrl ?? null,
    //                 ],
    //             ],
    //         ], 409);
    //     }

    //     $attendance = DB::transaction(function () use ($event, $pass, $validated, $attendanceDate) {
    //         return DailyAttendance::create([
    //             'eventId' => $event->eventId,
    //             'attendeeId' => $pass->attendeeId,
    //             'passId' => $pass->passId,
    //             'attendanceDate' => $attendanceDate,
    //             'deviceName' => $validated['deviceName'] ?? null,
    //             'scanSource' => $validated['scanSource'] ?? 'barcode',
    //             'markedBy' => auth()->id(),
    //         ]);
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Attendance marked successfully.',
    //         'data' => [
    //             'status' => 'marked',
    //             'attendanceId' => $attendance->attendanceId,
    //             'attendanceDate' => $attendance->attendanceDate,
    //             'markedAt' => optional($attendance->created_at)?->toDateTimeString(),
    //             'attendee' => [
    //                 'attendeeId' => $pass->attendee->attendeeId,
    //                 'name' => trim(($pass->attendee->firstName ?? '') . ' ' . ($pass->attendee->lastName ?? '')),
    //                 'uniqueId' => $pass->attendee->uniqueId,
    //                 'phone' => $pass->attendee->phone,
    //                 'gender' => $pass->attendee->gender,
    //                 'passportUrl' => $pass->attendee->passportUrl ?? null,
    //             ],
    //             'pass' => [
    //                 'passId' => $pass->passId,
    //                 'serialNumber' => $pass->serialNumber,
    //             ],
    //         ],
    //     ]);
    // }


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

    // public function summary(Request $request, Event $event): JsonResponse
    // {
    //     $date = $request->query('date', now()->toDateString());

    //     $presentCount = DailyAttendance::where('eventId', $event->eventId)
    //         ->whereDate('attendanceDate', $date)
    //         ->count();

    //     $registeredCount = $event->passes()
    //         ->whereNotNull('attendeeId')
    //         ->count();

    //     $absentCount = max($registeredCount - $presentCount, 0);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Attendance summary retrieved successfully.',
    //         'data' => [
    //             'eventId' => $event->eventId,
    //             'attendanceDate' => $date,
    //             'registeredCount' => $registeredCount,
    //             'presentCount' => $presentCount,
    //             'absentCount' => $absentCount,
    //         ],
    //     ]);
    // }


    public function summary(Request $request, Event $event): JsonResponse
{
    $validated = $request->validate([
        'date' => ['nullable', 'date'],
    ]);

    $attendanceDate = $validated['date'] ?? now()->toDateString();

    $registeredCount = $event->attendees()->count();

    $presentCount = DailyAttendance::where('eventId', $event->eventId)
        ->whereDate('attendanceDate', $attendanceDate)
        ->count();

    $absentCount = max($registeredCount - $presentCount, 0);

    $window = $this->attendanceWindowService->getStatus($event, $attendanceDate);

    return response()->json([
        'success' => true,
        'message' => 'Attendance summary fetched successfully.',
        'data' => [
            'eventId' => $event->eventId,
            'attendanceDate' => $attendanceDate,
            'registeredCount' => $registeredCount,
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'attendanceLock' => $window,
        ],
    ]);
}


public function config(Request $request, Event $event): JsonResponse
{
    $validated = $request->validate([
        'date' => ['nullable', 'date'],
    ]);

    $attendanceDate = $validated['date'] ?? now()->toDateString();
    $window = $this->attendanceWindowService->getStatus($event, $attendanceDate);

    return response()->json([
        'success' => true,
        'message' => 'Attendance config fetched successfully.',
        'data' => [
            'attendanceDate' => $attendanceDate,
            'attendanceLock' => $window,
        ],
    ]);
}
}