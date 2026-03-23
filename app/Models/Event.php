<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $primaryKey = 'eventId';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'startDate',
        'endDate',
        'location',
        'status',
        'passCount',
        'createdBy',
    ];

    public function mealSessions()
    {
        return $this->hasMany(MealSession::class, 'eventId', 'eventId');
    }

    public function passes()
    {
        return $this->hasMany(EventPass::class, 'eventId', 'eventId');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy');
    }
}