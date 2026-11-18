<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\VerificationCodeController;
use App\Http\Controllers\Auth\PasswordResetCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Mot de passe oublié (notre système par code)
    Route::get('/forgot-password', [PasswordResetCodeController::class, 'request'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetCodeController::class, 'sendCode'])
        ->name('password.email');
    Route::get('/reset-password-code', [PasswordResetCodeController::class, 'showResetForm'])
        ->name('password.reset.form');
    Route::post('/reset-password-code', [PasswordResetCodeController::class, 'reset'])
        ->name('password.update.custom');
});

Route::middleware('auth')->group(function () {
    // Vérification email par code
    Route::get('/verify-email-code', [VerificationCodeController::class, 'show'])
        ->name('verification.notice.custom');
    Route::post('/verify-email-code', [VerificationCodeController::class, 'verify'])
        ->name('verification.verify.custom');
    Route::post('/verify-email-code/resend', [VerificationCodeController::class, 'resend'])
        ->name('verification.resend.custom');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});