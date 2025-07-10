<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'types_id',
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
     * Get the type that owns the tag.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TagType::class, 'types_id');
    }

    /**
     * Get all buses that have this tag.
     */
    public function buses(): BelongsToMany
    {
        return $this->belongsToMany(Bus::class, 'tags_con', 'tags_id', 'buses_id');
    }
}