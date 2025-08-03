<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\RoleManagementMiddleware;


Route::post('addUser', [AuthController::class, 'CreateUser']);
Route::post('login', [AuthController::class, 'Login']);


 Route::middleware(['auth:api', RoleManagementMiddleware::class])->group(function () {
    Route::get('getuser', [AuthController::class, 'getUser']);
    Route::post('addBulkusers', [AuthController::class, 'addBulkusers']);
    Route::post('updateUser', [AuthController::class, 'updateUser']);
    Route::post('deleteUser', [AuthController::class, 'deleteUser']);
});