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
        $today = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $todayDate = $today->toDateString();

        $programme = 'ISSAM Residential Training';
        $venue = 'Zaria';
        $periodStart = Carbon::parse($request->query('periodStart', '2026-03-24'))->toDateString();
        $periodEnd = Carbon::parse($request->query('periodEnd', '2026-03-30'))->toDateString();

        // ======================
        // CORE STATS
        // ======================

        $totalParticipants = DB::table('attendees')->where('isRegistered', 1)->count();

        $presentToday = DB::table('daily_attendances')
            ->whereDate('attendanceDate', $todayDate)
            ->distinct('attendeeId')
            ->count('attendeeId');

        $lateToday = 0;

        $absentToday = max($totalParticipants - $presentToday, 0);

        $attendancePercent = $totalParticipants > 0
            ? round(($presentToday / $totalParticipants) * 100, 1)
            : 0;

        $incidentsToday = DB::table('incidents')
            ->whereDate('reportedAt', $todayDate)
            ->count();

        $openIncidents = DB::table('incidents')
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $roomsChecked = DB::table('room_allocations')
            ->whereNotNull('allocatedAt')
            ->count();

        // ✅ UNIQUE PEOPLE WHO TOOK MEALS
        $mealsServed = DB::table('meal_redemptions as mr')
            ->join('event_passes as ep', 'ep.passId', '=', 'mr.passId')
            ->whereDate('mr.created_at', $todayDate)
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

        // ======================
        // OVERVIEW CARDS
        // ======================

        $overviewStats = [
            ['title' => 'Total Participants', 'value' => (string)$totalParticipants],
            ['title' => 'Present Today', 'value' => (string)$presentToday],
            ['title' => 'Absent Today', 'value' => (string)$absentToday],
            ['title' => 'Attendance %', 'value' => number_format($attendancePercent, 1) . '%'],
            ['title' => 'Incidents Today', 'value' => (string)$incidentsToday],
            ['title' => 'Open Incidents', 'value' => (string)$openIncidents],
            ['title' => 'Rooms Checked', 'value' => (string)$roomsChecked],
            ['title' => 'Meals (Unique)', 'value' => (string)$mealsServed],
        ];

        // ======================
        // SUPERVISOR PERFORMANCE
        // ======================

        $supervisorRows = $this->buildSupervisorRows($todayDate);

        // ======================
        // OTHER BLOCKS
        // ======================

        $incidentSnapshot = $this->buildIncidentSnapshot();
        $roomMetrics = $this->buildRoomMetrics();
        $coordinatorNotes = $this->buildCoordinatorNotes($supervisorRows, $openIncidents);

        return response()->json([
            'data' => [
                'dashboardDate' => $today->format('d M Y'),
                'dayName' => $today->format('l'),
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

    // ======================
    // SUPERVISOR ROWS
    // ======================

    protected function buildSupervisorRows(string $todayDate): array
    {
        return DB::table('daily_attendances as da')
    ->join('users as u', 'u.id', '=', 'da.markedBy')
    ->select(
        'u.id as supervisorId',
        DB::raw("TRIM(CONCAT(COALESCE(u.firstName, ''), ' ', COALESCE(u.lastName, ''))) as supervisorName"),
        DB::raw('COUNT(DISTINCT da.attendeeId) as attendanceCount')
    )
    ->whereDate('da.attendanceDate', $todayDate)
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

    // ======================
    // INCIDENT SNAPSHOT
    // ======================

    protected function buildIncidentSnapshot(): array
    {
        return DB::table('incidents')
            ->select('category', DB::raw('COUNT(*) as total'))
            ->whereIn('status', ['open', 'pending'])
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => (int)$row->total,
            ])
            ->toArray();
    }

    // ======================
    // ROOM METRICS
    // ======================

    protected function buildRoomMetrics(): array
{
    $assigned = DB::table('room_allocations')->count();

    $checkedIn = DB::table('room_allocations')
        ->whereNotNull('allocatedAt')
        ->count();

    // $ackSigned = DB::table('room_allocations')
    //     ->whereNotNull('acknowledgementSignedAt')
    //     ->count();

    // $keysIssued = DB::table('room_allocations')
    //     ->whereNotNull('keyIssuedAt')
    //     ->count();

    return [
        [
            'metric' => 'Assigned',
            'value' => $assigned,
        ],
        [
            'metric' => 'Checked In',
            'value' => $checkedIn,
        ],
        // [
        //     'metric' => 'Acknowledged',
        //     'value' => $ackSigned,
        // ],
        // [
        //     'metric' => 'Keys Issued',
        //     'value' => $keysIssued,
        // ],
    ];
}

    // ======================
    // COORDINATOR NOTES
    // ======================

    protected function buildCoordinatorNotes(array $supervisorRows, int $openIncidents): array
    {
        $notes = [];

        $low = collect($supervisorRows)
            ->filter(fn ($r) => $r['attendanceCount'] < 5)
            ->pluck('supervisorName')
            ->all();

        if ($low) {
            $notes[] = 'Low attendance activity: ' . implode(', ', $low);
        }

        if ($openIncidents > 0) {
            $notes[] = "There are {$openIncidents} open incidents.";
        }

        if (empty($notes)) {
            $notes[] = 'Operations stable.';
        }

        return $notes;
    }
}