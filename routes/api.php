 <?php

 use App\Http\Controllers\ProjectController;
 use App\Http\Controllers\RepositoryController;
 use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::apiResource('repositories', RepositoryController::class);

Route::controller(ProjectController::class)->prefix('projects')
    ->group(function (){
        Route::get('/',  'index')->name('projects.index');
        Route::post('/', 'store')->name('projects.store');
        Route::get('/{id}', 'show')->name('projects.show');
        Route::put('/{id}', 'update')->name('projects.update');
        Route::delete('/{id}', 'destroy')->name('projects.destroy');
    });

 Route::controller(\App\Http\Controllers\GitController::class)
     ->prefix('repositories/{repoId}/git')
     ->group(function (){
         Route::get('branches','listBranches');
         Route::post('checkout','checkoutBranch');
         Route::post('run-commands','runMultipleCommands');
     });

