<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'users_id',
        'status',
        'bus_code',
        'bus_name',
        'routes_schedules_code',
        'route_name',
        'departure_terminal',
        'departure_location',
        'departure_time',
        'arrival_terminal',
        'arrival_location',
        'arrival_time',
        'payment_proof',
        'payment_method',
        'quantity',
        'total_cost',
        'comments',
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
            'total_cost' => 'decimal:2',
        ];
    }

    /**
     * Get the user associated with this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * Get all seats associated with this order.
     */
    public function seats(): HasMany
    {
        return $this->hasMany(OrdersSeat::class, 'orders_id');
    }
}