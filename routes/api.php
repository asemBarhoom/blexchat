<?php

use App\Http\Controllers\Api\V1\AiSettingsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\AuthScreenController;
use App\Http\Controllers\Api\V1\Auth\GoogleController;
use App\Http\Controllers\Api\V1\Auth\LoginLinkController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\AutomationFolderController;
use App\Http\Controllers\Api\V1\AutomationTemplateController;
use App\Http\Controllers\Api\V1\ChannelAccessRequestController;
use App\Http\Controllers\Api\V1\ChannelConnectScreenController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ChannelHealthController;
use App\Http\Controllers\Api\V1\ChannelOAuthController;
use App\Http\Controllers\Api\V1\ChannelOptionController;
use App\Http\Controllers\Api\V1\ChannelPostController;
use App\Http\Controllers\Api\V1\ChannelTestEventController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CheckoutSessionController;
use App\Http\Controllers\Api\V1\ConnectedChannelController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FirstMoveController;
use App\Http\Controllers\Api\V1\HelpController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InboxSettingsController;
use App\Http\Controllers\Api\V1\InstagramConnectionController;
use App\Http\Controllers\Api\V1\InstagramWebhookController;
use App\Http\Controllers\Api\V1\LegalPageController;
use App\Http\Controllers\Api\V1\MessageDeliveryAttemptController;
use App\Http\Controllers\Api\V1\MetaChannelWebhookController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\SolutionController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Middleware\EnsureAccountOwner;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\LegalPage;
use Illuminate\Support\Facades\Route;

// Marketing site: HomePage
Route::get('v1/home', HomeController::class)->name('api.v1.home');

// Plan ladder: pricing page and the onboarding plan screen (W09)
Route::get('v1/plans', PlanController::class)->name('api.v1.plans');

// Marketing site: the /solutions/{slug} pages
Route::get('v1/solutions', [SolutionController::class, 'index'])->name('api.v1.solutions.index');
Route::get('v1/solutions/{slug}', [SolutionController::class, 'show'])->name('api.v1.solutions.show');

// Marketing site: the Privacy Policy and the Terms of Service
Route::get('v1/legal/{slug}', LegalPageController::class)->whereIn('slug', LegalPage::SLUGS)->name('api.v1.legal.show');

// Checkout modal: the tiers it offers and the trial reminder day
Route::get('v1/checkout', CheckoutController::class)->name('api.v1.checkout');

// Marketing site: PricingPage (copy, plan comparison, FAQ)
Route::get('v1/pricing', PricingController::class)->name('api.v1.pricing');
Route::get('v1/help', HelpController::class)->name('api.v1.help');
Route::get('v1/health', SystemHealthController::class)->name('api.v1.health');

// Meta calls these; requests are authenticated by their signature, not a user
Route::get('v1/webhooks/instagram', [InstagramWebhookController::class, 'verify'])->name('webhooks.instagram.verify');
Route::post('v1/webhooks/instagram', [InstagramWebhookController::class, 'receive'])->name('webhooks.instagram');
// Stripe calls this about card payments; authenticated by the Stripe-Signature header
// Meta calls these for WhatsApp and Messenger; authenticated by the X-Hub-Signature-256 header
Route::get('v1/webhooks/{platform}', [MetaChannelWebhookController::class, 'verify'])->whereIn('platform', ['whatsapp', 'messenger'])->name('webhooks.meta.verify');
Route::post('v1/webhooks/{platform}', [MetaChannelWebhookController::class, 'receive'])->whereIn('platform', ['whatsapp', 'messenger'])->name('webhooks.meta');
Route::post('v1/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');

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

