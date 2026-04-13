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
            ->orderByDesc('created_at')   // if multiple active, take the latest
            ->first();

        if (!$activeEvent) {
            return response()->json([
                'data' => null,
                'message' => 'No active event found.',
            ], 404);
        }

        $eventId = $activeEvent->eventId ?? $activeEvent->id;

        // ── Date & period ─────────────────────────────────────────────────────
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $selectedDateString = $selectedDate->toDateString();

        $programme = $activeEvent->title ?? 'ISSAM Residential Training';
        $venue     = $activeEvent->location ?? 'ABU Zaria';

        $periodStart = Carbon::parse($activeEvent->startDate ?? $request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd   = Carbon::parse($activeEvent->endDate   ?? $request->query('periodEnd',   '2026-03-30'))->toDateString();

        // ── Core counts (scoped to active event) ─────────────────────────────

        // Total accredited participants for this event
        $totalParticipants = DB::table('attendees as a')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->where('a.isRegistered', 1)
            ->distinct()
            ->count('a.attendeeId');

        // Present on selected date — daily_attendances must be scoped to the event
        $presentForDate = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->distinct()
            ->count('da.attendeeId');

        $absentForDate     = max($totalParticipants - $presentForDate, 0);
        $attendancePercent = $totalParticipants > 0
            ? round(($presentForDate / $totalParticipants) * 100, 1)
            : 0;

        // Incidents scoped to event
        $incidentsForDate = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereDate('reportedAt', $selectedDateString)
            ->count();

        $openIncidents = DB::table('incidents')
            ->where('eventId', $eventId)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        // Room allocations scoped to event
        $roomsCheckedForDate = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->whereDate('allocatedAt', $selectedDateString)
            ->whereNotNull('allocatedAt')
            ->count();

        // Meals served (unique attendees) scoped to event
        $mealsServedForDate = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('mr.created_at', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

        // Gender split — present on selected date, scoped to event
        $genderSplit = DB::table('daily_attendances as da')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm') THEN 'male'
                        WHEN TRIM(LOWER(a.gender)) IN ('female', 'f') THEN 'female'
                        ELSE 'other'
                    END as gender
                "),
                DB::raw('COUNT(DISTINCT da.attendeeId) as count')
            )
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Registered gender split — accredited attendees for this event
        $registeredGenderSplit = DB::table('attendees as a')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->where('a.isRegistered', 1)
            ->select(
                DB::raw("
                    CASE
                        WHEN TRIM(LOWER(a.gender)) IN ('male', 'm') THEN 'male'
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
        $supervisorRows    = $this->buildSupervisorRows($selectedDateString, $eventId);
        $incidentSnapshot  = $this->buildIncidentSnapshot($selectedDateString, $eventId);
        $roomMetrics       = $this->buildRoomMetrics($selectedDateString, $eventId);
        $coordinatorNotes  = $this->buildCoordinatorNotes($supervisorRows, $openIncidents, $selectedDateString);

        return response()->json([
            'data' => [
                'dashboardDate'  => $selectedDate->format('d M Y'),
                'dayName'        => $selectedDate->format('l'),
                'programme'      => $programme,
                'venue'          => $venue,
                'period'         => Carbon::parse($periodStart)->format('d-M-Y') . ' to ' . Carbon::parse($periodEnd)->format('d-M-Y'),
                'overviewStats'  => $overviewStats,
                'supervisorRows' => $supervisorRows,
                'incidentSnapshot' => $incidentSnapshot,
                'roomMetrics'    => $roomMetrics,
                'coordinatorNotes' => $coordinatorNotes,
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function buildSupervisorRows(string $selectedDate, int $eventId): array
    {
        return DB::table('daily_attendances as da')
            ->join('users as u', 'u.id', '=', 'da.markedBy')
            ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
            ->join('event_passes as ep', 'ep.attendeeId', '=', 'a.attendeeId')
            ->where('ep.eventId', $eventId)
            ->whereDate('da.attendanceDate', $selectedDate)
            ->select(
                'u.id as supervisorId',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))) as supervisorName"),
                DB::raw('COUNT(DISTINCT da.attendeeId) as attendanceCount')
            )
            ->groupBy('u.id', 'u.firstName', 'u.lastName')
            ->orderByDesc('attendanceCount')
            ->get()
            ->map(function ($row) {
                return [
                    'supervisorId'    => $row->supervisorId,
                    'supervisorName'  => $row->supervisorName,
                    'attendanceCount' => (int) $row->attendanceCount,
                    'status'          => $this->resolveSupervisorStatus((int) $row->attendanceCount),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveSupervisorStatus(int $count): string
    {
        if ($count >= 10) return 'High Activity';
        if ($count >= 5)  return 'Active';
        return 'Low Activity';
    }

    protected function buildIncidentSnapshot(string $selectedDate, int $eventId): array
    {
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

    protected function buildRoomMetrics(string $selectedDate, int $eventId): array
    {
        $assigned = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->count();

        $checkedInForDate = DB::table('room_allocations')
            ->where('eventId', $eventId)
            ->whereNotNull('allocatedAt')
            ->whereDate('allocatedAt', $selectedDate)
            ->count();

        return [
            ['metric' => 'Assigned (All)',       'value' => $assigned],
            ['metric' => 'Checked In for Date',  'value' => $checkedInForDate],
        ];
    }

    protected function buildCoordinatorNotes(array $supervisorRows, int $openIncidents, string $selectedDate): array
    {
        $notes = [];

        $low = collect($supervisorRows)
            ->filter(fn ($r) => $r['attendanceCount'] < 5)
            ->pluck('supervisorName')
            ->all();

        if ($low) {
            $notes[] = 'Low attendance activity on selected date: ' . implode(', ', $low);
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