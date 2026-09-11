<?php

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request): UserResource => new UserResource($request->user()))
        ->name('api.v1.user');
});
