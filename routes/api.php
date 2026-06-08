<?php

use App\Domains\Settings\ManageUsers\Api\Controllers\UserController;
use App\Domains\Vault\ManageVault\Api\Controllers\VaultController;
use App\Http\Controllers\Api\ContactLabelController;
use App\Http\Controllers\Api\LabelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the bootstrap/app.php file and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    // users
    Route::get('user', [UserController::class, 'user']);
    Route::apiResource('users', UserController::class)->only(['index', 'show']);

    // vaults
    Route::apiResource('vaults', VaultController::class);

    // labels
    // Labels / Tags
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/tags', [LabelController::class, 'index']);
        Route::post('/tags', [LabelController::class, 'store']);
        Route::get('/tags/{id}', [LabelController::class, 'show']);
        Route::put('/tags/{id}', [LabelController::class, 'update']);
        Route::delete('/tags/{id}', [LabelController::class, 'destroy']);

        Route::post('/contacts/{contactId}/tags', [ContactLabelController::class, 'attach']);
        Route::delete('/contacts/{contactId}/tags/{labelId}', [ContactLabelController::class, 'detach']);
        Route::get('/contacts', [ContactLabelController::class, 'index']);
    });
});
