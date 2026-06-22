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
    public function dashboard(Request $request)
    {
        if (!$request->user()->can('view_dashboard')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $repoCount = Repository::count();
        $projectCount = Project::count();
        $userCount = User::count();

        return response()->json([
            "repositories_count"=> $repoCount,
            "projects_count"=> $projectCount,
            "user_count"=> $userCount
        ]);
    }

    public function activityChart(Request $request)
    {
        $userId = $request->input('user_id') ?? $_GET['user_id'] ?? null;
        $startDate = $request->input('start_date') ?? $_GET['start_date'] ?? null;
        $endDate = $request->input('end_date') ?? $_GET['end_date'] ?? null;

        $query = RepoActivityLog::query();

        // Apply User Filter
        if (Auth::user()->can('view_users')) {
            if ($userId && $userId !== 'all') {
                $query->where('repo_activity_logs.user_id', $userId);
            }
        } else {
            $query->where('repo_activity_logs.user_id', Auth::id());
        }

        // Apply Date Range Filter
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $query->whereBetween('repo_activity_logs.created_at', [$start, $end]);
        } else {
            $query->whereDate('repo_activity_logs.created_at', '>=', Carbon::now()->subDays(30));
        }

        $logs = $query->selectRaw('DATE(repo_activity_logs.created_at) as date, repo_activity_logs.user_id, users.name as user_name, COUNT(*) as count')
            ->leftJoin('users', 'users.id', '=', 'repo_activity_logs.user_id')
            ->groupBy('date', 'repo_activity_logs.user_id', 'users.name')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}
