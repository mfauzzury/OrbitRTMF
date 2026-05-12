<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DevelopersGuideController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RtmfActorController;
use App\Http\Controllers\Api\RtmfFrontendController;
use App\Http\Controllers\Api\RtmfModuleController;
use App\Http\Controllers\Api\RtmfModulePhotoController;
use App\Http\Controllers\Api\RtmfSubModuleController;
use App\Http\Controllers\Api\RtmfSubModulePhotoController;
use App\Http\Controllers\Api\RtmfFrontendAttachmentController;
use App\Http\Controllers\Api\RtmfFrontendItemController;
use App\Http\Controllers\Api\RtmfDashboardController;
use App\Http\Controllers\Api\RtmfFrontendScenarioGroupController;
use App\Http\Controllers\Api\RtmfFrontendScenarioRowController;
use App\Http\Controllers\Api\RtmfUrlPathController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth)
Route::prefix('public')->group(function () {
    Route::get('/site', [PublicController::class, 'site']);
    Route::get('/pages/frontpage', [PublicController::class, 'frontpage']);
    Route::get('/pages/{slug}', [PublicController::class, 'pageBySlug']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::post('/password', [AuthController::class, 'changePassword']);
        Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/avatar', [AuthController::class, 'removeAvatar']);
    });
});

// Settings GET is public (used by SPA before auth)
Route::get('/settings', [SettingController::class, 'index']);

// Protected admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', PostController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('pages', PageController::class);
    Route::apiResource('users', UserController::class);
    Route::get('/external/users', [UserController::class, 'externalIndex']);
    Route::apiResource('roles', RoleController::class);
    // RTMF — read (rtmf.view)
    Route::middleware('permission:rtmf.view')->group(function () {
        Route::get('/rtmf/dashboard', [RtmfDashboardController::class, 'summary']);
        Route::get('/rtmf-frontends/{id}/attachments', [RtmfFrontendAttachmentController::class, 'index']);
        Route::get('/rtmf-frontends/{frontendId}/items', [RtmfFrontendItemController::class, 'index']);
        Route::get('/rtmf-frontends/{frontendId}/scenario-groups', [RtmfFrontendScenarioGroupController::class, 'index']);
        Route::apiResource('rtmf-frontends', RtmfFrontendController::class)->only(['index', 'show']);
        Route::apiResource('rtmf-modules', RtmfModuleController::class)->only(['index', 'show']);
        Route::apiResource('rtmf-modules.sub-modules', RtmfSubModuleController::class)->only(['index', 'show'])->parameters(['sub-modules' => 'sub_module']);
        Route::apiResource('rtmf-actors', RtmfActorController::class)->only(['index', 'show']);
        Route::apiResource('rtmf-url-paths', RtmfUrlPathController::class)->only(['index', 'show']);
        Route::get('/rtmf-frontends/export/csv', [RtmfFrontendController::class, 'export']);
        Route::get('/rtmf-modules/{moduleId}/photos', [RtmfModulePhotoController::class, 'index']);
        Route::get('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos', [RtmfSubModulePhotoController::class, 'index']);
    });

    // RTMF — write (rtmf.manage)
    Route::middleware('permission:rtmf.manage')->group(function () {
        Route::post('/rtmf-frontends/{id}/attachments', [RtmfFrontendAttachmentController::class, 'store']);
        Route::patch('/rtmf-frontends/{id}/attachments/{attachmentId}', [RtmfFrontendAttachmentController::class, 'update']);
        Route::delete('/rtmf-frontends/{id}/attachments/{attachmentId}', [RtmfFrontendAttachmentController::class, 'destroy']);
        Route::post('/rtmf-frontends/{frontendId}/items', [RtmfFrontendItemController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/items/{itemId}', [RtmfFrontendItemController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/items/{itemId}', [RtmfFrontendItemController::class, 'destroy']);
        Route::post('/rtmf-frontends/{frontendId}/scenario-groups', [RtmfFrontendScenarioGroupController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}', [RtmfFrontendScenarioGroupController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}', [RtmfFrontendScenarioGroupController::class, 'destroy']);
        Route::post('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows', [RtmfFrontendScenarioRowController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows/{rowId}', [RtmfFrontendScenarioRowController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows/{rowId}', [RtmfFrontendScenarioRowController::class, 'destroy']);
        Route::apiResource('rtmf-frontends', RtmfFrontendController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('rtmf-modules', RtmfModuleController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('rtmf-modules.sub-modules', RtmfSubModuleController::class)->only(['store', 'update', 'destroy'])->parameters(['sub-modules' => 'sub_module']);
        Route::post('/rtmf-modules/{moduleId}/photos', [RtmfModulePhotoController::class, 'store']);
        Route::delete('/rtmf-modules/{moduleId}/photos/{photoId}', [RtmfModulePhotoController::class, 'destroy']);
        Route::post('/rtmf-modules/{moduleId}/sub-modules/reorder', [RtmfSubModuleController::class, 'reorder']);
        Route::post('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos', [RtmfSubModulePhotoController::class, 'store']);
        Route::delete('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos/{photoId}', [RtmfSubModulePhotoController::class, 'destroy']);
        Route::apiResource('rtmf-actors', RtmfActorController::class)->only(['store', 'update', 'destroy']);
        Route::post('/rtmf-url-paths/{rtmf_url_path}/snapshot', [RtmfUrlPathController::class, 'captureSnapshot']);
        Route::apiResource('rtmf-url-paths', RtmfUrlPathController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::put('/media/{media}', [MediaController::class, 'update']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);

    Route::put('/settings', [SettingController::class, 'update']);
    Route::get('/settings/admin-menu-prefs', [SettingController::class, 'adminMenuPrefs']);
    Route::put('/settings/admin-menu-prefs', [SettingController::class, 'updateAdminMenuPrefs']);
    Route::get('/settings/storefront-menu', [SettingController::class, 'storefrontMenu']);
    Route::put('/settings/storefront-menu', [SettingController::class, 'updateStorefrontMenu']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/developers-guide', [DevelopersGuideController::class, 'show']);
    Route::put('/developers-guide', [DevelopersGuideController::class, 'update']);
});