Route::middleware(['auth:sanctum', ResolveWorkspace::class, EnsureAccountOwner::class])->prefix('v1')->name('api.v1.')->group(function () {
    // App home screen, and its "last 7 days" cards (S7.2)
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('stats/overview', [StatsController::class, 'overview'])->name('stats.overview');

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

    // The plan after onboarding: read it, switch plan or billing period, cancel back to Free
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('subscription', [SubscriptionController::class, 'update'])->name('subscription.update');
    Route::delete('subscription', [SubscriptionController::class, 'destroy'])->name('subscription.destroy');

    // Checkout: "Apply coupon" (checks and prices only; redeemed once Stripe takes the payment)
    Route::post('coupons/check', [CouponController::class, 'check'])->middleware('throttle:30,1')->name('coupons.check');
    // Checkout: "Continue To Payment" opens the Stripe payment page; its success page checks the result
    Route::post('checkout/session', [CheckoutSessionController::class, 'store'])->middleware('throttle:10,1')->name('checkout.session.store');
    Route::get('checkout/session/{id}', [CheckoutSessionController::class, 'show'])->name('checkout.session.show');
    // Onboarding W10: the "popular first move" cards that open the automation wizard
    Route::get('onboarding/first-moves', FirstMoveController::class)->name('onboarding.first-moves');

    // The connected channels (sidebar and channels page), and connect / disconnect channel screens
    Route::get('channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::get('channels/instagram/authorize', [InstagramConnectionController::class, 'authorize'])->name('channels.instagram.authorize');
    Route::post('channels/instagram', [InstagramConnectionController::class, 'store'])->name('channels.instagram.store');
    Route::post('channels/connect', [ChannelController::class, 'connect'])->name('channels.connect');
    Route::get('channels/oauth/{platform}/start', [ChannelOAuthController::class, 'start'])->name('channels.oauth.start');
    Route::get('channels/oauth/status', [ChannelOAuthController::class, 'status'])->name('channels.oauth.status');
    Route::get('channels/oauth/{platform}/callback', [ChannelOAuthController::class, 'callback'])->withoutMiddleware('auth:sanctum')->name('channels.oauth.callback');
    Route::get('channels/oauth/messenger/pages', [ChannelOAuthController::class, 'pages'])->name('channels.oauth.messenger.pages');
    Route::post('channels/oauth/messenger/pages', [ChannelOAuthController::class, 'selectPage'])->name('channels.oauth.messenger.select-page');

    // Connect a channel (W06): the screen per channel, and Request access for an account another workspace has
    Route::get('onboarding/channels/{platform}/connect', ChannelConnectScreenController::class)->name('onboarding.channels.connect');
    Route::get('channels/access-requests', [ChannelAccessRequestController::class, 'index'])->name('channels.access-requests.index');
    Route::post('channels/access-requests', [ChannelAccessRequestController::class, 'store'])->name('channels.access-requests.store');
    Route::delete('channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
    // Channel connected (W07)
    Route::get('channels/{channel}', ConnectedChannelController::class)->whereNumber('channel')->name('channels.show');
    Route::get('channels/{channel}/health', ChannelHealthController::class)->whereNumber('channel')->name('channels.health');
    // INSTAGRAM_DRIVER=fake only: a simulated comment or DM, handled like Instagram's webhook
    Route::post('channels/{channel}/test-events', ChannelTestEventController::class)->whereNumber('channel')->middleware('throttle:30,1')->name('channels.test-events');

    // Automations list, detail, wizard, start/stop, move to Trash and restore (S5.3)
    Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::post('automations/{automation}/restore', [AutomationController::class, 'restore'])->withTrashed()->name('automations.restore');
    Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
    Route::get('automations/{automation}', [AutomationController::class, 'show'])->name('automations.show');
    Route::get('automations/{automation}/stats', [StatsController::class, 'automation'])->name('automations.stats'); // W17's figures
    Route::patch('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
    Route::patch('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    Route::patch('automations/{automation}/ai', [AutomationController::class, 'ai'])->name('automations.ai'); // Blexchat AI, on plans that include it

    // Workspaces (BE-11): the businesses an owner runs; X-Workspace picks one per request
    Route::apiResource('workspaces', WorkspaceController::class)->except('show');

    // Automation folders: the list's Create folder, rename and delete; automations move with PATCH automations/{id}
    Route::apiResource('automation-folders', AutomationFolderController::class)->except('show')->parameters(['automation-folders' => 'folder']);

    // Automation wizard (W11–W15, Review) and its template picker (W19)
    Route::get('automation-templates', AutomationTemplateController::class)->name('automation-templates.index');
    Route::get('channels/{channel}/posts', ChannelPostController::class)->name('channels.posts');
    Route::post('automations/wizard', [AutomationController::class, 'storeFromWizard'])->name('automations.wizard.store');
    Route::put('automations/{automation}/wizard', [AutomationController::class, 'updateFromWizard'])->name('automations.wizard.update');

    // Contacts list (W23), contact detail (W22), and the sidebar's contacts meter
    Route::get('contacts/usage', [ContactController::class, 'usage'])->name('contacts.usage');
    Route::post('contacts/import', [ContactController::class, 'import'])->middleware('throttle:10,1')->name('contacts.import');
    Route::apiResource('contacts', ContactController::class)->whereNumber('contact');

    // Inbox: conversation list, thread, reply
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::patch('conversations/{conversation}', [ConversationController::class, 'update'])->name('conversations.update'); // Mark done, Reopen, read
    Route::get('ai/settings', [AiSettingsController::class, 'show'])->name('ai.settings.show');
    Route::patch('ai/settings', [AiSettingsController::class, 'update'])->name('ai.settings.update');
    Route::get('inbox/settings', [InboxSettingsController::class, 'show'])->name('inbox.settings.show');
    Route::patch('inbox/settings', [InboxSettingsController::class, 'update'])->name('inbox.settings.update');
    // Settings · Team: invite teammates to the inbox (seats per plan), remove them; a teammate accepts or leaves
    Route::get('team', [TeamController::class, 'show'])->name('team.show');
    Route::post('team/invitations', [TeamController::class, 'invite'])->middleware('throttle:20,1')->name('team.invitations.store');
    Route::delete('team/invitations/{invitation}', [TeamController::class, 'withdraw'])->name('team.invitations.destroy');
    Route::post('team/invitations/accept', [TeamController::class, 'accept'])->middleware('throttle:10,1')->name('team.invitations.accept');
    Route::delete('team/members/{member}', [TeamController::class, 'remove'])->name('team.members.destroy');
    Route::post('team/leave', [TeamController::class, 'leave'])->name('team.leave');

    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'messages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])->middleware('throttle:60,1')->name('conversations.send-message');
    Route::post('message-delivery-attempts/{attempt}/retry', [MessageDeliveryAttemptController::class, 'retry'])->middleware('throttle:10,1')->name('message-delivery-attempts.retry');
});
