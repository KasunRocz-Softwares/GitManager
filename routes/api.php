 <?php

 use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
 use App\Http\Controllers\RepositoryController;
 use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/* Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); */

Route::controller(\App\Http\Controllers\Auth\AuthController::class)->group(function (){
    Route::post('login','login')->name('login');
});


Route::middleware('auth:api')->put('repositories/{id}/toggle-status', [RepositoryController::class, 'toggleRepositoryStatus']);
Route::middleware('auth:api')->apiResource('repositories', RepositoryController::class);
Route::middleware('auth:api')->apiResource('pipelines', \App\Http\Controllers\PipelineController::class);

Route::middleware('auth:api')->group(function () {
    Route::post('repositories/{repoId}/run-pipeline', [\App\Http\Controllers\PipelineRunController::class, 'runPipeline']);
    Route::get('repositories/{repoId}/latest-pipeline-run', [\App\Http\Controllers\PipelineRunController::class, 'latestRun']);
    Route::get('repositories/{repoId}/pipeline-runs', [\App\Http\Controllers\PipelineRunController::class, 'index']);
    Route::get('pipeline-runs/{id}', [\App\Http\Controllers\PipelineRunController::class, 'show']);
});

Route::controller(DashboardController::class)->middleware('auth:api')->prefix('dashboard')
->group(function (){
    Route::get('/','dashboard');
    Route::get('activity-chart','activityChart');
});

Route::controller(ProjectController::class)->middleware('auth:api')->prefix('projects')
    ->group(function (){
        Route::get('/',  'index')->name('projects.index');
        Route::post('/', 'store')->name('projects.store');
        Route::get('/{id}', 'show')->name('projects.show');
        Route::put('/{id}', 'update')->name('projects.update');
        Route::put('/{id}/toggle-status', 'toggleProjectStatus');
        Route::delete('/{id}', 'destroy')->name('projects.destroy');
    });

    Route::controller(UserController::class)->middleware('auth:api')->prefix('users')
    ->group(function (){
        Route::get('/',  'index');
        Route::post('/', 'store');
        Route::get('/{user}', 'getUser');
        Route::put('/{user}', 'updateUser');
        Route::put('/{user}/toggle-status', 'toggleUserStatus');
        Route::post('/repo/store', 'storeUserRepo');
    });

    Route::middleware('auth:api')->group(function () {
        Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
        Route::get('permissions', [\App\Http\Controllers\PermissionController::class, 'index']);
        Route::get('profile', function (Request $request) {
            $user = $request->user();
            $role = $user->roles()->first()?->name ?? 'User';
            $permissions = $user->hasRole('Super Admin')
                ? \Spatie\Permission\Models\Permission::pluck('name')->toArray()
                : $user->getAllPermissions()->pluck('name')->toArray();

            $userArray = $user->toArray();
            $userArray['role'] = $role;
            $userArray['permissions'] = $permissions;
            $userArray['is_admin'] = $user->hasRole('Super Admin');

            return response()->json([
                'success' => true,
                'user' => $userArray,
            ]);
        });

        Route::put('profile', [\App\Http\Controllers\ProfileController::class, 'updateProfile']);
        Route::put('profile/security', [\App\Http\Controllers\ProfileController::class, 'updatePassword']);
        Route::post('profile/support', [\App\Http\Controllers\ProfileController::class, 'createSupportTicket']);
        Route::get('profile/support', [\App\Http\Controllers\ProfileController::class, 'indexSupportTickets']);
        Route::get('support-tickets', [\App\Http\Controllers\ProfileController::class, 'indexAllSupportTickets']);
        Route::put('support-tickets/{id}/status', [\App\Http\Controllers\ProfileController::class, 'updateSupportTicketStatus']);
    });

 Route::controller(\App\Http\Controllers\GitController::class)
    ->middleware('auth:api')
     ->prefix('repositories/{repoId}/git')
     ->group(function (){
         Route::get('branches','listBranches');
         Route::post('checkout','checkoutBranch');
         Route::post('run-commands','runMultipleCommands');
         Route::get('current-branch','currentBranch');
     });


