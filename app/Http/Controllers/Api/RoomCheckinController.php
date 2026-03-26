<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\RoomAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomCheckinController extends Controller
{
    protected function ensureSupervisorAccess(): void
    {
        // $user = Auth::user();
        $user = auth()->user();
        if (!$user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only supervisors can perform check-in.',
            ], 403));
        }
        // $user = auth()->user();
        // if (!$user) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized.',
        //     ], 403);
        // }


    }

   public function checkin(Request $request, Event $event): JsonResponse
{
    $this->ensureSupervisorAccess();

    $validated = $request->validate([
        'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
        'roomId' => ['required', 'integer', 'exists:rooms,roomId'],
    ]);

    return DB::transaction(function () use ($validated, $event) {

        $attendee = Attendee::lockForUpdate()
            ->where('attendeeId', $validated['attendeeId'])
            ->firstOrFail();

        if ($attendee->eventId !== $event->eventId) {
            throw ValidationException::withMessages([
                'attendeeId' => ['Attendee does not belong to this event.'],
            ]);
        }

        $existing = RoomAllocation::where('eventId', $event->eventId)
            ->where('attendeeId', $attendee->attendeeId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee already checked in.',
            ], 422);
        }

        $room = \App\Models\Room::lockForUpdate()
            ->where('roomId', $validated['roomId'])
            ->firstOrFail();

        // Optional: enforce gender rule
        // if ($room->gender !== 'mixed' && $room->gender !== $attendee->gender) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Room gender restriction mismatch.',
        //     ], 422);
        // }

        // Optional: capacity check
        $occupied = RoomAllocation::where('roomId', $room->roomId)
            ->where('status', 'active')
            ->count();

        if ($room->capacity && $occupied >= $room->capacity) {
            return response()->json([
                'success' => false,
                'message' => 'Room is already full.',
            ], 422);
        }

        $allocation = RoomAllocation::create([
            'eventId' => $event->eventId,
            'attendeeId' => $attendee->attendeeId,
            'roomId' => $room->roomId,
            'allocationType' => 'initial',
            'status' => 'active',
            'allocatedAt' => now(),
            'allocatedBy' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Checked in successfully.',
            'data' => [
                'allocationId' => $allocation->allocationId,
                'room' => [
                    'roomId' => $room->roomId,
                    'name' => $room->name,
                    'code' => $room->code,
                    'building' => $room->building,
                ],
            ],
        ]);
    });
}


public function reallocate(Request $request, Event $event): JsonResponse
{
    $this->ensureSupervisorAccess();

    $validated = $request->validate([
        'attendeeId' => ['required', 'integer', 'exists:attendees,attendeeId'],
        'roomId' => ['required', 'integer', 'exists:rooms,roomId'],
        'reason' => ['required', 'string'],
    ]);

    return DB::transaction(function () use ($validated, $event) {

        $attendee = Attendee::lockForUpdate()
            ->where('attendeeId', $validated['attendeeId'])
            ->firstOrFail();

        $current = RoomAllocation::where('attendeeId', $attendee->attendeeId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (!$current) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not checked in yet.',
            ], 422);
        }

        if ($current->roomId == $validated['roomId']) {
            return response()->json([
                'success' => false,
                'message' => 'Already in this room.',
            ], 422);
        }

        $room = \App\Models\Room::lockForUpdate()
            ->findOrFail($validated['roomId']);

        // Capacity check
        $occupied = RoomAllocation::where('roomId', $room->roomId)
            ->where('status', 'active')
            ->count();

        if ($room->capacity && $occupied >= $room->capacity) {
            return response()->json([
                'success' => false,
                'message' => 'Room is full.',
            ], 422);
        }

        // Close old allocation
        $current->update(['status' => 'moved']);

        $new = RoomAllocation::create([
            'eventId' => $event->eventId,
            'attendeeId' => $attendee->attendeeId,
            'roomId' => $room->roomId,
            'allocationType' => 'reallocation',
            'status' => 'active',
            'allocatedAt' => now(),
            'allocatedBy' => Auth::id(),
            'reason' => $validated['reason'],
            'previousAllocationId' => $current->allocationId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room reallocated.',
            'data' => [
                'allocationId' => $new->allocationId,
                'room' => [
                    'roomId' => $room->roomId,
                    'name' => $room->name,
                    'code' => $room->code,
                    'building' => $room->building,
                ],
                'reason' => $new->reason,
            ],
        ]);
    });
}
}