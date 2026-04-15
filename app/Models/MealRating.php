<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class MealRating extends Model
{
    protected $table = 'meal_ratings';
    protected $primaryKey = 'ratingId';

    protected $fillable = [
        'eventId',
        'mealSessionId',
        'attendeeId',
        'rating',
        'comment',
        'deviceIdentifier',
        'ipAddress',
        'userAgent',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    protected $hidden = [
        'deviceIdentifier',
        'ipAddress',
        'userAgent',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function mealSession(): BelongsTo
    {
        return $this->belongsTo(MealSession::class, 'mealSessionId', 'mealSessionId');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }
}

/**
 * Update existing MealSession model with new relationships
 * Add these methods to your existing App\Models\MealSession class
 */

// Add to MealSession class:
/*


// Helper to get inventory summary
public function getInventorySummary(): array
{
    $supplies = $this->foodSupplies;
    
    return [
        'totalSupplied' => $supplies->sum('quantitySupplied'),
        'totalDistributed' => $supplies->sum('quantityDistributed'),
        'totalRemaining' => $supplies->sum('quantityRemaining'),
    ];
}

// Helper to get average rating
public function getAverageRating(): ?float
{
    $avg = $this->ratings()->avg('rating');
    return $avg ? round($avg, 2) : null;
}
*/