<?php

namespace App\Services;

use App\Models\EventPass;
use App\Models\MealRedemption;
use App\Models\MealSession;
use App\Models\ScanLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventRedemptionService
{
    public function redeem(string $token, ?User $scanner = null, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($token, $scanner, $deviceName) {
            $pass = EventPass::where('passCode', $token)
                ->orWhere('qrPayload', $token)
                ->lockForUpdate()
                ->first();

            if (!$pass) {
                ScanLog::create([
                    'token' => $token,
                    'scanResult' => 'invalid',
                    'message' => 'Pass not found',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid pass.',
                    'data' => ['status' => 'invalid'],
                ];
            }

            if ($pass->status === 'void') {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'void',
                    'message' => 'Pass is void',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass has been voided.',
                    'data' => ['status' => 'void'],
                ];
            }

            $now = now();

            $mealSession = MealSession::where('eventId', $pass->eventId)
                ->where('status', 'active')
                ->orderBy('mealDate')
                ->orderBy('startTime')
                ->lockForUpdate()
                ->first();

            if (!$mealSession) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'no_active_meal',
                    'message' => 'No active meal session',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'No active meal session.',
                    'data' => ['status' => 'no_active_meal'],
                ];
            }

            $mealStart = Carbon::parse($mealSession->mealDate . ' ' . $mealSession->startTime);
            $mealEnd = Carbon::parse($mealSession->mealDate . ' ' . $mealSession->endTime);

            if ($now->lt($mealStart) || $now->gt($mealEnd)) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'mealSessionId' => $mealSession->mealSessionId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'outside_window',
                    'message' => 'Meal session is outside redemption window',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass is outside the meal window.',
                    'data' => [
                        'status' => 'outside_window',
                        'mealSession' => $mealSession->title,
                    ],
                ];
            }

            $alreadyRedeemed = MealRedemption::where('mealSessionId', $mealSession->mealSessionId)
                ->where('passId', $pass->passId)
                ->first();

            if ($alreadyRedeemed) {
                ScanLog::create([
                    'eventId' => $pass->eventId,
                    'mealSessionId' => $mealSession->mealSessionId,
                    'passId' => $pass->passId,
                    'token' => $token,
                    'scanResult' => 'already_redeemed',
                    'message' => 'Pass already redeemed for this meal session',
                    'scannedBy' => $scanner?->id,
                    'deviceName' => $deviceName,
                    'ipAddress' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Pass already redeemed for this meal.',
                    'data' => [
                        'status' => 'already_redeemed',
                        'mealSession' => $mealSession->title,
                        'redeemedAt' => $alreadyRedeemed->redeemedAt,
                    ],
                ];
            }

            MealRedemption::create([
                'mealSessionId' => $mealSession->mealSessionId,
                'passId' => $pass->passId,
                'redeemedAt' => $now,
                'redeemedBy' => $scanner?->id,
                'deviceName' => $deviceName,
            ]);

            $mealSession->increment('redeemedCount');

            ScanLog::create([
                'eventId' => $pass->eventId,
                'mealSessionId' => $mealSession->mealSessionId,
                'passId' => $pass->passId,
                'token' => $token,
                'scanResult' => 'valid',
                'message' => 'Pass redeemed successfully',
                'scannedBy' => $scanner?->id,
                'deviceName' => $deviceName,
                'ipAddress' => request()->ip(),
            ]);

            return [
                'success' => true,
                'message' => 'Pass redeemed successfully.',
                'data' => [
                    'status' => 'redeemed',
                    'eventId' => $pass->eventId,
                    'mealSessionId' => $mealSession->mealSessionId,
                    'mealSession' => $mealSession->title,
                    'mealDate' => $mealSession->mealDate,
                ],
            ];
        });
    }
}