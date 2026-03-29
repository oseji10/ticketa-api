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
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $selectedDateString = $selectedDate->toDateString();

        $programme = 'ISSAM Residential Training';
        $venue = 'Zaria';
        $periodStart = Carbon::parse($request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd = Carbon::parse($request->query('periodEnd', '2026-03-30'))->toDateString();

        $totalParticipants = DB::table('attendees')
            ->where('isRegistered', 1)
            ->count();

        $presentForDate = DB::table('daily_attendances')
            ->whereDate('attendanceDate', $selectedDateString)
            ->distinct('attendeeId')
            ->count('attendeeId');

        $lateForDate = 0;

        $absentForDate = max($totalParticipants - $presentForDate, 0);

        $attendancePercent = $totalParticipants > 0
            ? round(($presentForDate / $totalParticipants) * 100, 1)
            : 0;

        $incidentsForDate = DB::table('incidents')
            ->whereDate('reportedAt', $selectedDateString)
            ->count();

        $openIncidents = DB::table('incidents')
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $roomsCheckedForDate = DB::table('room_allocations')
            ->whereDate('allocatedAt', $selectedDateString)
            ->whereNotNull('allocatedAt')
            ->count();

        $mealsServedForDate = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->whereDate('mr.created_at', $selectedDateString)
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

        $overviewStats = [
            ['title' => 'Total Participants', 'value' => (string) $totalParticipants],
            ['title' => 'Present for Date', 'value' => (string) $presentForDate],
            ['title' => 'Absent for Date', 'value' => (string) $absentForDate],
            ['title' => 'Attendance %', 'value' => number_format($attendancePercent, 1) . '%'],
            ['title' => 'Incidents for Date', 'value' => (string) $incidentsForDate],
            ['title' => 'Open Incidents', 'value' => (string) $openIncidents],
            ['title' => 'Rooms Checked for Date', 'value' => (string) $roomsCheckedForDate],
            ['title' => 'Meals (Unique)', 'value' => (string) $mealsServedForDate],
        ];

        $supervisorRows = $this->buildSupervisorRows($selectedDateString);
        $incidentSnapshot = $this->buildIncidentSnapshot($selectedDateString);
        $roomMetrics = $this->buildRoomMetrics($selectedDateString);
        $coordinatorNotes = $this->buildCoordinatorNotes($supervisorRows, $openIncidents, $selectedDateString);

        return response()->json([
            'data' => [
                'dashboardDate' => $selectedDate->format('d M Y'),
                'dayName' => $selectedDate->format('l'),
                'programme' => $programme,
                'venue' => $venue,
                'period' => Carbon::parse($periodStart)->format('d-M-Y') . ' to ' . Carbon::parse($periodEnd)->format('d-M-Y'),
                'overviewStats' => $overviewStats,
                'supervisorRows' => $supervisorRows,
                'incidentSnapshot' => $incidentSnapshot,
                'roomMetrics' => $roomMetrics,
                'coordinatorNotes' => $coordinatorNotes,
            ],
        ]);
    }

    protected function buildSupervisorRows(string $selectedDate): array
    {
        return DB::table('daily_attendances as da')
            ->join('users as u', 'u.id', '=', 'da.markedBy')
            ->select(
                'u.id as supervisorId',
                DB::raw("TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))) as supervisorName"),
                DB::raw('COUNT(DISTINCT da.attendeeId) as attendanceCount')
            )
            ->whereDate('da.attendanceDate', $selectedDate)
            ->groupBy('u.id', 'u.firstName', 'u.lastName')
            ->orderByDesc('attendanceCount')
            ->get()
            ->map(function ($row) {
                return [
                    'supervisorId' => $row->supervisorId,
                    'supervisorName' => $row->supervisorName,
                    'attendanceCount' => (int) $row->attendanceCount,
                    'status' => $this->resolveSupervisorStatus((int) $row->attendanceCount),
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveSupervisorStatus(int $count): string
    {
        if ($count >= 20) return 'High Activity';
        if ($count >= 10) return 'Active';
        return 'Low Activity';
    }

    protected function buildIncidentSnapshot(string $selectedDate): array
    {
        return DB::table('incidents')
            ->select('category', DB::raw('COUNT(*) as total'))
            ->whereDate('reportedAt', $selectedDate)
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => (int) $row->total,
            ])
            ->toArray();
    }

    protected function buildRoomMetrics(string $selectedDate): array
    {
        $assigned = DB::table('room_allocations')->count();

        $checkedInForDate = DB::table('room_allocations')
            ->whereNotNull('allocatedAt')
            ->whereDate('allocatedAt', $selectedDate)
            ->count();

        return [
            [
                'metric' => 'Assigned (All)',
                'value' => $assigned,
            ],
            [
                'metric' => 'Checked In for Date',
                'value' => $checkedInForDate,
            ],
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