<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectCategoryController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProjectTemplateController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\DailyTaskController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Models\EmailDigestQueue;
use App\Models\BatchedEmail;

// Public Auth endpoint
Route::post('/login', [AuthController::class, 'login']);

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    // User Management & Profile routes
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::get('/roles', [RolePermissionController::class, 'indexRoles']);
    Route::get('/permissions', [RolePermissionController::class, 'indexPermissions']);

    // Projects CRUD & status management
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/restore', [ProjectController::class, 'restore']);
    Route::post('/projects/{id}/statuses', [ProjectController::class, 'addStatus']);
    Route::delete('/projects/{id}/statuses', [ProjectController::class, 'deleteStatus']);

    // Project Categories
    Route::get('/project-categories', [ProjectCategoryController::class, 'index']);
    Route::post('/project-categories', [ProjectCategoryController::class, 'store']);
    Route::put('/project-categories/{id}', [ProjectCategoryController::class, 'update']);
    Route::delete('/project-categories/{id}', [ProjectCategoryController::class, 'destroy']);

    // Tasks CRUD
    Route::get('/projects/{projectId}/tasks', [TaskController::class, 'index']);
    Route::post('/projects/{projectId}/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);

    // Custom fields management
    Route::post('/projects/{projectId}/custom-fields', [CustomFieldController::class, 'storeDefinition']);
    Route::delete('/projects/{projectId}/custom-fields/{fieldId}', [CustomFieldController::class, 'deactivateDefinition']);
    Route::post('/tasks/{taskId}/custom-fields', [CustomFieldController::class, 'setValue']);

    // Attachments uploads and deletes
    Route::post('/tasks/{taskId}/attachments', [AttachmentController::class, 'store']);
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);

    // Folders routes
    Route::get('/projects/{projectId}/folders', [FolderController::class, 'index']);
    Route::post('/projects/{projectId}/folders', [FolderController::class, 'store']);
    Route::delete('/folders/{id}', [FolderController::class, 'destroy']);

    // Project files routes
    Route::get('/projects/{projectId}/project-files', [ProjectFileController::class, 'index']);
    Route::post('/projects/{projectId}/project-files', [ProjectFileController::class, 'store']);
    Route::get('/project-files/{id}/download', [ProjectFileController::class, 'download']);
    Route::delete('/project-files/{id}', [ProjectFileController::class, 'destroy']);

    // Notes routes
    Route::get('/projects/{projectId}/notes', [NoteController::class, 'index']);
    Route::post('/projects/{projectId}/notes', [NoteController::class, 'store']);
    Route::put('/notes/{id}', [NoteController::class, 'update']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);

    // Notifications management
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/testing-notifications-helper', [NotificationController::class, 'createTestingHelper']);

    // Command trigger route (Console simulator link)
    Route::post('/digest/send', function () {
        Artisan::call('digest:send');
        return response()->json(['message' => 'تم إرسال الملخص بنجاح']);
    });

    // Helper routes to fetch digest queue info
    Route::get('/digest/queue', function () {
        return response()->json(EmailDigestQueue::with('task')->get());
    });

    Route::get('/digest/emails', function () {
        return response()->json(BatchedEmail::orderBy('created_at', 'desc')->get());
    });

    // Project templates CRUD routes
    Route::get('/project-templates', [ProjectTemplateController::class, 'index']);
    Route::post('/project-templates', [ProjectTemplateController::class, 'store']);
    Route::put('/project-templates/{id}', [ProjectTemplateController::class, 'update']);
    Route::delete('/project-templates/{id}', [ProjectTemplateController::class, 'destroy']);
    Route::post('/project-templates/{id}/set-default', [ProjectTemplateController::class, 'setDefault']);

    // Task templates CRUD routes
    Route::get('/task-templates', [TaskTemplateController::class, 'index']);
    Route::post('/task-templates', [TaskTemplateController::class, 'store']);
    Route::put('/task-templates/{id}', [TaskTemplateController::class, 'update']);
    Route::delete('/task-templates/{id}', [TaskTemplateController::class, 'destroy']);
    Route::post('/task-templates/{id}/set-default', [TaskTemplateController::class, 'setDefault']);

    // Daily Tasks routes
    Route::get('/daily-tasks', [DailyTaskController::class, 'index']);
    Route::post('/daily-tasks', [DailyTaskController::class, 'store']);
    Route::put('/daily-tasks/{id}', [DailyTaskController::class, 'update']);
    Route::delete('/daily-tasks/{id}', [DailyTaskController::class, 'destroy']);
    Route::post('/daily-tasks/sync', [DailyTaskController::class, 'sync']);
});
