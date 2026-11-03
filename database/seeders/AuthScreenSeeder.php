<?php

namespace Database\Seeders;

use App\Models\AuthScreen;
use Illuminate\Database\Seeder;

/**
 * The copy of the sign-up and sign-in screens (W01–W04), as the frontend shows it
 * (src/pages/auth/EmailAuth.tsx, CheckEmail.tsx, Verify.tsx and AuthLayout.tsx). Words in braces,
 * like {email}, are filled in by the frontend. Safe to run again: rows are matched by key, and
 * their texts are reset to these.
 */
class AuthScreenSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->screens() as $key => $texts) {
            AuthScreen::updateOrCreate(['key' => $key], ['texts' => $texts]);
        }
    }

    /**
     * @return array<string, array{en: array<string, string>, ar: array<string, string>}>
     */
    private function screens(): array
    {
        return [
            'layout' => [
                'en' => [
                    'panel_line' => 'Turn comments into customers.',
                    'home_label' => 'Blexchat home',
                    'copyright' => '© 2026 Blexchat',
                    'privacy' => 'Privacy',
                    'terms' => 'Terms',
                ],
                'ar' => [
                    'panel_line' => 'حوّل التعليقات إلى عملاء.',
                    'home_label' => 'الصفحة الرئيسية لـ Blexchat',
                    'copyright' => '© 2026 Blexchat',
                    'privacy' => 'الخصوصية',
                    'terms' => 'الشروط',
                ],
            ],
            'email_form' => [
                'en' => [
                    'or' => 'or',
                    'email_label' => 'Email',
                    'email_placeholder' => 'name@yourbrand.com',
                    'email_required' => 'Enter your email address to continue.',
                    'email_invalid' => 'Enter a full email address, like name@yourbrand.com.',
                    'notice_google_failed' => 'Google sign-in didn’t finish. Try again, or continue with email.',
                    'notice_signed_out' => 'You were signed out. Sign in again to pick up where you left off.',
                ],
                'ar' => [
                    'or' => 'أو',
                    'email_label' => 'البريد الإلكتروني',
                    'email_placeholder' => 'name@yourbrand.com',
                    'email_required' => 'أدخل بريدك الإلكتروني للمتابعة.',
                    'email_invalid' => 'أدخل بريدًا إلكترونيًا كاملًا، مثل name@yourbrand.com.',
                    'notice_google_failed' => 'لم يكتمل تسجيل الدخول عبر Google. حاول مرة أخرى، أو تابع بالبريد الإلكتروني.',
                    'notice_signed_out' => 'تم تسجيل خروجك. سجّل الدخول مرة أخرى لتكمل من حيث توقفت.',
                ],
            ],
            'signup' => [
                'en' => [
                    'title' => 'Create your free account',
                    'lead' => 'Automate replies on Instagram, WhatsApp, TikTok and Messenger. No credit card.',
                    'google' => 'Sign up with Google',
                    'submit' => 'Continue with email',
                    'aside_text' => 'Have an account?',
                    'aside_link' => 'Sign in',
                    'legal' => 'By continuing you agree to our {terms} and {privacy}.',
                    'legal_terms' => 'Terms',
                    'legal_privacy' => 'Privacy Policy',
                ],
                'ar' => [
                    'title' => 'أنشئ حسابك المجاني',
                    'lead' => 'ردود تلقائية على Instagram وWhatsApp وTikTok وMessenger. بدون بطاقة ائتمان.',
                    'google' => 'أنشئ حسابك عبر Google',
                    'submit' => 'تابع بالبريد الإلكتروني',
                    'aside_text' => 'لديك حساب؟',
                    'aside_link' => 'سجّل الدخول',
                    'legal' => 'بالمتابعة فإنك توافق على {terms} و{privacy}.',
                    'legal_terms' => 'الشروط',
                    'legal_privacy' => 'سياسة الخصوصية',
                ],
            ],
            'signin' => [
                'en' => [
                    'title' => 'Welcome back',
                    'lead' => 'Sign in with Google, or we’ll email you a link. No password to remember.',
                    'google' => 'Sign in with Google',
                    'submit' => 'Email me a sign-in link',
                    'aside_text' => 'New to Blexchat?',
                    'aside_link' => 'Create an account',
                ],
                'ar' => [
                    'title' => 'أهلًا بعودتك',
                    'lead' => 'سجّل الدخول عبر Google، أو نرسل لك رابطًا على بريدك. بدون كلمة مرور تتذكرها.',
                    'google' => 'سجّل الدخول عبر Google',
                    'submit' => 'أرسل لي رابط الدخول',
                    'aside_text' => 'جديد على Blexchat؟',
                    'aside_link' => 'أنشئ حسابًا',
                ],
            ],
            'check_email' => [
                'en' => [
                    'title' => 'Check your email',
                    'lead_signup' => 'We sent a sign-in link to {email}. Open it on this device to finish setting up your account.',
                    'lead_signin' => 'We sent a sign-in link to {email}. Open it on this device to finish signing in.',
                    'open_gmail' => 'Open Gmail',
                    'open_outlook' => 'Open Outlook',
                    'resend_countdown' => 'Didn’t get it? Check your spam folder, or resend in {time}.',
                    'resend_prompt' => 'Didn’t get it?',
                    'resend' => 'Resend the link',
                    'resend_limited' => 'Too many attempts. Try again in a few minutes.',
                    'wrong_address' => 'Wrong address?',
                    'use_another' => 'Use another email',
                ],
                'ar' => [
                    'title' => 'تحقّق من بريدك',
                    'lead_signup' => 'أرسلنا رابط الدخول إلى {email}. افتحه على هذا الجهاز لتكمل إعداد حسابك.',
                    'lead_signin' => 'أرسلنا رابط الدخول إلى {email}. افتحه على هذا الجهاز لتكمل تسجيل الدخول.',
                    'open_gmail' => 'افتح Gmail',
                    'open_outlook' => 'افتح Outlook',
                    'resend_countdown' => 'لم يصلك؟ تحقّق من مجلد الرسائل غير المرغوبة، أو أعد الإرسال بعد {time}.',
                    'resend_prompt' => 'لم يصلك؟',
                    'resend' => 'أعد إرسال الرابط',
                    'resend_limited' => 'محاولات كثيرة. حاول مرة أخرى بعد بضع دقائق.',
                    'wrong_address' => 'عنوان خاطئ؟',
                    'use_another' => 'استخدم بريدًا آخر',
                ],
            ],
            'verify' => [
                'en' => [
                    'title' => 'One more step',
                    'lead' => 'We’re making sure this sign-in is really you. It only takes a moment.',
                    'status_title' => 'Verifying your link…',
                    'status_note' => 'This page continues on its own.',
                    'badge' => 'Secure',
                    'fine' => 'Don’t close this tab. If nothing happens in a minute, open the link again.',
                    'toast_created_title' => 'Account created',
                    'toast_created_text' => 'Welcome to Blexchat.',
                    'toast_welcome_back' => 'Welcome back, {name}',
                ],
                'ar' => [
                    'title' => 'خطوة أخيرة',
                    'lead' => 'نتأكد أن تسجيل الدخول هذا منك فعلًا. لن يستغرق سوى لحظة.',
                    'status_title' => 'جارٍ التحقق من الرابط…',
                    'status_note' => 'ستنتقل هذه الصفحة تلقائيًا.',
                    'badge' => 'آمن',
                    'fine' => 'لا تغلق هذه الصفحة. إن لم يحدث شيء خلال دقيقة، افتح الرابط مرة أخرى.',
                    'toast_created_title' => 'تم إنشاء الحساب',
                    'toast_created_text' => 'أهلًا بك في Blexchat.',
                    'toast_welcome_back' => 'أهلًا بعودتك يا {name}',
                ],
            ],
            'link_expired' => [
                'en' => [
                    'title' => 'This link has expired',
                    'lead' => 'Sign-in links work once, for 15 minutes. We can send a new one to {email}.',
                    'submit' => 'Send a new link',
                    'use_another' => 'Use another email',
                    'toast_title' => 'New link sent',
                    'toast_text' => 'Check {email}.',
                ],
                'ar' => [
                    'title' => 'انتهت صلاحية هذا الرابط',
                    'lead' => 'روابط الدخول تعمل مرة واحدة ولمدة 15 دقيقة. يمكننا إرسال رابط جديد إلى {email}.',
                    'submit' => 'أرسل رابطًا جديدًا',
                    'use_another' => 'استخدم بريدًا آخر',
                    'toast_title' => 'تم إرسال رابط جديد',
                    'toast_text' => 'تحقّق من {email}.',
                ],
            ],
        ];
    }
}
