<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendee extends Model
{
    protected $primaryKey = 'attendeeId';

    protected $fillable = [
        'eventId',
        'uniqueId',
        'fullName',
        'phone',
        'email',
        'organization',
        'gender',
        'category',

        // NEW FIELDS
        'age',
        'state',
        'lga',
        'ward',
        'community',
        'religion',
        'bank',
        'accountName',
        'accountNumber',
        'photoUrl',
        'accommodation',
        'color',
        'colorId',
        'subClId',

        // REGISTRATION FIELDS
        'isRegistered',
        'registeredAt',
        'registeredBy',
    ];


    protected $casts = [
    'isRegistered' => 'boolean',
    'registeredAt' => 'datetime',
    // 'accountNumber' => 'encrypted',
];

    /**
     * Relationships
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function pass(): HasOne
    {
        return $this->hasOne(EventPass::class, 'attendeeId', 'attendeeId');
    }

    public function dailyAttendances()
{
    return $this->hasMany(DailyAttendance::class, 'attendeeId', 'attendeeId');
}

public function roomAllocations()
{
    return $this->hasMany(RoomAllocation::class, 'attendeeId', 'attendeeId');
}

public function activeRoomAllocation()
{
    return $this->hasOne(RoomAllocation::class, 'attendeeId', 'attendeeId')
        ->where('status', 'active')
        ->latest('allocationId');
}

public function currentRoomAllocation()
{
    return $this->hasOne(RoomAllocation::class, 'attendeeId', 'attendeeId')
        // ->where('status', 'active')
        ->latest('allocationId');
}

public function incidents()
{
    return $this->hasMany(Incident::class, 'attendeeId', 'attendeeId');
}

public function group_color()
{
    return $this->belongsTo(Color::class, 'colorId', 'colorId');
}

public function subCommunityLead()
{
    return $this->belongsTo(SubCL::class, 'subClId');
}

}