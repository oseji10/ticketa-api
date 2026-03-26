<?php

namespace App\Services;

use App\Models\DailyAttendance;
use App\Models\Event;
use App\Models\EventPass;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyAttendanceService
{
    public function markAttendance(
        Event $event,
        string $token,
        ?string $deviceName = null,
        ?string $scanSource = 'barcode',
        ?string $attendanceDate = null
    ): array {
        return DB::transaction(function () use ($event, $token, $deviceName, $scanSource, $attendanceDate) {
            $date = $attendanceDate
                ? Carbon::parse($attendanceDate)->toDateString()
                : now()->toDateString();

            $pass = EventPass::with('attendee')
                ->lockForUpdate()
                ->where('eventId', $event->eventId)
                ->where('token', $token)
                ->first();

            if (!$pass) {
                return [
                    'success' => false,
                    'message' => 'Invalid barcode or QR code.',
                    'data' => [
                        'status' => 'invalid',
                    ],
                ];
            }

            if (!$pass->attendeeId || !$pass->attendee) {
                return [
                    'success' => false,
                    'message' => 'This pass has not been assigned to any attendee.',
                    'data' => [
                        'status' => 'unassigned',
                        'passId' => $pass->passId,
                    ],
                ];
            }

            if (isset($pass->status) && $pass->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'This pass is not active.',
                    'data' => [
                        'status' => 'inactive_pass',
                        'passId' => $pass->passId,
                    ],
                ];
            }

            // Optional event date guard:
            // Uncomment if your events table has startDate and endDate columns.
            /*
            if ($event->startDate && $event->endDate) {
                $eventStart = Carbon::parse($event->startDate)->toDateString();
                $eventEnd = Carbon::parse($event->endDate)->toDateString();

                if ($date < $eventStart || $date > $eventEnd) {
                    return [
                        'success' => false,
                        'message' => 'Attendance cannot be marked outside the event dates.',
                        'data' => [
                            'status' => 'outside_event_dates',
                            'attendanceDate' => $date,
                        ],
                    ];
                }
            }
            */

            $existing = DailyAttendance::where('eventId', $event->eventId)
                ->where('eventPassId', $pass->passId)
                ->whereDate('attendanceDate', $date)
                ->first();

            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Attendance has already been marked for today.',
                    'data' => [
                        'status' => 'already_marked',
                        'attendanceId' => $existing->attendanceId,
                        'attendanceDate' => $existing->attendanceDate?->toDateString(),
                        'markedAt' => optional($existing->markedAt)?->toDateTimeString(),
                        'attendee' => [
                            'attendeeId' => $pass->attendee->attendeeId,
                            'name' => strtoupper(trim(($pass->attendee->firstName ?? '') . ' ' . ($pass->attendee->lastName ?? ''))),
                            'uniqueId' => $pass->attendee->uniqueId,
                            'phone' => $pass->attendee->phone,
                        ],
                    ],
                ];
            }

            $attendance = DailyAttendance::create([
                'eventId' => $event->eventId,
                'attendeeId' => $pass->attendeeId,
                'eventPassId' => $pass->passId,
                'attendanceDate' => $date,
                'markedAt' => now(),
                'markedBy' => Auth::id(),
                'deviceName' => $deviceName,
                'scanSource' => $scanSource,
            ]);

            return [
                'success' => true,
                'message' => 'Attendance marked successfully.',
                'data' => [
                    'status' => 'marked',
                    'attendanceId' => $attendance->attendanceId,
                    'attendanceDate' => $attendance->attendanceDate?->toDateString(),
                    'markedAt' => optional($attendance->markedAt)?->toDateTimeString(),
                    'attendee' => [
                        'attendeeId' => $pass->attendee->attendeeId,
                        'name' => strtoupper(trim(($pass->attendee->firstName ?? '') . ' ' . ($pass->attendee->lastName ?? ''))),
                        'uniqueId' => $pass->attendee->uniqueId,
                        'phone' => $pass->attendee->phone,
                        'gender' => $pass->attendee->gender ?? null,
                        'passportUrl' => $pass->attendee->passportUrl ?? null,
                    ],
                    'pass' => [
                        'passId' => $pass->passId,
                        'serialNumber' => $pass->serialNumber ?? null,
                        'token' => $pass->token,
                    ],
                ],
            ];
        });
    }
}