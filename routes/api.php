<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\AuthScreenController;
use App\Http\Controllers\Api\V1\Auth\GoogleController;
use App\Http\Controllers\Api\V1\Auth\LoginLinkController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\AutomationTemplateController;
use App\Http\Controllers\Api\V1\ChannelAccessRequestController;
use App\Http\Controllers\Api\V1\ChannelConnectScreenController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ChannelOptionController;
use App\Http\Controllers\Api\V1\ChannelPostController;
use App\Http\Controllers\Api\V1\ConnectedChannelController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FirstMoveController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InstagramConnectionController;
use App\Http\Controllers\Api\V1\InstagramWebhookController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\SolutionController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SurveyController;
use Illuminate\Support\Facades\Route;

// Marketing site: HomePage
Route::get('v1/home', HomeController::class)->name('api.v1.home');

// Plan ladder: pricing page and the onboarding plan screen (W09)
Route::get('v1/plans', PlanController::class)->name('api.v1.plans');

// Marketing site: the /solutions/{slug} pages
Route::get('v1/solutions', [SolutionController::class, 'index'])->name('api.v1.solutions.index');
Route::get('v1/solutions/{slug}', [SolutionController::class, 'show'])->name('api.v1.solutions.show');

// Checkout modal: the tiers it offers and the trial reminder day
Route::get('v1/checkout', CheckoutController::class)->name('api.v1.checkout');

// Marketing site: PricingPage (copy, plan comparison, FAQ)
Route::get('v1/pricing', PricingController::class)->name('api.v1.pricing');

// Meta calls these; requests are authenticated by their signature, not a user
Route::get('v1/webhooks/instagram', [InstagramWebhookController::class, 'verify'])->name('webhooks.instagram.verify');
Route::post('v1/webhooks/instagram', [InstagramWebhookController::class, 'receive'])->name('webhooks.instagram');

// Sign up / sign in (W01, W04), check email (W02), one more step (W03): passwordless, by emailed link or Google
Route::prefix('v1/auth')->name('auth.')->middleware('throttle:30,1')->group(function () {
    Route::post('email-link', [LoginLinkController::class, 'send'])->name('email-link.send');
    Route::post('email-link/verify', [LoginLinkController::class, 'verify'])->name('email-link.verify');
    Route::get('google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::post('google', [GoogleController::class, 'signIn'])->name('google.sign-in');
    Route::get('google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// The texts of the sign-up and sign-in screens (W01–W04), edited in the admin panel
Route::get('v1/auth/screens', AuthScreenController::class)->name('api.v1.auth.screens');

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function () {
    // App home screen
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // The signed-in account, and sign out
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('auth/me', [AuthController::class, 'update'])->name('auth.me.update');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Onboarding after sign-up: progress, the survey (W08) and the plan (W09); channels connect below
    Route::get('onboarding', OnboardingController::class)->name('onboarding');
    Route::get('onboarding/channels', ChannelOptionController::class)->name('onboarding.channels'); // W05 channel picker
    Route::get('onboarding/survey', [SurveyController::class, 'show'])->name('onboarding.survey');
    Route::post('onboarding/survey', [SurveyController::class, 'store'])->name('onboarding.survey.store');
    Route::post('onboarding/plan', [SubscriptionController::class, 'store'])->name('onboarding.plan');

    // Checkout: "Apply coupon" (checks and prices only; nothing is redeemed yet)
    Route::post('coupons/check', [CouponController::class, 'check'])->middleware('throttle:30,1')->name('coupons.check');
    // Onboarding W10: the "popular first move" cards that open the automation wizard
    Route::get('onboarding/first-moves', FirstMoveController::class)->name('onboarding.first-moves');

    // Connect / disconnect channel screens
    Route::get('channels/instagram/authorize', [InstagramConnectionController::class, 'authorize'])->name('channels.instagram.authorize');
    Route::post('channels/instagram', [InstagramConnectionController::class, 'store'])->name('channels.instagram.store');
    Route::post('channels/connect', [ChannelController::class, 'connect'])->name('channels.connect');

    // Connect a channel (W06): the screen per channel, and Request access for an account another workspace has
    Route::get('onboarding/channels/{platform}/connect', ChannelConnectScreenController::class)->name('onboarding.channels.connect');
    Route::get('channels/access-requests', [ChannelAccessRequestController::class, 'index'])->name('channels.access-requests.index');
    Route::post('channels/access-requests', [ChannelAccessRequestController::class, 'store'])->name('channels.access-requests.store');
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
    // Channel connected (W07)
    Route::get('channels/{channel}', ConnectedChannelController::class)->whereNumber('channel')->name('channels.show');

    // Automations list, detail, wizard, start/stop, move to Trash
    Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}', [AutomationController::class, 'show'])->name('automations.show');
    Route::patch('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
    Route::patch('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');

    // Automation wizard (W11–W15, Review) and its template picker (W19)
    Route::get('automation-templates', AutomationTemplateController::class)->name('automation-templates.index');
    Route::get('channels/{channel}/posts', ChannelPostController::class)->name('channels.posts');
    Route::post('automations/wizard', [AutomationController::class, 'storeFromWizard'])->name('automations.wizard.store');
    Route::put('automations/{automation}/wizard', [AutomationController::class, 'updateFromWizard'])->name('automations.wizard.update');

    // Inbox: conversation list, thread, reply
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('conversations.send-message');
});
