<?php

use Gmarineau\AdminPhp\Http\Controllers\ItemPublicationController;
use Gmarineau\AdminPhp\Http\Controllers\ItemRevisionController;
use Gmarineau\AdminPhp\Http\Controllers\ModuleController;
use Gmarineau\AdminPhp\Http\Controllers\ModuleItemController;
use Gmarineau\AdminPhp\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function () {
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');

    Route::get('modules/{module}/items', [ModuleItemController::class, 'index'])->name('items.index');
    Route::post('modules/{module}/items', [ModuleItemController::class, 'store'])->name('items.store');
    Route::get('modules/{module}/items/{id}', [ModuleItemController::class, 'show'])->name('items.show');
    Route::put('modules/{module}/items/{id}', [ModuleItemController::class, 'update'])->name('items.update');

    Route::post('modules/{module}/items/{id}/publish', [ItemPublicationController::class, 'publish'])->name('items.publish');
    Route::post('modules/{module}/items/{id}/unpublish', [ItemPublicationController::class, 'unpublish'])->name('items.unpublish');
    Route::delete('modules/{module}/items/{id}/draft', [ItemPublicationController::class, 'discardDraft'])->name('items.draft.destroy');

    Route::get('modules/{module}/items/{id}/revisions', [ItemRevisionController::class, 'index'])->name('items.revisions.index');
    Route::post('modules/{module}/items/{id}/revisions/{revision}/restore', [ItemRevisionController::class, 'restore'])->name('items.revisions.restore');
});

Route::get('{view?}', SpaController::class)->where('view', '^(?!api(/|$)).*')->name('spa');
