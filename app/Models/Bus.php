<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bus extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'plate_number',
        'status',
        'description',
        'thumbnail_pic',
        'details_pic',
        'capacity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * Get all tags associated with the bus.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tags_cons', 'buses_id', 'tags_id')
                    ->using(TagCon::class);
    }

    /**
     * Get all routes to this bus.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(RoutesSchedule::class, 'buses_id');
    }
}