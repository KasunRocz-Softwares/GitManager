<?php

use Illuminate\Support\Facades\Route;


Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');


Route::middleware(['auth', 'verified'])->group(function (){
    Route::view('/', 'dashboard')->name('dashboard');
    Route::view('/projects', 'projects')->name('projects');
    Route::view('/repositories','repositories')->name('repositories');
    Route::view('git','git-functions')->name('git-functions');
});


require __DIR__.'/auth.php';
