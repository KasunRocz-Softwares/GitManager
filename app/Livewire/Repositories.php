<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Repository;
use Livewire\Component;

class Repositories extends Component
{
    public $repositories;
    public $projects;
    public $selectedProject;
    public $repoName;
    public $repoPath;
    public $codeBaseType = 'Laravel';
    public $hasDistFolder = false;

    public function mount()
    {
        $this->projects = Project::all();
    }

    public function render()
    {
        $this->repositories = Repository::where('project_id', $this->selectedProject)->get();
        return view('livewire.repositories');
    }

    public function saveRepository()
    {
        $this->validate([
            'selectedProject' => 'required|exists:projects,id',
            'repoName' => 'required|string',
            'repoPath' => 'required|string',
            'codeBaseType' => 'required|string|in:Laravel,NodeJS,React/Vue',
            'hasDistFolder' => 'boolean',
        ]);

        Repository::create([
            'project_id' => $this->selectedProject,
            'name' => $this->repoName,
            'repo_path' => $this->repoPath,
            'code_base_type' => $this->codeBaseType,
            'has_dist_folder' => $this->hasDistFolder,
        ]);

        session()->flash('message', 'Repository created successfully.');
        $this->reset(['repoName', 'repoPath']);
        $this->codeBaseType = 'Laravel';
        $this->hasDistFolder = false;
    }
}
