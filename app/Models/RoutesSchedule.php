<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutesSchedule extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'routes_schedules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'buses_id',
        'name',
        'status',
        'price',
        'description',
        'departure_id',
        'departure_time',
        'arrival_id',
        'arrival_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'arrival_time' => 'datetime',
            'status' => 'string',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Get the bus associated with this route schedule.
     */
    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class, 'buses_id');
    }

    /**
     * Get the departure terminal for this route.
     */
    public function departureTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'departure_id');
    }

    /**
     * Get the arrival terminal for this route.
     */
    public function arrivalTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'arrival_id');
    }

    /**
     * Get all seat orders for this routes schedules.
     */
    public function seats(): HasMany
    {
        return $this->hasMany(OrdersSeat::class, 'routes_schedules_id');
    }
}