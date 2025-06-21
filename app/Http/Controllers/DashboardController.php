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
        $query = RepoActivityLog::whereDate('repo_activity_logs.created_at', '>=', Carbon::now()->subDays(30));

        if (!Auth::user()->is_admin) {
            $query->where('user_id', Auth::id());

            // For non-admin users, just return their own activity
            $logs = $query->selectRaw('DATE(repo_activity_logs.created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
                'users' => null // No need for user breakdown for non-admin
            ]);
        }

        // For admin users, get activity grouped by user and date
        $userLogs = RepoActivityLog::whereDate('repo_activity_logs.created_at', '>=', Carbon::now()->subDays(30))
            ->join('users', 'repo_activity_logs.user_id', '=', 'users.id')
            ->selectRaw('DATE(repo_activity_logs.created_at) as date, users.name as user_name, users.id as user_id, COUNT(*) as count')
            ->groupBy('date', 'users.id', 'users.name')
            ->orderBy('date', 'ASC')
            ->get();

        // Get all unique dates from the logs
        $dates = $userLogs->pluck('date')->unique()->values()->all();

        // Get all unique users from the logs
        $users = $userLogs->pluck('user_name', 'user_id')->unique()->all();

        // Get total activity per date (for the main chart)
        $totalLogs = $query->selectRaw('DATE(repo_activity_logs.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $totalLogs,
            'userActivities' => $userLogs,
            'dates' => $dates,
            'users' => $users
        ]);
    }
}
