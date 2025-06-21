<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repository Activity Log Model
 *
 * Tracks user activities related to repositories.
 *
 * @property int $id
 * @property int $user_id
 * @property int $repository_id
 * @property string $type
 * @property string|null $command
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Repository $repository
 */
class RepoActivityLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'repo_activity_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'repository_id',
        'type',
        'command',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who performed the activity.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the repository related to this activity.
     *
     * @return BelongsTo
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Create a new repository activity log.
     *
     * @param int $userId User ID who performed the activity
     * @param int $repositoryId Repository ID where the activity was performed
     * @param string $type Type of activity (e.g., 'git-checkout', 'run-command', 'upload-dist')
     * @param string|null $command Command that was executed (if applicable)
     * @return RepoActivityLog
     */
    public static function makeRepoLogs(int $userId, int $repositoryId, string $type, ?string $command = null): RepoActivityLog
    {
        return self::create([
            'user_id' => $userId,
            'repository_id' => $repositoryId,
            'type' => $type,
            'command' => $command,
        ]);
    }
}
