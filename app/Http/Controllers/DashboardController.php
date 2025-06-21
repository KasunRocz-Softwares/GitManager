<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RepoActivityLog;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

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
    public function activityChart()
    {
        $query = RepoActivityLog::whereDate('created_at', '>=', Carbon::now()->subDays(30));

        if (!Auth::user()->is_admin) {
            $query->where('user_id', Auth::id());
        }

        $logs = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
