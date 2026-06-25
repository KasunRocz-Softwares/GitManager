<?php

namespace App\Jobs;

use App\Models\PipelineRun;
use App\Services\GitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Exception;

class RunPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $runId;

    /**
     * Create a new job instance.
     */
    public function __construct($runId)
    {
        $this->runId = $runId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $run = PipelineRun::with(['repository.project', 'pipeline'])->find($this->runId);
        if (!$run) {
            return;
        }

        $redisKey = 'pipeline_run:' . $this->runId . ':logs';

        // 1. Initialize Redis Log Cache
        $initialLogs = "[SYSTEM] Initializing background pipeline execution...\n";
        Redis::set($redisKey, $initialLogs);
        Redis::expire($redisKey, 3600); // Expire after 1 hour

        $run->status = 'running';
        $run->logs = null; // Stored in Redis during run
        
        $pipeline = $run->pipeline;
        $stages = $pipeline ? ($pipeline->stages ?? []) : [];
        
        if (empty($stages)) {
            $run->status = 'failed';
            $errorLog = "[ERROR] Pipeline has no stages defined.\n";
            Redis::append($redisKey, $errorLog);
            
            // Persist final logs
            $run->logs = Redis::get($redisKey);
            $run->save();
            Redis::del($redisKey);
            return;
        }

        $stageStatuses = [];
        foreach ($stages as $idx => $stage) {
            $stageStatuses[$idx] = 'pending';
        }
        $run->stage_statuses = $stageStatuses;
        $run->save();

        // 2. Initialize Git Service
        $repository = $run->repository;
        $project = $repository->project;
        $sshHost = $project->host;
        $sshUsername = $project->username;
        $sshPassword = $project->password;
        $repoPath = $repository->repo_path;

        try {
            $gitService = new GitService($sshHost, $sshUsername, $sshPassword, $repoPath);
        } catch (Exception $e) {
            $run->status = 'failed';
            $errorLog = "[ERROR] Failed to initialize SSH connection: " . $e->getMessage() . "\n";
            Redis::append($redisKey, $errorLog);
            
            // Persist final logs
            $run->logs = Redis::get($redisKey);
            $run->save();
            Redis::del($redisKey);
            return;
        }

        $variables = $run->variables ?? [];

        for ($i = 0; $i < count($stages); $i++) {
            $stage = $stages[$i];
            
            // Set current stage status to running
            $stageStatuses[$i] = 'running';
            $run->stage_statuses = $stageStatuses;
            $run->save();

            $stageHeader = "\n[Stage " . ($i + 1) . "/" . count($stages) . "] Running stage: \"" . $stage['name'] . "\"...\n";
            Redis::append($redisKey, $stageHeader);

            // Substitute command variables
            $commands = $stage['commands'] ?? [];
            $substitutedCommands = [];
            foreach ($commands as $cmd) {
                $command = $cmd;
                foreach ($variables as $key => $value) {
                    $command = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/', $value, $command);
                }
                $substitutedCommands[] = $command;
            }

            $commandsLog = "[COMMANDS] " . implode(' && ', $substitutedCommands) . "\n";
            Redis::append($redisKey, $commandsLog);

            // Run AI Guard check if enabled
            if (env('AI_IS_ACTIVE', false)) {
                Redis::append($redisKey, "[SYSTEM] AI Guard checking commands...\n");
                try {
                    foreach ($substitutedCommands as $command) {
                        $response = Http::withToken(env('AI_ACCESS_TOKEN'))
                            ->post(env('AI_BASE_URL') . '/git_manager_guard', [
                                'command' => $command,
                            ]);
                        $aiResult = $response->json();
                        if (!empty($aiResult['data']['is_risk']) && $aiResult['data']['is_risk'] === true) {
                            $reason = $aiResult['data']['reason'] ?? 'Unknown risk';
                            throw new Exception("AI Guard blocked command: " . $reason);
                        }
                    }
                } catch (Exception $e) {
                    $stageStatuses[$i] = 'failed';
                    $run->stage_statuses = $stageStatuses;
                    $run->status = 'failed';
                    
                    $aiErrorLog = "[ERROR] AI Guard Blocked Stage \"" . $stage['name'] . "\": " . $e->getMessage() . "\n";
                    Redis::append($redisKey, $aiErrorLog);

                    // Persist final logs
                    $run->logs = Redis::get($redisKey);
                    $run->save();
                    Redis::del($redisKey);
                    return;
                }
            }

            // Execute commands via SSH
            try {
                $output = $gitService->runMultipleCommands($substitutedCommands);
                $stageStatuses[$i] = 'success';
                $run->stage_statuses = $stageStatuses;
                $run->save();

                $successLog = "[SUCCESS] Stage \"" . $stage['name'] . "\" completed successfully.\n";
                $outputLog = "[OUTPUT] " . ($output ?: 'No output') . "\n";
                Redis::append($redisKey, $successLog . $outputLog);
            } catch (Exception $e) {
                $stageStatuses[$i] = 'failed';
                $run->stage_statuses = $stageStatuses;
                $run->status = 'failed';

                $failLog = "[ERROR] Stage \"" . $stage['name'] . "\" failed!\n";
                $reasonLog = "[REASON] " . $e->getMessage() . "\n";
                Redis::append($redisKey, $failLog . $reasonLog);

                // Persist final logs
                $run->logs = Redis::get($redisKey);
                $run->save();
                Redis::del($redisKey);
                return;
            }
        }

        // All stages succeeded!
        $run->status = 'success';
        
        $completionLog = "\n[SYSTEM] Pipeline completed all stages successfully!\n";
        Redis::append($redisKey, $completionLog);

        // Persist final logs and clean up Redis
        $run->logs = Redis::get($redisKey);
        $run->save();
        Redis::del($redisKey);
    }
}
