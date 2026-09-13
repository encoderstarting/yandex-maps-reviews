<?php

use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationReviewController;
use App\Http\Controllers\Api\V1\OrganizationSyncController;
use App\Http\Controllers\Api\V1\OrganizationSyncStatusController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', fn (Request $request): UserResource => new UserResource($request->user()))
        ->name('api.v1.user');

    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->name('api.v1.organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->name('api.v1.organizations.store');
    Route::get('/organizations/{organizationId}', [OrganizationController::class, 'show'])
        ->whereNumber('organizationId')
        ->name('api.v1.organizations.show');
    Route::get('/organizations/{organizationId}/reviews', OrganizationReviewController::class)
        ->whereNumber('organizationId')
        ->name('api.v1.organizations.reviews.index');
    Route::get('/organizations/{organizationId}/sync-status', OrganizationSyncStatusController::class)
        ->whereNumber('organizationId')
        ->name('api.v1.organizations.sync-status.show');
    Route::post('/organizations/{organizationId}/sync', OrganizationSyncController::class)
        ->whereNumber('organizationId')
        ->name('api.v1.organizations.sync.store');
});
