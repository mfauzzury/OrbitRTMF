<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CrController;
use App\Http\Controllers\Api\DefectController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\ChangelogController;
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
use App\Http\Controllers\Api\RtmfScenarioController;
use App\Http\Controllers\Api\RtmfScenarioStepController;
use App\Http\Controllers\Api\RtmfScenarioAttachmentController;
use App\Http\Controllers\Api\RtmfScenarioStepLinkController;
use App\Http\Controllers\Api\RtmfFrontendApiEndpointController;
use App\Http\Controllers\Api\RtmfFrontendFeedbackController;
use App\Http\Controllers\Api\RtmfProjectController;
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

    // Defect Reporting (MantisBT proxy)
    Route::get('/defects/dashboard',  [DefectController::class, 'dashboard']);
    Route::get('/defects/log',        [DefectController::class, 'log']);
    Route::get('/defects/summary',    [DefectController::class, 'summary']);
    Route::get('/defects/categories', [DefectController::class, 'categories']);
    Route::get('/defects/trend',      [DefectController::class, 'trend']);

    // Page Catalog Tracking
    Route::get('/tracking/overview',   [TrackingController::class, 'overview']);
    Route::get('/tracking/by-module',  [TrackingController::class, 'byModule']);
    Route::get('/tracking/pages',      [TrackingController::class, 'pages']);
    Route::get('/tracking/modules',    [TrackingController::class, 'modules']);
    Route::get('/tracking/trend',      [TrackingController::class, 'trend']);

    // CR Tracking (MantisBT proxy — Category CR)
    Route::get('/cr/filters', [CrController::class, 'filters']);
    Route::get('/cr/log',     [CrController::class, 'log']);
    Route::get('/cr/summary', [CrController::class, 'summary']);
    Route::get('/cr/trend',   [CrController::class, 'trend']);

    // RTMF — read + project-member write (rtmf.view)
    // Project-level authorization is handled by denyIfCannotEdit in each controller.
    Route::middleware('permission:rtmf.view')->group(function () {
        Route::get('/rtmf/dashboard', [RtmfDashboardController::class, 'summary']);

        // Frontends
        Route::get('/rtmf-frontends/export/csv', [RtmfFrontendController::class, 'export']);
        Route::post('/rtmf-frontends/import', [RtmfFrontendController::class, 'import']);
        Route::apiResource('rtmf-frontends', RtmfFrontendController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::get('/rtmf-frontends/{id}/incoming-links', [RtmfFrontendController::class, 'incomingLinks']);
        Route::get('/rtmf-frontends/{id}/attachments', [RtmfFrontendAttachmentController::class, 'index']);
        Route::post('/rtmf-frontends/{id}/attachments', [RtmfFrontendAttachmentController::class, 'store']);
        Route::patch('/rtmf-frontends/{id}/attachments/{attachmentId}', [RtmfFrontendAttachmentController::class, 'update']);
        Route::delete('/rtmf-frontends/{id}/attachments/{attachmentId}', [RtmfFrontendAttachmentController::class, 'destroy']);
        Route::get('/rtmf-frontends/{frontendId}/items', [RtmfFrontendItemController::class, 'index']);
        Route::post('/rtmf-frontends/{frontendId}/items', [RtmfFrontendItemController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/items/{itemId}', [RtmfFrontendItemController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/items/{itemId}', [RtmfFrontendItemController::class, 'destroy']);
        Route::get('/rtmf-frontends/{frontendId}/scenario-groups', [RtmfFrontendScenarioGroupController::class, 'index']);
        Route::post('/rtmf-frontends/{frontendId}/scenario-groups', [RtmfFrontendScenarioGroupController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}', [RtmfFrontendScenarioGroupController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}', [RtmfFrontendScenarioGroupController::class, 'destroy']);
        Route::post('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows', [RtmfFrontendScenarioRowController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows/{rowId}', [RtmfFrontendScenarioRowController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/scenario-groups/{groupId}/rows/{rowId}', [RtmfFrontendScenarioRowController::class, 'destroy']);
        Route::get('/rtmf-frontends/{frontendId}/feedbacks', [RtmfFrontendFeedbackController::class, 'index']);
        Route::put('/rtmf-frontends/{frontendId}/feedbacks/{role}', [RtmfFrontendFeedbackController::class, 'upsert']);
        Route::get('/rtmf-frontends/{frontendId}/api-endpoints', [RtmfFrontendApiEndpointController::class, 'index']);
        Route::post('/rtmf-frontends/{frontendId}/api-endpoints', [RtmfFrontendApiEndpointController::class, 'store']);
        Route::patch('/rtmf-frontends/{frontendId}/api-endpoints/{endpointId}', [RtmfFrontendApiEndpointController::class, 'update']);
        Route::delete('/rtmf-frontends/{frontendId}/api-endpoints/{endpointId}', [RtmfFrontendApiEndpointController::class, 'destroy']);

        // Modules
        Route::apiResource('rtmf-modules', RtmfModuleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::apiResource('rtmf-modules.sub-modules', RtmfSubModuleController::class)->only(['index', 'show', 'store', 'update', 'destroy'])->parameters(['sub-modules' => 'sub_module']);
        Route::get('/rtmf-modules/{moduleId}/photos', [RtmfModulePhotoController::class, 'index']);
        Route::post('/rtmf-modules/{moduleId}/photos', [RtmfModulePhotoController::class, 'store']);
        Route::delete('/rtmf-modules/{moduleId}/photos/{photoId}', [RtmfModulePhotoController::class, 'destroy']);
        Route::post('/rtmf-modules/{moduleId}/sub-modules/reorder', [RtmfSubModuleController::class, 'reorder']);
        Route::get('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos', [RtmfSubModulePhotoController::class, 'index']);
        Route::post('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos', [RtmfSubModulePhotoController::class, 'store']);
        Route::delete('/rtmf-modules/{moduleId}/sub-modules/{subModuleId}/photos/{photoId}', [RtmfSubModulePhotoController::class, 'destroy']);

        // Actors
        Route::apiResource('rtmf-actors', RtmfActorController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

        // Scenarios
        Route::get('/rtmf-scenarios', [RtmfScenarioController::class, 'index']);
        Route::get('/rtmf-scenarios/{scenarioId}', [RtmfScenarioController::class, 'show']);
        Route::post('/rtmf-scenarios', [RtmfScenarioController::class, 'store']);
        Route::patch('/rtmf-scenarios/{scenarioId}', [RtmfScenarioController::class, 'update']);
        Route::delete('/rtmf-scenarios/{scenarioId}', [RtmfScenarioController::class, 'destroy']);
        Route::get('/rtmf-scenarios/{scenarioId}/steps', [RtmfScenarioStepController::class, 'index']);
        Route::post('/rtmf-scenarios/{scenarioId}/steps', [RtmfScenarioStepController::class, 'store']);
        Route::patch('/rtmf-scenarios/{scenarioId}/steps/{stepId}', [RtmfScenarioStepController::class, 'update']);
        Route::delete('/rtmf-scenarios/{scenarioId}/steps/{stepId}', [RtmfScenarioStepController::class, 'destroy']);
        Route::post('/rtmf-scenarios/{scenarioId}/steps/reorder', [RtmfScenarioStepController::class, 'reorder']);
        Route::get('/rtmf-scenarios/{scenarioId}/attachments', [RtmfScenarioAttachmentController::class, 'index']);
        Route::post('/rtmf-scenarios/{scenarioId}/attachments', [RtmfScenarioAttachmentController::class, 'store']);
        Route::patch('/rtmf-scenarios/{scenarioId}/attachments/{attachmentId}', [RtmfScenarioAttachmentController::class, 'update']);
        Route::delete('/rtmf-scenarios/{scenarioId}/attachments/{attachmentId}', [RtmfScenarioAttachmentController::class, 'destroy']);
        Route::post('/rtmf-scenarios/{scenarioId}/steps/{stepId}/links', [RtmfScenarioStepLinkController::class, 'store']);
        Route::patch('/rtmf-scenarios/{scenarioId}/steps/{stepId}/links/{linkId}', [RtmfScenarioStepLinkController::class, 'update']);
        Route::delete('/rtmf-scenarios/{scenarioId}/steps/{stepId}/links/{linkId}', [RtmfScenarioStepLinkController::class, 'destroy']);

        // URL paths
        Route::post('/rtmf-url-paths/{rtmf_url_path}/snapshot', [RtmfUrlPathController::class, 'captureSnapshot']);
        Route::apiResource('rtmf-url-paths', RtmfUrlPathController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

        // Projects (read only — management stays in rtmf.manage)
        Route::apiResource('rtmf-projects', RtmfProjectController::class)->only(['index', 'show']);
        Route::get('/rtmf-projects/{id}/members', [RtmfProjectController::class, 'members']);
        Route::get('/rtmf-projects/{id}/candidates', [RtmfProjectController::class, 'candidates']);
    });

    // RTMF — global project management (rtmf.manage, system-admin only)
    Route::middleware('permission:rtmf.manage')->group(function () {
        Route::apiResource('rtmf-projects', RtmfProjectController::class)->only(['store', 'update', 'destroy']);
        Route::post('/rtmf-projects/{id}/members', [RtmfProjectController::class, 'addMember']);
        Route::patch('/rtmf-projects/{id}/members/{userId}', [RtmfProjectController::class, 'updateMember']);
        Route::delete('/rtmf-projects/{id}/members/{userId}', [RtmfProjectController::class, 'removeMember']);
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

    Route::get('/changelog', [ChangelogController::class, 'show']);
    Route::put('/changelog', [ChangelogController::class, 'update']);
});
