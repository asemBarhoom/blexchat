<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuthScreenCollection;
use App\Models\AuthScreen;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuthScreens',
    description: 'The visible copy of the sign-up and sign-in screens, one object of texts per screen. Words in braces are placeholders the frontend fills in: {email} the address, {time} the resend countdown (0:59), {name} the owner’s first name, {terms} and {privacy} the legal links (labelled by `legal_terms` and `legal_privacy`).',
    properties: [
        new OA\Property(property: 'layout', description: 'The frame every auth screen shares: brand panel, header and footer', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'panel_line' => 'Turn comments into customers.', 'home_label' => 'Blexchat home', 'copyright' => '© 2026 Blexchat', 'privacy' => 'Privacy', 'terms' => 'Terms',
        ]),
        new OA\Property(property: 'email_form', description: 'The email form and notices shared by W01 and W04', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'or' => 'or', 'email_label' => 'Email', 'email_placeholder' => 'name@yourbrand.com',
            'email_required' => 'Enter your email address to continue.', 'email_invalid' => 'Enter a full email address, like name@yourbrand.com.',
            'notice_google_failed' => 'Google sign-in didn’t finish. Try again, or continue with email.', 'notice_signed_out' => 'You were signed out. Sign in again to pick up where you left off.',
        ]),
        new OA\Property(property: 'signup', description: 'W01 — sign up', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'title' => 'Create your free account', 'lead' => 'Automate replies on Instagram, WhatsApp, TikTok and Messenger. No credit card.',
            'google' => 'Sign up with Google', 'submit' => 'Continue with email', 'aside_text' => 'Have an account?', 'aside_link' => 'Sign in',
            'legal' => 'By continuing you agree to our {terms} and {privacy}.', 'legal_terms' => 'Terms', 'legal_privacy' => 'Privacy Policy',
        ]),
        new OA\Property(property: 'signin', description: 'W04 — sign in', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'title' => 'Welcome back', 'lead' => 'Sign in with Google, or we’ll email you a link. No password to remember.',
            'google' => 'Sign in with Google', 'submit' => 'Email me a sign-in link', 'aside_text' => 'New to Blexchat?', 'aside_link' => 'Create an account',
        ]),
        new OA\Property(property: 'check_email', description: 'W02 — check your email; `lead_signup` or `lead_signin` by mode', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'title' => 'Check your email',
            'lead_signup' => 'We sent a sign-in link to {email}. Open it on this device to finish setting up your account.',
            'lead_signin' => 'We sent a sign-in link to {email}. Open it on this device to finish signing in.',
            'open_gmail' => 'Open Gmail', 'open_outlook' => 'Open Outlook',
            'resend_countdown' => 'Didn’t get it? Check your spam folder, or resend in {time}.', 'resend_prompt' => 'Didn’t get it?', 'resend' => 'Resend the link',
            'resend_limited' => 'Too many attempts. Try again in a few minutes.', 'wrong_address' => 'Wrong address?', 'use_another' => 'Use another email',
        ]),
        new OA\Property(property: 'verify', description: 'W03 — one more step, and the toasts it ends with', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'title' => 'One more step', 'lead' => 'We’re making sure this sign-in is really you. It only takes a moment.',
            'status_title' => 'Verifying your link…', 'status_note' => 'This page continues on its own.', 'badge' => 'Secure',
            'fine' => 'Don’t close this tab. If nothing happens in a minute, open the link again.',
            'toast_created_title' => 'Account created', 'toast_created_text' => 'Welcome to Blexchat.', 'toast_welcome_back' => 'Welcome back, {name}',
        ]),
        new OA\Property(property: 'link_expired', description: 'W03 when the link was used or is past its 15 minutes', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), example: [
            'title' => 'This link has expired', 'lead' => 'Sign-in links work once, for 15 minutes. We can send a new one to {email}.',
            'submit' => 'Send a new link', 'use_another' => 'Use another email', 'toast_title' => 'New link sent', 'toast_text' => 'Check {email}.',
        ]),
    ],
)]
class AuthScreenController extends Controller
{
    #[OA\Get(
        path: '/api/v1/auth/screens',
        summary: 'The texts of the sign-up and sign-in screens (W01–W04)',
        description: <<<'MD'
            Headings, leads, button and field labels, notices and links of the auth screens, as edited in the admin panel. No authentication.

            - Send `?lang=ar` for Arabic or `?lang=en` for English; without it, `Accept-Language` is used, and anything else returns English.
            - A text not yet translated is served in English, and `meta.fallback` is then `true`.
            - Validation and sign-in errors returned by the other auth endpoints keep coming in their own responses.
            MD,
        tags: ['Authentication'],
        parameters: [
            new OA\Parameter(name: 'lang', in: 'query', required: false, description: 'Language of the texts in the response. Takes precedence over Accept-Language. Defaults to en.', schema: new OA\Schema(type: 'string', default: 'en', enum: ['en', 'ar']), example: 'ar'),
            new OA\Parameter(name: 'Accept-Language', in: 'header', required: false, description: 'Used only when `lang` is absent; the first two letters are read (ar-SA → ar).', schema: new OA\Schema(type: 'string'), example: 'ar'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The screens’ texts',
                headers: [new OA\Header(header: 'Content-Language', description: 'Language of the returned texts', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/AuthScreens'),
                    new OA\Property(property: 'meta', type: 'object', properties: [
                        new OA\Property(property: 'locale', type: 'string', enum: ['en', 'ar'], description: 'Language the texts were served in', example: 'ar'),
                        new OA\Property(property: 'fallback', type: 'boolean', description: 'True when at least one text is not translated yet and is served in English'),
                        new OA\Property(property: 'available_locales', type: 'array', description: 'Every value `lang` accepts', items: new OA\Items(type: 'string'), example: ['en', 'ar']),
                    ]),
                ]),
            ),
            new OA\Response(response: 404, description: 'The screens’ texts have not been seeded'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $screens = AuthScreen::query()->orderBy('id')->get();

        abort_if($screens->isEmpty(), 404);

        return (new AuthScreenCollection($screens))
            ->response()
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Language', App::getLocale());
    }
}
