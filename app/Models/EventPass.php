<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPass extends Model
{
    protected $primaryKey = 'passId';

    protected $fillable = [
        'eventId',
        'passCode',
        'serialNumber',
        'qrPayload',
        'qrPath',
        'qrUrl',
        'status',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function redemptions()
    {
        return $this->hasMany(MealRedemption::class, 'passId', 'passId');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'passId', 'passId');
    }
}