<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineRun extends Model
{
    protected $table = 'pipeline_runs';

    protected $fillable = [
        'repository_id',
        'pipeline_id',
        'user_id',
        'status',
        'stage_statuses',
        'logs',
        'variables',
    ];

    protected $casts = [
        'stage_statuses' => 'array',
        'variables' => 'array',
    ];

    /**
     * Get the repository associated with the run.
     */
    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

    /**
     * Get the pipeline definition associated with the run.
     */
    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the user who triggered the run.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
