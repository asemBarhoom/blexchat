<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InstagramConnectionController;
use App\Http\Controllers\Api\V1\InstagramWebhookController;
use Illuminate\Support\Facades\Route;

// Marketing site: HomePage
Route::get('v1/home', HomeController::class)->name('api.v1.home');

// Meta calls these; requests are authenticated by their signature, not a user
Route::get('v1/webhooks/instagram', [InstagramWebhookController::class, 'verify'])->name('webhooks.instagram.verify');
Route::post('v1/webhooks/instagram', [InstagramWebhookController::class, 'receive'])->name('webhooks.instagram');

// Sign up / sign in screens
Route::prefix('v1/auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    // App home screen
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Sign out
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Connect / disconnect channel screens
    Route::get('channels/instagram/authorize', [InstagramConnectionController::class, 'authorize'])->name('channels.instagram.authorize');
    Route::post('channels/instagram', [InstagramConnectionController::class, 'store'])->name('channels.instagram.store');
    Route::post('channels/connect', [ChannelController::class, 'connect'])->name('channels.connect');
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');

    // Automations list, detail, wizard, start/stop, move to Trash
    Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}', [AutomationController::class, 'show'])->name('automations.show');
    Route::patch('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
    Route::patch('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');

    // Inbox: conversation list, thread, reply
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.send-message');
});
