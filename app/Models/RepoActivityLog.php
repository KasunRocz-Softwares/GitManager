<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepoActivityLog extends Model
{
    protected $table = 'repo_activity_logs';

    protected $fillable = [
        'user_id',
        'repository_id',
        'type',
        'command',
    ];

    /**
     * Get the user who performed the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the repository related to this activity.
     */
    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

    public static function makeRepoLogs($user, $repo, $type, $command = null)
    {
        self::create([
            'user_id'=> $user,
            'repository_id'=> $repo,
            'type'=> $type,
            'command'=> $command,
        ]);
    }
}
