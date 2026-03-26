<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Room;
use App\Models\RoomAllocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomAllocationService
{
    public function checkInAttendee(Event $event, Attendee $attendee, Room $room): array
    {
        return DB::transaction(function () use ($event, $attendee, $room) {
            $attendee->refresh();
            $room->refresh();

            if ($attendee->eventId !== $event->eventId) {
                throw ValidationException::withMessages([
                    'attendeeId' => ['Attendee does not belong to this event.'],
                ]);
            }

            if ($room->eventId !== $event->eventId) {
                throw ValidationException::withMessages([
                    'roomId' => ['Selected room does not belong to this event.'],
                ]);
            }

            if ($room->status !== 'active') {
                throw ValidationException::withMessages([
                    'roomId' => ['Selected room is not active.'],
                ]);
            }

            $existingActive = RoomAllocation::where('eventId', $event->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingActive) {
                return [
                    'success' => false,
                    'message' => 'Attendee has already been checked into a room.',
                    'data' => [
                        'status' => 'already_checked_in',
                        'allocationId' => $existingActive->allocationId,
                        'roomId' => $existingActive->roomId,
                    ],
                ];
            }

            $activeCount = RoomAllocation::where('eventId', $event->eventId)
                ->where('roomId', $room->roomId)
                ->where('status', 'active')
                ->count();

            if ($room->capacity > 0 && $activeCount >= $room->capacity) {
                return [
                    'success' => false,
                    'message' => 'This room is already full.',
                    'data' => [
                        'status' => 'room_full',
                        'roomId' => $room->roomId,
                        'capacity' => $room->capacity,
                        'occupied' => $activeCount,
                    ],
                ];
            }

            if ($room->gender !== 'mixed' && !empty($attendee->gender)) {
                $attendeeGender = strtolower((string) $attendee->gender);
                if ($attendeeGender !== strtolower($room->gender)) {
                    return [
                        'success' => false,
                        'message' => 'Attendee gender does not match this room allocation policy.',
                        'data' => [
                            'status' => 'gender_mismatch',
                            'attendeeGender' => $attendee->gender,
                            'roomGender' => $room->gender,
                        ],
                    ];
                ];
            }

            $allocation = RoomAllocation::create([
                'eventId' => $event->eventId,
                'attendeeId' => $attendee->attendeeId,
                'roomId' => $room->roomId,
                'allocationType' => 'initial',
                'status' => 'active',
                'allocatedAt' => now(),
                'allocatedBy' => Auth::id(),
                'reason' => null,
                'previousAllocationId' => null,
            ]);

            return [
                'success' => true,
                'message' => 'Attendee checked into room successfully.',
                'data' => [
                    'status' => 'checked_in',
                    'allocation' => $allocation->load('room'),
                ],
            ];
        });
    }

    public function reallocateAttendee(Event $event, Attendee $attendee, Room $newRoom, string $reason): array
    {
        return DB::transaction(function () use ($event, $attendee, $newRoom, $reason) {
            $attendee->refresh();
            $newRoom->refresh();

            if (trim($reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => ['Reason is required for reallocation.'],
                ]);
            }

            if ($attendee->eventId !== $event->eventId) {
                throw ValidationException::withMessages([
                    'attendeeId' => ['Attendee does not belong to this event.'],
                ]);
            }

            if ($newRoom->eventId !== $event->eventId) {
                throw ValidationException::withMessages([
                    'roomId' => ['Selected room does not belong to this event.'],
                ]);
            }

            if ($newRoom->status !== 'active') {
                throw ValidationException::withMessages([
                    'roomId' => ['Selected room is not active.'],
                ]);
            }

            $currentAllocation = RoomAllocation::where('eventId', $event->eventId)
                ->where('attendeeId', $attendee->attendeeId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$currentAllocation) {
                return [
                    'success' => false,
                    'message' => 'Attendee has not been checked into any room yet.',
                    'data' => [
                        'status' => 'not_checked_in',
                    ],
                ];
            }

            if ((int) $currentAllocation->roomId === (int) $newRoom->roomId) {
                return [
                    'success' => false,
                    'message' => 'Attendee is already allocated to this room.',
                    'data' => [
                        'status' => 'same_room',
                    ],
                ];
            }

            $activeCount = RoomAllocation::where('eventId', $event->eventId)
                ->where('roomId', $newRoom->roomId)
                ->where('status', 'active')
                ->count();

            if ($newRoom->capacity > 0 && $activeCount >= $newRoom->capacity) {
                return [
                    'success' => false,
                    'message' => 'The new room is already full.',
                    'data' => [
                        'status' => 'room_full',
                        'roomId' => $newRoom->roomId,
                        'capacity' => $newRoom->capacity,
                        'occupied' => $activeCount,
                    ],
                ];
            }

           if ($room->gender !== 'mixed' && !empty($attendee->gender)) {
    $attendeeGender = strtolower((string) $attendee->gender);

    if ($attendeeGender !== strtolower($room->gender)) {
        return [
            'success' => false,
            'message' => 'Attendee gender does not match this room allocation policy.',
            'data' => [
                'status' => 'gender_mismatch',
                'attendeeGender' => $attendee->gender,
                'roomGender' => $room->gender,
            ],
        ];
    }
}

            $currentAllocation->update([
                'status' => 'moved',
            ]);

            $newAllocation = RoomAllocation::create([
                'eventId' => $event->eventId,
                'attendeeId' => $attendee->attendeeId,
                'roomId' => $newRoom->roomId,
                'allocationType' => 'reallocation',
                'status' => 'active',
                'reason' => trim($reason),
                'allocatedAt' => now(),
                'allocatedBy' => Auth::id(),
                'previousAllocationId' => $currentAllocation->allocationId,
            ]);

            return [
                'success' => true,
                'message' => 'Room reallocated successfully.',
                'data' => [
                    'status' => 'reallocated',
                    'previousAllocationId' => $currentAllocation->allocationId,
                    'allocation' => $newAllocation->load('room'),
                ],
            ];
        });
    }
}