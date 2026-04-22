<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function issamCentral(Request $request): JsonResponse
    {
        // ── Resolve active event ──────────────────────────────────────────────
        $activeEvent = DB::table('events')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'data'    => null,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Scoped user filter ────────────────────────────────────────────────
        // Look up the logged-in user's record in sub_cls via userId.
        // If found, restrict all attendee-linked stats to attendees sharing
        // that subClId. If no sub_cls record exists, show all data.
        $subCl = DB::table('sub_cls')
            ->where('userId', auth()->id())
            ->first();

        $isScoped         = !is_null($subCl);
        $scopedAttendeeIds = $isScoped
            ? DB::table('attendees')
                ->where('subClId', $subCl->subClId)
                ->where('isRegistered', 1)
                ->pluck('attendeeId')
            : collect();

        // ── Date & period ─────────────────────────────────────────────────────
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $selectedDateString = $selectedDate->toDateString();

        $programme = $activeEvent->title    ?? 'ISSAM Residential Training';
        $venue     = $activeEvent->location ?? 'ABU Zaria';

        $periodStart = Carbon::parse($activeEvent->startDate ?? $request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd   = Carbon::parse($activeEvent->endDate   ?? $request->query('periodEnd',   '2026-03-30'))->toDateString();

        // ── Core counts (scoped to active event) ─────────────────────────────

        $totalParticipants = DB::table('attendees as a')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->where('a.isRegistered', 1)
            ->when($isScoped, fn ($q) => $q->whereIn('a.attendeeId', $scopedAttendeeIds))
            ->distinct()
            ->count('a.attendeeId');

        $presentForDate = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->when($isScoped, fn ($q) => $q->whereIn('da.attendeeId', $scopedAttendeeIds))
            ->distinct()
            ->count('da.attendeeId');

        $absentForDate     = max($totalParticipants - $presentForDate, 0);
        $attendancePercent = $totalParticipants > 0
            ? round(($presentForDate / $totalParticipants) * 100, 1)
            : 0;

        // Incidents are not attendee-linked — always event-wide
        $incidentsForDate = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereDate('reportedAt', $selectedDateString)
            ->count();

        $openIncidents = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $roomsCheckedForDate = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->whereDate('allocatedAt', $selectedDateString)
            ->whereNotNull('allocatedAt')
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->count();

        $mealsServedForDate = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('mr.created_at', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->when($isScoped, fn ($q) => $q->whereIn('ep.attendeeId', $scopedAttendeeIds))
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

        // Gender split — present on selected date
        $genderSplit = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->when($isScoped, fn ($q) => $q->whereIn('da.attendeeId', $scopedAttendeeIds))
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm')   THEN 'male'
                        WHEN TRIM(LOWER(a.gender)) IN ('female', 'f') THEN 'female'
                        ELSE 'other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT da.attendeeId) as count')
            )
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Registered gender split — all accredited attendees
        $registeredGenderSplit = DB::table('attendees as a')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->where('a.isRegistered', 1)
            ->when($isScoped, fn ($q) => $q->whereIn('a.attendeeId', $scopedAttendeeIds))
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm')   THEN 'male'
                        WHEN TRIM(LOWER(a.gender)) IN ('female', 'f') THEN 'female'
                        ELSE 'other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT a.attendeeId) as count')
            )
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // ── Overview stats ────────────────────────────────────────────────────
        $overviewStats = [
            ['title' => 'Total Accredited Participants', 'value' => (string) $totalParticipants],

            [
                'title'            => 'Accredited Male',
                'value'            => (string) ($registeredGenderSplit['male'] ?? 0),
                'note'             => 'Registered attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-black-100 dark:bg-black-800/40',
                'iconClassName'    => 'w-5 h-5 text-black-700 dark:text-black-100',
            ],
            [
                'title'            => 'Accredited Female',
                'value'            => (string) ($registeredGenderSplit['female'] ?? 0),
                'note'             => 'Registered attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
                'iconClassName'    => 'w-5 h-5 text-pink-700 dark:text-pink-100',
            ],

            ['title' => 'Total Present for Selected Date', 'value' => (string) $presentForDate],
            ['title' => 'Total Absent for Selected Date',  'value' => (string) $absentForDate],
            ['title' => 'Attendance %',                    'value' => number_format($attendancePercent, 1) . '%'],

            [
                'title'            => 'Males Present',
                'value'            => (string) ($genderSplit['male'] ?? 0),
                'note'             => 'Present attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-blue-100 dark:bg-blue-800/40',
                'iconClassName'    => 'w-5 h-5 text-blue-700 dark:text-blue-100',
            ],
            [
                'title'            => 'Females Present',
                'value'            => (string) ($genderSplit['female'] ?? 0),
                'note'             => 'Present attendees',
                'iconKey'          => 'users',
                'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
                'iconClassName'    => 'w-5 h-5 text-pink-700 dark:text-pink-100',
            ],

            ['title' => 'Rooms Checked for Date', 'value' => (string) $roomsCheckedForDate],
            ['title' => 'Incidents for Date',      'value' => (string) $incidentsForDate],
            ['title' => 'Open Incidents',           'value' => (string) $openIncidents],
            ['title' => 'Meals (Unique)',           'value' => (string) $mealsServedForDate],
        ];

        // ── Sub-sections ──────────────────────────────────────────────────────
        $supervisorRows   = $this->buildSupervisorRows($selectedDateString, $eventId, $isScoped, $scopedAttendeeIds->all());
        $incidentSnapshot = $this->buildIncidentSnapshot($selectedDateString, $eventId);
        $roomMetrics      = $this->buildRoomMetrics($selectedDateString, $eventId, $isScoped, $scopedAttendeeIds->all());
        $coordinatorNotes = $this->buildCoordinatorNotes($supervisorRows, $openIncidents, $selectedDateString);

        return response()->json([
            'data' => [
                'dashboardDate'    => $selectedDate->format('d M Y'),
                'dayName'          => $selectedDate->format('l'),
                'programme'        => $programme,
                'venue'            => $venue,
                'period'           => Carbon::parse($periodStart)->format('d-M-Y') . ' to ' . Carbon::parse($periodEnd)->format('d-M-Y'),
                'scopedToUser'     => $isScoped,
                'overviewStats'    => $overviewStats,
                'supervisorRows'   => $supervisorRows,
                'incidentSnapshot' => $incidentSnapshot,
                'roomMetrics'      => $roomMetrics,
                'coordinatorNotes' => $coordinatorNotes,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function buildSupervisorRows(
        string $selectedDate,
        int    $eventId,
        bool   $isScoped   = false,
        array  $scopedAttendeeIds = []
    ): array {
        // Get all Sub-CL supervisors (users who have a sub_cls record)
        $allSupervisors = DB::table('sub_cls as sc')
            ->join('users as u', 'u.id', '=', 'sc.userId')
            ->select(
                'u.id as supervisorId',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))) as supervisorName"),
                'sc.subClId'
            )
            ->get()
            ->keyBy('supervisorId');

        // Get total participants assigned to each Sub-CL (from attendees table directly)
        $participantCounts = DB::table('attendees')
            ->where('isRegistered', 1)
            ->whereNotNull('subClId')
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->select(
                'subClId',
                DB::raw('COUNT(*) as totalParticipants')
            )
            ->groupBy('subClId')
            ->pluck('totalParticipants', 'subClId');

        // Get attendance counts for each supervisor on the selected date
        $attendanceCounts = DB::table('daily_attendances as da')
            ->join('users as u', 'u.id', '=', 'da.markedBy')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDate)
            // ->when($isScoped, fn ($q) => $q->whereIn('da.attendeeId', $scopedAttendeeIds))
            ->select(
                'u.id as supervisorId',
                DB::raw('COUNT(DISTINCT da.attendeeId) as attendanceCount')
            )
            ->groupBy('u.id')
            ->pluck('attendanceCount', 'supervisorId');

        // Combine all supervisors with their attendance counts and percentages
        return $allSupervisors->map(function ($supervisor) use ($attendanceCounts, $participantCounts) {
            $scannedCount = (int) ($attendanceCounts[$supervisor->supervisorId] ?? 0);
            $totalAssigned = (int) ($participantCounts[$supervisor->subClId] ?? 0);
            
            // Calculate percentage (avoid division by zero)
            $percentage = $totalAssigned > 0 
                ? round(($scannedCount / $totalAssigned) * 100, 1)
                : 0;
            
            return [
                'supervisorId'      => $supervisor->supervisorId,
                'supervisorName'    => $supervisor->supervisorName,
                'subClId'           => $supervisor->subClId,
                'attendanceCount'   => $scannedCount,
                'totalAssigned'     => $totalAssigned,
                'attendancePercent' => $percentage,
                'status'            => $this->resolveSupervisorStatus($percentage, $scannedCount),
            ];
        })
        ->sortByDesc('attendancePercent')
        ->values()
        ->all();
    }

    protected function resolveSupervisorStatus(float $percentage, int $count): string
    {
        // Percentage-based rating for fairness across different Sub-CL sizes
        // But also require minimum scan count to avoid false positives
        
        if ($percentage >= 80 && $count >= 1) return 'High Activity';
        if ($percentage >= 50 && $count >= 1) return 'Active';
        return 'Low Activity';
    }

    protected function buildIncidentSnapshot(string $selectedDate, int $eventId): array
    {
        // Incidents are not attendee-linked — always event-wide
        return DB::table('incidents')
            ->select('category', DB::raw('COUNT(*) as total'))
            ->where('eventId', $eventId)
            ->whereDate('reportedAt', $selectedDate)
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count'    => (int) $row->total,
            ])
            ->toArray();
    }

    protected function buildRoomMetrics(
        string $selectedDate,
        int    $eventId,
        bool   $isScoped         = false,
        array  $scopedAttendeeIds = []
    ): array {
        $assigned = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->count();

        $checkedInForDate = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->whereNotNull('allocatedAt')
            ->whereDate('allocatedAt', $selectedDate)
            ->when($isScoped, fn ($q) => $q->whereIn('attendeeId', $scopedAttendeeIds))
            ->count();

        return [
            ['metric' => 'Assigned (All)',      'value' => $assigned],
            ['metric' => 'Checked In for Date', 'value' => $checkedInForDate],
        ];
    }

    protected function buildCoordinatorNotes(array $supervisorRows, int $openIncidents, string $selectedDate): array
    {
        $notes = [];

        // Flag Sub-CLs with low attendance percentage (less than 50%)
        $low = collect($supervisorRows)
            ->filter(fn ($r) => $r['attendancePercent'] < 50 && $r['totalAssigned'] > 0)
            ->pluck('supervisorName')
            ->all();

        if ($low) {
            $notes[] = 'Sub-CLs with low attendance coverage (<50%) on selected date: ' . implode(', ', $low);
        }

        if ($openIncidents > 0) {
            $notes[] = "There are {$openIncidents} open incidents currently.";
        }

        if (empty($notes)) {
            $notes[] = 'Operations stable for selected date.';
        }

        return $notes;
    }
}