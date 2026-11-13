<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Http\Controllers\AccountController;
use Gmarineau\AdminPhp\Http\Controllers\ActivityController;
use Gmarineau\AdminPhp\Http\Controllers\AssetController;
use Gmarineau\AdminPhp\Http\Controllers\ItemOrderController;
use Gmarineau\AdminPhp\Http\Controllers\ItemPublicationController;
use Gmarineau\AdminPhp\Http\Controllers\ItemRevisionController;
use Gmarineau\AdminPhp\Http\Controllers\ItemTrashController;
use Gmarineau\AdminPhp\Http\Controllers\ModuleController;
use Gmarineau\AdminPhp\Http\Controllers\ModuleItemController;
use Gmarineau\AdminPhp\Http\Controllers\PasswordController;
use Gmarineau\AdminPhp\Http\Controllers\ReferenceController;
use Gmarineau\AdminPhp\Http\Controllers\SessionController;
use Gmarineau\AdminPhp\Http\Controllers\SettingsController;
use Gmarineau\AdminPhp\Http\Controllers\SpaController;
use Gmarineau\AdminPhp\Http\Controllers\UserController;
use Gmarineau\AdminPhp\Http\Middleware\Authorize;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::post('session', [SessionController::class, 'store'])->name('session.store');
    Route::post('password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:5,1')->name('password.forgot');
    Route::post('password/reset', [PasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.reset');

    Route::middleware([Authenticate::using(app(AdminPhp::class)->guardName()), Authorize::class])->group(function () {
        Route::get('session', [SessionController::class, 'show'])->name('session.show');
        Route::delete('session', [SessionController::class, 'destroy'])->name('session.destroy');

        Route::put('account', [AccountController::class, 'update'])->name('account.update');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password');

        Route::get('activity', ActivityController::class)->name('activity');

        Route::middleware('can:'.AdminPhp::MANAGE_SETTINGS)->group(function () {
            Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('can:'.AdminPhp::MANAGE_USERS)->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{id}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation');
            Route::post('users/{id}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{id}/activate', [UserController::class, 'activate'])->name('users.activate');
        });

        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{id}', [AssetController::class, 'show'])->name('assets.show');

        Route::get('references', [ReferenceController::class, 'index'])->name('references.index');

        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');

        Route::get('modules/{module}/items', [ModuleItemController::class, 'index'])->name('items.index');
        Route::post('modules/{module}/items', [ModuleItemController::class, 'store'])->name('items.store');
        Route::put('modules/{module}/items/order', [ItemOrderController::class, 'update'])->name('items.order');
        Route::post('modules/{module}/items/bulk', [ItemTrashController::class, 'bulk'])->name('items.bulk');
        Route::get('modules/{module}/items/{id}', [ModuleItemController::class, 'show'])->name('items.show');
        Route::put('modules/{module}/items/{id}', [ModuleItemController::class, 'update'])->name('items.update');
        Route::delete('modules/{module}/items/{id}', [ItemTrashController::class, 'destroy'])->name('items.destroy');
        Route::post('modules/{module}/items/{id}/duplicate', [ItemTrashController::class, 'duplicate'])->name('items.duplicate');

        Route::post('modules/{module}/items/{id}/publish', [ItemPublicationController::class, 'publish'])->name('items.publish');
        Route::post('modules/{module}/items/{id}/unpublish', [ItemPublicationController::class, 'unpublish'])->name('items.unpublish');
        Route::delete('modules/{module}/items/{id}/draft', [ItemPublicationController::class, 'discardDraft'])->name('items.draft.destroy');

        Route::get('modules/{module}/items/{id}/revisions', [ItemRevisionController::class, 'index'])->name('items.revisions.index');
        Route::post('modules/{module}/items/{id}/revisions/{revision}/restore', [ItemRevisionController::class, 'restore'])->name('items.revisions.restore');
    });
});

Route::get('{view?}', SpaController::class)->where('view', '^(?!api(/|$)).*')->name('spa');
