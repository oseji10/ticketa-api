<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealSession extends Model
{
    protected $primaryKey = 'mealSessionId';

    protected $fillable = [
        'eventId',
        'title',
        'slug',
        'description',
        'mealDate',
        'startTime',
        'endTime',
        'location',
        'status',
        'sortOrder',
        'redeemedCount',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function redemptions()
    {
        return $this->hasMany(MealRedemption::class, 'mealSessionId', 'mealSessionId');
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class, 'mealSessionId', 'mealSessionId');
    }

    public function foodSupplies(): HasMany
{
    return $this->hasMany(FoodSupply::class, 'mealSessionId', 'mealSessionId');
}

public function foodDistributions(): HasMany
{
    return $this->hasMany(FoodDistribution::class, 'mealSessionId', 'mealSessionId');
}

public function ratings(): HasMany
{
    return $this->hasMany(MealRating::class, 'mealSessionId', 'mealSessionId');
}





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


}