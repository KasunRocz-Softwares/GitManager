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


Route::middleware('auth:api')->apiResource('repositories', RepositoryController::class);

Route::controller(DashboardController::class)->middleware('auth:api')
->group(function (){
    Route::get('dashboard','dashboard');
});

Route::controller(ProjectController::class)->middleware('auth:api')->prefix('projects')
    ->group(function (){
        Route::get('/',  'index')->name('projects.index');
        Route::post('/', 'store')->name('projects.store');
        Route::get('/{id}', 'show')->name('projects.show');
        Route::put('/{id}', 'update')->name('projects.update');
        Route::delete('/{id}', 'destroy')->name('projects.destroy');
    });

    Route::controller(UserController::class)->middleware('auth:api')->prefix('users')
    ->group(function (){
        Route::get('/',  'index')->name('user.index');
        Route::post('/', 'store')->name('user.store');
        Route::post('/repo/store', 'storeUserRepo')->name('user.repo.store');
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

