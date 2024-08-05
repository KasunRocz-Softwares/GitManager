<?php

namespace App\Livewire;

use App\Models\Repository;
use App\Services\GitService;
use Livewire\Component;

class GitFunctions extends Component
{
    public $repoId;
    public $branchName;
    public $commands = [];

    public function render()
    {
        return view('livewire.git-functions');
    }

    public function runCommands()
    {
        $this->validate([
            'commands' => 'required|array',
            'commands.*' => 'string',
        ]);

        $repository = Repository::findOrFail($this->repoId);
        $gitService = new GitService($repository->project->host, $repository->project->username, $repository->project->password, $repository->repo_path);

        try {
            $output = $gitService->runMultipleCommands($this->commands);
            session()->flash('message', 'Commands executed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function createBranch()
    {
        $this->validate([
            'branchName' => 'required|string',
        ]);

        $repository = Repository::findOrFail($this->repoId);
        $gitService = new GitService($repository->project->host, $repository->project->username, $repository->project->password, $repository->repo_path);

        try {
            $gitService->createBranch($this->branchName);
            session()->flash('message', 'Branch created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }
}
