<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $repoCount = Repository::count();
        $projectCount = Project::count();
        $userCount = User::count();

        return response()->json([
            "repositories_count"=> $repoCount,
            "projects_count"=> $projectCount,
            "user_count"=> $userCount
        ]);
    }
}
