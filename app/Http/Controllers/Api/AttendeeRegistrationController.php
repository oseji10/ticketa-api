<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPass;
use App\Services\AttendeeRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendeeRegistrationController extends Controller
{
    public function __construct(
        protected AttendeeRegistrationService $attendeeRegistrationService
    ) {
    }

    /**
     * Search attendee by phone or unique ID
     */
    public function search(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
    'q' => ['required', 'string'],
    'eventId' => ['required', 'integer'],
]);

$query = $validated['q'];
$eventId = $validated['eventId'];

        $attendee = Attendee::with('pass')
            // ->where('eventId', $eventId)
            ->where(function ($q) use ($query) {
                $q->where('phone', $query)
                  ->orWhere('uniqueId', $query);
            })
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendee found.',
            'data' => [
                'attendee' => $this->formatAttendee($attendee),
                'assignedPass' => $attendee->pass ? $this->formatPass($attendee->pass) : null,
            ],
        ]);
    }

    /**
     * Verify QR serial before assignment
     */
    public function verifyPass(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'serialNumber' => ['required', 'string', 'max:100'],
        ]);

        $pass = EventPass::with('attendee')
            ->where('eventId', $event->eventId)
            ->where('serialNumber', $validated['serialNumber'])
            ->first();

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'QR code serial number not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'QR code found.',
            'data' => [
                'pass' => $this->formatPass($pass),
                'attendee' => $pass->attendee ? $this->formatAttendee($pass->attendee) : null,
            ],
        ]);
    }

    /**
     * Final registration: tie attendee to a printed QR code
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'attendeeId' => ['required', 'integer'],
            'serialNumber' => ['required', 'string', 'max:100'],
        ]);

        $attendee = Attendee::where('eventId', $event->eventId)
            ->where('attendeeId', $validated['attendeeId'])
            ->first();

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'message' => 'Attendee not found for this event.',
            ], 404);
        }

        $pass = EventPass::where('eventId', $event->eventId)
            ->where('serialNumber', $validated['serialNumber'])
            ->first();

        if (!$pass) {
            return response()->json([
                'success' => false,
                'message' => 'QR code serial number not found for this event.',
            ], 404);
        }

        try {
$result = $this->attendeeRegistrationService->assignPassToAttendee(
    $attendee,
    $pass,
    $request->accommodation,
    $request->color
);
            return response()->json([
                'success' => true,
                'message' => 'Attendee registered successfully.',
                'data' => [
                    'attendee' => $this->formatAttendee($result['attendee']),
                    'pass' => $this->formatPass($result['pass']),
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function formatAttendee(Attendee $attendee): array
    {
        return [
            'attendeeId' => $attendee->attendeeId,
            'eventId' => $attendee->eventId,
            'uniqueId' => $attendee->uniqueId,
            'fullName' => $attendee->fullName,
            'phone' => $attendee->phone,
            'email' => $attendee->email,
            'organization' => $attendee->organization,
            'gender' => $attendee->gender,
            'category' => $attendee->category,

            'age' => $attendee->age,
            'state' => $attendee->state,
            'lga' => $attendee->lga,
            'ward' => $attendee->ward,
            'community' => $attendee->community,
            'religion' => $attendee->religion,
            'bank' => $attendee->bank,
            'accountName' => $attendee->accountName,
            'accountNumber' => $attendee->accountNumber,
            'photoUrl' => $attendee->photoUrl,

            'isRegistered' => (bool) $attendee->isRegistered,
            'registeredAt' => optional($attendee->registeredAt)?->toDateTimeString(),
            'registeredBy' => $attendee->registeredBy,
            'createdAt' => optional($attendee->created_at)?->toDateTimeString(),
            'updatedAt' => optional($attendee->updated_at)?->toDateTimeString(),
        ];
    }

    protected function formatPass(EventPass $pass): array
    {
        return [
            'passId' => $pass->passId,
            'eventId' => $pass->eventId,
            'attendeeId' => $pass->attendeeId,
            'serialNumber' => $pass->serialNumber,
            'status' => $pass->status,
            'isAssigned' => (bool) $pass->isAssigned,
            'assignedAt' => optional($pass->assignedAt)?->toDateTimeString(),
            'assignedBy' => $pass->assignedBy,
            'createdAt' => optional($pass->created_at)?->toDateTimeString(),
            'updatedAt' => optional($pass->updated_at)?->toDateTimeString(),
        ];
    }



    public function registeredAttendees(Event $event): JsonResponse
{
    $attendees = Attendee::query()
        ->with(['pass'])
        ->where('eventId', $event->eventId)
        ->where('isRegistered', true)
        ->orderByDesc('registeredAt')
        ->get()
        ->map(function (Attendee $attendee) {
            return [
                'attendeeId' => $attendee->attendeeId,
                'fullName' => $attendee->fullName,
                'uniqueId' => $attendee->uniqueId,
                'phone' => $attendee->phone,
                'gender' => $attendee->gender,
                'accomodation' => $attendee->accommodation,
                'color' => $attendee->color,
                'serialNumber' => optional($attendee->pass)->serialNumber,
                'registeredAt' => optional($attendee->registeredAt)?->format('Y-m-d H:i:s'),
            ];
        })
        ->values();

    return response()->json([
        'success' => true,
        'message' => 'Registered attendees retrieved successfully.',
        'data' => [
            'attendees' => $attendees,
        ],
    ]);
}
}