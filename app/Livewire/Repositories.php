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
        ]);

        Repository::create([
            'project_id' => $this->selectedProject,
            'name' => $this->repoName,
            'repo_path' => $this->repoPath,
        ]);

        session()->flash('message', 'Repository created successfully.');
        $this->reset(['repoName', 'repoPath']);
    }
}
