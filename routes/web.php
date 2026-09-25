<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\LetterNumberController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [ArchiveController::class, 'index'])->name('dashboard');
    Route::post('/arsip/upload', [ArchiveController::class, 'upload'])->name('archive.upload');
    Route::get('/arsip/{fileId}/buka', [ArchiveController::class, 'open'])->name('archive.open');
    Route::get('/arsip/{fileId}/preview-content', [ArchiveController::class, 'previewContent'])->name('archive.preview-content');
    Route::get('/arsip/{fileId}/download', [ArchiveController::class, 'download'])->name('archive.download');
    Route::delete('/arsip/{fileId}', [ArchiveController::class, 'destroy'])->name('archive.destroy');
    Route::post('/penomoran', [LetterNumberController::class, 'store'])->name('letter-number.store');
    Route::patch('/penomoran/{letterNumber}/lampiran', [LetterNumberController::class, 'updateAttachment'])->name('letter-number.attachment.update');
    Route::get('/penomoran/{letterNumber}/lampiran', [LetterNumberController::class, 'attachment'])->name('letter-number.attachment');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/google/connect', [GoogleDriveController::class, 'connect'])->name('google.connect');
        Route::get('/google/callback', [GoogleDriveController::class, 'callback'])->name('google.callback');
        Route::post('/google/disconnect', [GoogleDriveController::class, 'disconnect'])->name('google.disconnect');
    });
});
