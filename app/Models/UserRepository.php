<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRepository extends Model
{
    protected $table = 'user_repositories';
    protected $fillable = [
        'user_id',
        'repository_id',
    ];

    /**
     * Get the user that owns the repository.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the repository associated with the user.
     */
    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }
}
