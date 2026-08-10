<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

/*
 * Autenticación (HU-03, HU-05, HU-24, HU-25.2).
 * Las URL conservan las del prototipo para no romper enlaces existentes.
 */

Route::middleware('guest')->group(function () {
    Route::get('registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('registro', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('olvide-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('olvide-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('cambiar-password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('cambiar-password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
