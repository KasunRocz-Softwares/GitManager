<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class Projects extends Component
{
    public $projects = [];
    public $name;
    public $username;
    public $password;
    public $host;

    public function mount()
    {
        $this->loadProjects();
    }

    public function loadProjects()
    {
        $response = Http::get('/api/projects');
        if ($response->successful()) {
            $this->projects = $response->json();
        }
    }

    public function saveProject()
    {
        $this->validate([
            'name' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
            'host' => 'required|ip',
        ]);

        $response = Http::post('/api/projects', [
            'name' => $this->name,
            'username' => $this->username,
            'password' => $this->password,
            'host' => $this->host,
        ]);

        if ($response->successful()) {
            session()->flash('message', 'Project created successfully.');
            $this->reset(['name', 'username', 'password', 'host']);
            $this->loadProjects();
        } else {
            session()->flash('error', 'Failed to create project.');
        }
    }

    public function render()
    {
        return view('livewire.projects');
    }
}

