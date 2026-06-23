<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DateTimeInterface;

class Pipeline extends Model
{
    protected $fillable = ['name', 'description', 'stages'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stages' => 'array',
        ];
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function repositories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Repository::class);
    }
}
