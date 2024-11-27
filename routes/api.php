<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\JwtMiddleware;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\EmployeeController;

Route::post('login', [AuthController::class, 'login'])->name('login')->withoutMiddleware([JwtMiddleware::class]);

Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('company')->group(function() {
        Route::post('/', [CompanyController::class, 'index']);
        Route::post('create', [CompanyController::class, 'create']);
        Route::put('update/{id}', [CompanyController::class, 'update']);
        Route::delete('delete/{id}', [CompanyController::class, 'destroy']);
    });

    Route::prefix('employee')->group(function() {
        Route::post('/', [EmployeeController::class, 'index']);
        Route::post('create', [EmployeeController::class, 'create']);
        Route::get('detail/{id}', [EmployeeController::class, 'show']);
        Route::put('update/{id}', [EmployeeController::class, 'update']);
        Route::delete('delete/{id}', [EmployeeController::class, 'destroy']);
        Route::get('profile', [EmployeeController::class, 'profile']);
        Route::post('update-profile', [EmployeeController::class, 'updateSelfData']);
    });
});
