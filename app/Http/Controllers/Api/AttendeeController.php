<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $attendees = Attendee::where('eventId', $activeEvent->eventId)
            ->select([
                'attendeeId',
                'firstName',
                'lastName',
                'uniqueId',
                'phone',
                'accommodation',
                'roomNumber',
            ])
            ->get()
            ->map(function ($attendee) {
                return [
                    'attendeeId' => $attendee->attendeeId,
                    'firstName' => $attendee->firstName,
                    'lastName' => $attendee->lastName,
                    'fullName' => trim(($attendee->firstName ?? '') . ' ' . ($attendee->lastName ?? '')),
                    'uniqueId' => $attendee->uniqueId,
                    'phone' => $attendee->phone,
                    'accommodation' => $attendee->accommodation,
                    'roomNumber' => $attendee->roomNumber,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Attendees retrieved successfully.',
            'data' => $attendees,
        ]);
    }
}