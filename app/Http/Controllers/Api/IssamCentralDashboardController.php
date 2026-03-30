<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IssamCentralDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // keep your existing dashboard method here
        return response()->json([]);
    }

    public function detail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'string'], // overview | room-metric | incident | supervisor
            'title' => ['nullable', 'string'],
            'metric' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'supervisorId' => ['nullable', 'integer'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $type = $validated['type'];
        $title = $validated['title'] ?? null;
        $metric = $validated['metric'] ?? null;
        $category = $validated['category'] ?? null;
        $supervisorId = $validated['supervisorId'] ?? null;

        try {
            return match ($type) {
                'overview' => $this->handleOverviewDetail($date, $title),
                'room-metric' => $this->handleRoomMetricDetail($date, $metric),
                'incident' => $this->handleIncidentDetail($date, $category),
                'supervisor' => $this->handleSupervisorDetail($date, $supervisorId),
                default => response()->json([
                    'message' => 'Unsupported detail type supplied.',
                ], 422),
            };
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: 'Unable to load dashboard detail.',
            ], 500);
        }
    }

  protected function handleOverviewDetail(string $date, ?string $title): JsonResponse
{
    $normalized = strtolower(trim((string) $title));

    return match ($normalized) {
        'total participants' => $this->totalParticipantsDetail($date),

        'present today',
        'present for date',
        'present participants' => $this->presentParticipantsDetail($date),

        'absent today',
        'absent for date',
        'absent participants' => $this->absentParticipantsDetail($date),

        'attendance %',
        'attendance percentage',
        'attendance for date' => $this->attendancePercentageDetail($date),

        'late arrivals',
        'late arrivals for date' => $this->lateArrivalsDetail($date),

        'incidents today',
        'incidents for date' => $this->incidentsTodayDetail($date),

        'open incidents',
        'open incidents for date' => $this->openIncidentsDetail($date),

        'rooms checked',
        'rooms checked for date' => $this->roomsCheckedDetail($date),

        'meals served',
        'meals served for date' => $this->mealsServedDetail($date),

        'meals (unique)',
        'unique meals served',
        'meals unique' => $this->uniqueMealsServedDetail($date),

        default => response()->json([
            'message' => "No detail handler configured for overview title: {$title}",
        ], 422),
    };
}

    protected function handleRoomMetricDetail(string $date, ?string $metric): JsonResponse
    {
        $normalized = strtolower(trim((string) $metric));

        if (str_contains($normalized, 'assigned')) {
            return $this->roomAssignedDetail($date);
        }

        if (str_contains($normalized, 'checked')) {
            return $this->roomsCheckedDetail($date);
        }

        if (str_contains($normalized, 'ack')) {
            return $this->roomAcknowledgementDetail($date);
        }

        if (str_contains($normalized, 'key')) {
            return $this->roomKeyIssuedDetail($date);
        }

        if (str_contains($normalized, 'issue') || str_contains($normalized, 'flag')) {
            return $this->roomIssuesDetail($date);
        }

        return response()->json([
            'message' => "No detail handler configured for room metric: {$metric}",
        ], 422);
    }

    protected function handleIncidentDetail(string $date, ?string $category): JsonResponse
    {
        $rows = DB::table('incident_reports as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->leftJoin('users as u', 'u.id', '=', 'ir.reportedBy')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.severity',
                'ir.status',
                'ir.description',
                'ir.incidentDate',
                DB::raw("COALESCE(a.fullName, '-') as participantName"),
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as reportedBy")
            )
            ->whereDate('ir.incidentDate', $date)
            ->when($category, fn ($q) => $q->where('ir.category', $category))
            ->orderByDesc('ir.incidentId')
            ->get()
            ->map(fn ($row) => [
                'incidentId' => $row->incidentId,
                'category' => $row->category,
                'severity' => $row->severity,
                'status' => $row->status,
                'description' => $row->description,
                'incidentDate' => $row->incidentDate,
                'participantName' => $row->participantName,
                'reportedBy' => $row->reportedBy,
            ])
            ->values();

        return response()->json([
            'title' => $category ? "Incident Detail - {$category}" : 'Incident Detail',
            'date' => $date,
            'summary' => [
                'total' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'reportedBy', 'label' => 'Reported By'],
                ['key' => 'incidentDate', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function handleSupervisorDetail(string $date, ?int $supervisorId): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'da.attendanceDate',
                'a.attendeeId',
                'a.fullName',
                'a.phone',
                DB::raw("CONCAT(u.firstName, ' ', u.lastName) as supervisorName")
            )
            ->whereDate('da.attendanceDate', $date)
            ->when($supervisorId, fn ($q) => $q->where('u.id', $supervisorId))
            ->orderByDesc('da.attendanceId')
            ->get()
            ->map(fn ($row) => [
                'attendanceId' => $row->attendanceId,
                'attendanceDate' => $row->attendanceDate,
                'attendeeId' => $row->attendeeId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'supervisorName' => $row->supervisorName,
            ])
            ->values();

        return response()->json([
            'title' => 'Sub-CL Attendance Detail',
            'date' => $date,
            'summary' => [
                'totalScans' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'attendanceId', 'label' => 'Scan ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Participant Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'supervisorName', 'label' => 'Sub-CL'],
                ['key' => 'attendanceDate', 'label' => 'Date'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function totalParticipantsDetail(string $date): JsonResponse
    {
        $rows = DB::table('attendees as a')
        ->join('event_passes as ep', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                 DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                'a.gender',
                'ep.serialNumber',
                'a.photoUrl'
            )
            ->where('a.isRegistered', 1)
            ->orderBy('a.fullName')
            ->get();

        return response()->json([
            'title' => 'Total Participants',
            'date' => $date,
            'summary' => [
                'totalParticipants' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'photoUrl', 'label' => 'Passport'],
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function presentParticipantsDetail(string $date): JsonResponse
    {
        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.passId', '=', 'da.eventPassId')
            ->leftJoin('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'a.uniqueId',
                 DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                'a.gender',
                'a.photoUrl',
                'ep.serialNumber',
                'da.attendanceDate',
                DB::raw('UPPER(a.lga) as lga'),
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as markedBy")
            )
            ->whereDate('da.attendanceDate', $date)
            ->orderBy('a.fullName')
            ->get()
            // ->unique('attendeeId')
            ->values()
            ->map(fn ($row) => [
                'attendanceId' => $row->attendanceId,
                'attendeeId' => $row->uniqueId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'gender' => $row->gender,
                'serialNumber' => $row->serialNumber,
                'photoUrl' => $row->photoUrl,
                'attendanceDate' => $row->attendanceDate,
                // 'markedBy' => $row->markedBy,
                'lga' => $row->lga,
            ]);

        return response()->json([
            'title' => 'Present Participants',
            'date' => $date,
            'summary' => [
                'presentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
                ['key' => 'lga', 'label' => 'LGA'],
                // ['key' => 'markedBy', 'label' => 'Marked By'],
                ['key' => 'attendanceDate', 'label' => 'Attendance Date'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function absentParticipantsDetail(string $date): JsonResponse
    {
        $presentIds = DB::table('daily_attendances')
            ->whereDate('attendanceDate', $date)
            ->pluck('attendeeId')
            ->unique()
            ->filter()
            ->values();

        $rows = DB::table('attendees as a')
        ->leftJoin('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                'a.gender',
                'a.photoUrl',
                'ep.serialNumber',
                DB::raw('UPPER(a.lga) as lga'),
            )
            ->when($presentIds->isNotEmpty(), fn ($q) => $q->whereNotIn('a.attendeeId', $presentIds))
            ->where('isRegistered', 1)
            ->orderBy('fullName')
            ->get();

        return response()->json([
            'title' => 'Absent Participants',
            'date' => $date,
            'summary' => [
                'absentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'gender', 'label' => 'Gender'],
                ['key' => 'serialNumber', 'label' => 'Serial Number'],
                 ['key' => 'lga', 'label' => 'LGA'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function attendancePercentageDetail(string $date): JsonResponse
    {
        $totalParticipants = DB::table('attendees')->where('isRegistered', 1)->count();

        $presentCount = DB::table('daily_attendances')
            ->whereDate('attendanceDate', $date)
            ->distinct('attendeeId')
            ->count('attendeeId');

        $percentage = $totalParticipants > 0
            ? round(($presentCount / $totalParticipants) * 100, 2)
            : 0;

        return response()->json([
            'title' => 'Attendance Percentage',
            'date' => $date,
            'summary' => [
                'totalParticipants' => $totalParticipants,
                'presentCount' => $presentCount,
                'attendancePercentage' => $percentage,
            ],
            'columns' => [],
            'rows' => [],
        ]);
    }

    protected function lateArrivalsDetail(string $date): JsonResponse
    {
        // Assumes daily_attendances has attendanceTime column
        // Change this threshold as needed
        $cutoff = '09:00:00';

        $rows = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->leftJoin('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'da.attendanceId',
                'a.attendeeId',
                'a.fullName',
                'a.phone',
                'da.attendanceDate',
                'da.attendanceTime',
                DB::raw("COALESCE(CONCAT(u.firstName, ' ', u.lastName), '-') as markedBy")
            )
            ->whereDate('da.attendanceDate', $date)
            ->whereTime('da.attendanceTime', '>', $cutoff)
            ->orderBy('da.attendanceTime')
            ->get();

        return response()->json([
            'title' => 'Late Arrivals',
            'date' => $date,
            'summary' => [
                'lateCount' => $rows->count(),
                'cutoffTime' => $cutoff,
            ],
            'columns' => [
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'attendanceDate', 'label' => 'Date'],
                ['key' => 'attendanceTime', 'label' => 'Time'],
                ['key' => 'markedBy', 'label' => 'Marked By'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function incidentsTodayDetail(string $date): JsonResponse
    {
        $rows = DB::table('incident_reports as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.status',
                'ir.severity',
                'ir.description',
                'ir.incidentDate',
                DB::raw("COALESCE(a.fullName, '-') as participantName")
            )
            ->whereDate('ir.incidentDate', $date)
            ->orderByDesc('ir.incidentId')
            ->get();

        return response()->json([
            'title' => 'Incidents Today',
            'date' => $date,
            'summary' => [
                'incidentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'incidentDate', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function openIncidentsDetail(string $date): JsonResponse
    {
        $rows = DB::table('incident_reports as ir')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ir.attendeeId')
            ->select(
                'ir.incidentId',
                'ir.category',
                'ir.status',
                'ir.severity',
                'ir.description',
                'ir.incidentDate',
                DB::raw("COALESCE(a.fullName, '-') as participantName")
            )
            ->where('ir.status', 'open')
            ->whereDate('ir.incidentDate', '<=', $date)
            ->orderByDesc('ir.incidentId')
            ->get();

        return response()->json([
            'title' => 'Open Incidents',
            'date' => $date,
            'summary' => [
                'openIncidentCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'incidentId', 'label' => 'Incident ID'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'incidentDate', 'label' => 'Date'],
                ['key' => 'description', 'label' => 'Description'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function roomsCheckedDetail(string $date): JsonResponse
    {
        // Assumes room_allocations has checkedInAt or roomCheckedAt
        $rows = DB::table('room_allocations as ra')
            ->join('attendees as a', 'a.attendeeId', '=', 'ra.attendeeId')
            // ->leftJoin('rooms as r', 'r.roomId', '=', 'ra.roomId')
            ->select(
                'ra.allocationId',
                'a.uniqueId',
                DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                // DB::raw("COALESCE(r.roomNumber, r.name, '-') as roomName"),
                'a.photoUrl',
                'ra.roomNumber',
                'ra.created_at'
            )
            ->whereDate('ra.created_at', $date)
            ->orderByDesc('ra.allocationId')
            ->get()
            ->map(fn ($row) => [
                'allocationId' => $row->allocationId,
                'uniqueId' => $row->uniqueId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'roomNumber' => $row->roomNumber,
                'photoUrl' => $row->photoUrl,
                'allocatedAt' => $row->created_at,
            ]);

        return response()->json([
            'title' => 'Rooms Checked',
            'date' => $date,
            'summary' => [
                'roomsCheckedCount' => $rows->count(),
            ],
            'columns' => [
                // ['key' => 'allocationId', 'label' => 'Allocation ID'],
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'roomNumber', 'label' => 'Room'],
                ['key' => 'allocatedAt', 'label' => 'Checked/Allocated At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function roomAssignedDetail(string $date): JsonResponse
    {
        $rows = DB::table('room_allocations as ra')
            ->join('attendees as a', 'a.attendeeId', '=', 'ra.attendeeId')
            // ->leftJoin('rooms as r', 'r.roomId', '=', 'ra.roomId')
            ->select(
                'ra.allocationId',
                'a.uniqueId',
                DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                'a.photoUrl',
                'ra.roomNumber',
                'ra.created_at'
            )
            // ->whereDate('ra.created_at', $date)
            ->orderBy('a.fullName')
            ->get()
            ->map(fn ($row) => [
                'allocationId' => $row->allocationId,
                'uniqueId' => $row->uniqueId,
                'fullName' => $row->fullName,
                'phone' => $row->phone,
                'photoUrl' => $row->photoUrl,
                'roomNumber' => $row->roomNumber,
                'assignedAt' => $row->created_at,
            ]);

        return response()->json([
            'title' => 'Assigned Rooms',
            'date' => $date,
            'summary' => [
                'assignedCount' => $rows->count(),
            ],
            'columns' => [
                // ['key' => 'allocationId', 'label' => 'Allocation ID'],
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'roomNumber', 'label' => 'Room'],
                ['key' => 'assignedAt', 'label' => 'Assigned At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function roomAcknowledgementDetail(string $date): JsonResponse
    {
        // Assumes room_allocations has acknowledgedAt or acknowledgement flag
        $rows = DB::table('room_allocations as ra')
            ->join('attendees as a', 'a.attendeeId', '=', 'ra.attendeeId')
            ->leftJoin('rooms as r', 'r.roomId', '=', 'ra.roomId')
            ->select(
                'ra.roomAllocationId',
                'a.attendeeId',
                'a.fullName',
                DB::raw("COALESCE(r.roomNumber, r.name, '-') as roomName"),
                'ra.acknowledgedAt'
            )
            ->whereNotNull('ra.acknowledgedAt')
            ->whereDate('ra.acknowledgedAt', $date)
            ->orderByDesc('ra.roomAllocationId')
            ->get();

        return response()->json([
            'title' => 'Room Acknowledgements',
            'date' => $date,
            'summary' => [
                'acknowledgedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'roomAllocationId', 'label' => 'Allocation ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'roomName', 'label' => 'Room'],
                ['key' => 'acknowledgedAt', 'label' => 'Acknowledged At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function roomKeyIssuedDetail(string $date): JsonResponse
    {
        // Assumes room_allocations has keyIssuedAt or keyIssued flag
        $rows = DB::table('room_allocations as ra')
            ->join('attendees as a', 'a.attendeeId', '=', 'ra.attendeeId')
            ->leftJoin('rooms as r', 'r.roomId', '=', 'ra.roomId')
            ->select(
                'ra.roomAllocationId',
                'a.attendeeId',
                'a.fullName',
                DB::raw("COALESCE(r.roomNumber, r.name, '-') as roomName"),
                'ra.keyIssuedAt'
            )
            ->whereNotNull('ra.keyIssuedAt')
            ->whereDate('ra.keyIssuedAt', $date)
            ->orderByDesc('ra.roomAllocationId')
            ->get();

        return response()->json([
            'title' => 'Room Keys Issued',
            'date' => $date,
            'summary' => [
                'keyIssuedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'roomAllocationId', 'label' => 'Allocation ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'roomName', 'label' => 'Room'],
                ['key' => 'keyIssuedAt', 'label' => 'Key Issued At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function roomIssuesDetail(string $date): JsonResponse
    {
        // Assumes room_issues table exists
        $rows = DB::table('room_issues as ri')
            ->leftJoin('rooms as r', 'r.roomId', '=', 'ri.roomId')
            ->leftJoin('attendees as a', 'a.attendeeId', '=', 'ri.attendeeId')
            ->select(
                'ri.roomIssueId',
                DB::raw("COALESCE(r.roomNumber, r.name, '-') as roomName"),
                DB::raw("COALESCE(a.fullName, '-') as participantName"),
                'ri.issueType',
                'ri.status',
                'ri.description',
                'ri.created_at'
            )
            ->whereDate('ri.created_at', $date)
            ->orderByDesc('ri.roomIssueId')
            ->get();

        return response()->json([
            'title' => 'Room Issues',
            'date' => $date,
            'summary' => [
                'issueCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'roomIssueId', 'label' => 'Issue ID'],
                ['key' => 'roomName', 'label' => 'Room'],
                ['key' => 'participantName', 'label' => 'Participant'],
                ['key' => 'issueType', 'label' => 'Issue Type'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'created_at', 'label' => 'Created At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function mealsServedDetail(string $date): JsonResponse
    {
        // Assumes meal_redemptions table exists
        $rows = DB::table('meal_redemptions as mr')
            ->join('attendees as a', 'a.attendeeId', '=', 'mr.attendeeId')
            ->leftJoin('meals as m', 'm.mealId', '=', 'mr.mealId')
            ->select(
                'mr.redemptionId',
                'a.attendeeId',
                'a.fullName',
                'a.phone',
                DB::raw("COALESCE(m.title, '-') as mealTitle"),
                'mr.redeemedAt'
            )
            ->whereDate('mr.redeemedAt', $date)
            ->orderByDesc('mr.redemptionId')
            ->get();

        return response()->json([
            'title' => 'Meals Served',
            'date' => $date,
            'summary' => [
                'mealServedCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'redemptionId', 'label' => 'Redemption ID'],
                ['key' => 'attendeeId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'mealTitle', 'label' => 'Meal'],
                ['key' => 'redeemedAt', 'label' => 'Served At'],
            ],
            'rows' => $rows,
        ]);
    }

    protected function uniqueMealsServedDetail(string $date): JsonResponse
    {
        $rows = DB::table('meal_redemptions as mr')
        ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
        ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->select(
                'a.uniqueId',
                DB::raw('UPPER(a.fullName) as fullName'),
                'a.phone',
                DB::raw('COUNT(mr.redemptionId) as mealCount')
            )
            ->whereDate('mr.redeemedAt', $date)
            ->groupBy('a.attendeeId', 'a.fullName', 'a.phone')
            ->orderByDesc('mealCount')
            ->get();

        return response()->json([
            'title' => 'Meals (Unique)',
            'date' => $date,
            'summary' => [
                'uniqueParticipantCount' => $rows->count(),
            ],
            'columns' => [
                ['key' => 'uniqueId', 'label' => 'Participant ID'],
                ['key' => 'fullName', 'label' => 'Full Name'],
                ['key' => 'phone', 'label' => 'Phone Number'],
                ['key' => 'mealCount', 'label' => 'Meals Collected'],
            ],
            'rows' => $rows,
        ]);
    }
}