<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendeeRegistrationService
{
    public function assignPassToAttendee(
        Attendee $attendee,
        EventPass $pass,
        ?string $accomodation = null,
        ?string $color = null
    ): array {
        return DB::transaction(function () use ($attendee, $pass, $accomodation, $color) {
            $attendee->refresh();
            $pass->refresh();

            if ($attendee->eventId !== $pass->eventId) {
                throw ValidationException::withMessages([
                    'serialNumber' => ['Selected QR code does not belong to this event.'],
                ]);
            }

            if ($pass->isAssigned || !empty($pass->attendeeId)) {
                throw ValidationException::withMessages([
                    'serialNumber' => ['This QR code has already been assigned.'],
                ]);
            }

            $existingPass = EventPass::where('eventId', $attendee->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->first();

            if ($existingPass) {
                throw ValidationException::withMessages([
                    'attendee' => ['This attendee already has an assigned QR code.'],
                ]);
            }

            $now = now();
            $userId = Auth::id();

            $pass->update([
                'attendeeId' => $attendee->attendeeId,
                'isAssigned' => true,
                'assignedAt' => $now,
                'assignedBy' => $userId,
                'status' => 'active',
            ]);

            $attendee->update([
                'isRegistered' => true,
                'registeredAt' => $now,
                'registeredBy' => $userId,
                'accomodation' => $accomodation,
                'color' => $color,
            ]);

            return [
                'attendee' => $attendee->fresh(['event', 'pass']),
                'pass' => $pass->fresh(['attendee']),
            ];
        });
    }
}