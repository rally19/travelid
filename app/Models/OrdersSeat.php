<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdersSeat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'orders_id',
        'routes_schedules_id',
        'name',
        'age',
        'title',
        'cost',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    /**
     * Get the order associated with this seat.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orders_id');
    }

    /**
     * Get the route schedule associated with this seat.
     */
    public function routesSchedule(): BelongsTo
    {
        return $this->belongsTo(RoutesSchedule::class, 'routes_schedules_id');
    }
}