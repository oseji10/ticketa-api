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
        'accomodation',
        'color',

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
}