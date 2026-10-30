<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Webhooks (no auth — platform calls these) ─────────────────────
Route::prefix('v1/webhooks')->name('webhooks.')->group(function () {
    Route::get('meta', [WebhookController::class, 'verifyMeta'])->name('meta.verify');
    Route::post('meta', [WebhookController::class, 'meta'])->name('meta');
    Route::post('tiktok', [WebhookController::class, 'tiktok'])->name('tiktok');
});

// ── Auth (public) ──────────────────────────────────────────────────
Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

// ── Authenticated ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {

    // Home (single call for dashboard screen)
    Route::get('home', HomeController::class)->name('home');

    // Auth
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::put('auth/locale', [AuthController::class, 'setLocale'])->name('auth.locale');

    // Channels
    Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::post('channels/connect', [ChannelController::class, 'connect'])->name('channels.connect');
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');

    // Automations
    Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}', [AutomationController::class, 'show'])->name('automations.show');
    Route::patch('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
    Route::patch('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');

    // Conversations & Messages
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.send-message');

    // Comments
    Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('posts.comments');
    Route::post('comments/{comment}/reply', [CommentController::class, 'reply'])->name('comments.reply');
    Route::post('comments/{comment}/send-dm', [CommentController::class, 'sendDm'])->name('comments.send-dm');

    // Analytics
    Route::get('analytics/overview', [AnalyticsController::class, 'overview'])->name('analytics.overview');
    Route::get('analytics/automations', [AnalyticsController::class, 'automations'])->name('analytics.automations');
});
