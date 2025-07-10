<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Terminal extends Model
{
    

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'regencity',
        'province',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        ];
    }

    /**
     * Get all departure routes from this terminal.
     */
    public function departureRoutes(): HasMany
    {
        return $this->hasMany(RoutesSchedule::class, 'departure_id');
    }

    /**
     * Get all arrival routes to this terminal.
     */
    public function arrivalRoutes(): HasMany
    {
        return $this->hasMany(RoutesSchedule::class, 'arrival_id');
    }
}