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

        $presentForDate = DB::table('daily_attendances as da')
    ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
    ->whereDate('da.attendanceDate', $selectedDateString)
    ->where('a.isRegistered', 1)
    ->distinct()
    ->count('da.attendeeId');

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
            ->join('attendees as a', 'a.attendeeId', '=', 'ep.attendeeId')
            ->whereDate('mr.created_at', $selectedDateString)
            ->where('a.isRegistered', 1)
            ->distinct('ep.attendeeId')
            ->count('ep.attendeeId');

$genderSplit = DB::table('daily_attendances as da')
    ->join('attendees as a', 'a.attendeeId', '=', 'da.attendeeId')
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

    $registeredGenderSplit = DB::table('attendees')
    ->where('isRegistered', 1)
    ->select(
        DB::raw('LOWER(gender) as gender'),
        DB::raw('COUNT(*) as count')
    )
    ->groupBy('gender')
    ->pluck('count', 'gender');





        // $overviewStats = [
        //     ['title' => 'Total Participants', 'value' => (string) $totalParticipants],
        //     ['title' => 'Present for Date', 'value' => (string) $presentForDate],
        //     ['title' => 'Absent for Date', 'value' => (string) $absentForDate],
        //     ['title' => 'Attendance %', 'value' => number_format($attendancePercent, 1) . '%'],
        //     ['title' => 'Incidents for Date', 'value' => (string) $incidentsForDate],
        //     ['title' => 'Open Incidents', 'value' => (string) $openIncidents],
        //     ['title' => 'Rooms Checked for Date', 'value' => (string) $roomsCheckedForDate],
        //     ['title' => 'Meals (Unique)', 'value' => (string) $mealsServedForDate],
        //     ['title' => 'Present Split By Gender', 'value' => (string) $genderSplit],
        //     ['title' => 'Accredited Split By Gender', 'value' => (string) $registeredGenderSplit],
        // ];

        $overviewStats = [
    ['title' => 'Total Accredited Participants', 'value' => (string) $totalParticipants],

    [
        'title' => 'Accredited Male',
        'value' => (string) ($registeredGenderSplit['male'] ?? 0),
        'note' => 'Registered attendees',
        'iconKey' => 'users',
        'iconWrapperClass' => 'bg-black-100 dark:bg-black-800/40',
        'iconClassName' => 'w-5 h-5 text-black-700 dark:text-black-100',
    ],
    [
        'title' => 'Accredited Female',
        'value' => (string) ($registeredGenderSplit['female'] ?? 0),
        'note' => 'Registered attendees',
        'iconKey' => 'users',
        'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
        'iconClassName' => 'w-5 h-5 text-pink-700 dark:text-pink-100',
    ],
    ['title' => 'Total Present for Selected Date', 'value' => (string) $presentForDate],
    ['title' => 'Total Absent for Selected Date', 'value' => (string) $absentForDate],
    ['title' => 'Attendance %', 'value' => number_format($attendancePercent, 1) . '%'],


[
        'title' => 'Males Present',
        'value' => (string) ($genderSplit['male'] ?? 0),
        'note' => 'Present attendees',
        'iconKey' => 'users',
        'iconWrapperClass' => 'bg-blue-100 dark:bg-blue-800/40',
        'iconClassName' => 'w-5 h-5 text-blue-700 dark:text-blue-100',
    ],
    [
        'title' => 'Females Present',
        'value' => (string) ($genderSplit['female'] ?? 0),
        'note' => 'Present attendees',
        'iconKey' => 'users',
        'iconWrapperClass' => 'bg-pink-100 dark:bg-pink-800/40',
        'iconClassName' => 'w-5 h-5 text-pink-700 dark:text-pink-100',
    ],

    ['title' => 'Rooms Checked for Date', 'value' => (string) $roomsCheckedForDate],
    ['title' => 'Incidents for Date', 'value' => (string) $incidentsForDate],
    ['title' => 'Open Incidents', 'value' => (string) $openIncidents],
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