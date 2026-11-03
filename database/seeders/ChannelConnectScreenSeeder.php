<?php

namespace Database\Seeders;

use App\Models\ChannelConnectScreen;
use Illuminate\Database\Seeder;

/**
 * The connect-a-channel screen (W06) for each channel, as the frontend shows it
 * (src/pages/auth/connectContent.ts). Admins edit it in the panel. Safe to run again: rows are matched by platform.
 */
class ChannelConnectScreenSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->screens() as $screen) {
            ChannelConnectScreen::updateOrCreate(['platform' => $screen['platform']], $screen);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function screens(): array
    {
        $metaHelp = [
            'en' => ['title' => 'Use Meta Business Suite', 'note' => 'If an agency or a team manages your Page.', 'url' => null],
            'ar' => ['title' => 'استخدم Meta Business Suite', 'note' => 'إن كانت وكالة أو فريق يدير صفحتك.', 'url' => null],
        ];

        return [
            [
                'platform' => 'instagram',
                'method' => 'meta',
                'name' => ['en' => 'Instagram', 'ar' => 'إنستغرام'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'اربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. Your Instagram needs to be a professional account — Business or Creator.',
                    'ar' => 'يرتبط Blexchat عبر Meta. يجب أن يكون حسابك في إنستغرام احترافياً — حساب أعمال أو صانع محتوى.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your Instagram account', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى فيسبوك', 'اختر حسابك في إنستغرام', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Connect with Instagram login', 'note' => 'For accounts without a Facebook Page.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your account doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'اربط بتسجيل الدخول إلى إنستغرام', 'note' => 'للحسابات التي ليس لها صفحة على فيسبوك.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'حين لا يظهر حسابك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'Instagram · Professional account', 'ar' => 'إنستغرام · حساب احترافي'],
                'can' => ['en' => 'answer comments and DMs on your account', 'ar' => 'الرد على التعليقات والرسائل في حسابك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'أعد الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This is a personal account.', 'ar' => 'هذا حساب شخصي.'],
                'ineligible_text' => [
                    'en' => 'Blexchat works with professional accounts. Switching is free and takes a minute in the Instagram app.',
                    'ar' => 'يعمل Blexchat مع الحسابات الاحترافية. التحويل مجاني ويستغرق دقيقة في تطبيق إنستغرام.',
                ],
                'ineligible_kind' => ['en' => 'Instagram · Personal account', 'ar' => 'إنستغرام · حساب شخصي'],
                'ineligible_how' => ['en' => 'How to switch to a professional account', 'ar' => 'كيف تحوّل إلى حساب احترافي'],
            ],
            [
                'platform' => 'whatsapp',
                'method' => 'meta',
                'name' => ['en' => 'WhatsApp', 'ar' => 'واتساب'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'اربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. You need a WhatsApp Business account and a number that isn’t in use in the WhatsApp app.',
                    'ar' => 'يرتبط Blexchat عبر Meta. تحتاج إلى حساب واتساب للأعمال ورقم غير مستخدم في تطبيق واتساب.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your WhatsApp Business account and number', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى فيسبوك', 'اختر حساب واتساب للأعمال والرقم', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Move a number from the WhatsApp Business app', 'note' => 'Keep the number your customers already know.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your number doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'انقل رقماً من تطبيق واتساب للأعمال', 'note' => 'احتفظ بالرقم الذي يعرفه عملاؤك.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'حين لا يظهر رقمك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'WhatsApp Business', 'ar' => 'واتساب للأعمال'],
                'can' => ['en' => 'answer chats on your number', 'ar' => 'الرد على المحادثات على رقمك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'أعد الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This number isn’t on WhatsApp Business.', 'ar' => 'هذا الرقم ليس على واتساب للأعمال.'],
                'ineligible_text' => [
                    'en' => 'Blexchat needs a WhatsApp Business account, with a number that isn’t in use in the WhatsApp app.',
                    'ar' => 'يحتاج Blexchat إلى حساب واتساب للأعمال، برقم غير مستخدم في تطبيق واتساب.',
                ],
                'ineligible_kind' => ['en' => 'WhatsApp · Personal number', 'ar' => 'واتساب · رقم شخصي'],
                'ineligible_how' => ['en' => 'How to set up WhatsApp Business', 'ar' => 'كيف تُعدّ واتساب للأعمال'],
            ],
            [
                'platform' => 'tiktok',
                'method' => 'tiktok',
                'name' => ['en' => 'TikTok', 'ar' => 'تيك توك'],
                'cta' => ['en' => 'Continue with TikTok', 'ar' => 'تابع عبر تيك توك'],
                'lead' => [
                    'en' => 'Blexchat connects through TikTok. Your TikTok needs to be a Business account.',
                    'ar' => 'يرتبط Blexchat عبر تيك توك. يجب أن يكون حسابك في تيك توك حساب أعمال.',
                ],
                'steps' => [
                    'en' => ['Log in to TikTok', 'Choose your Business account', 'Review the permissions and authorize'],
                    'ar' => ['سجّل الدخول إلى تيك توك', 'اختر حساب الأعمال', 'راجع الأذونات ووافق'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Switch to a Business account', 'note' => 'Personal accounts can’t connect to apps.', 'url' => null],
                        ['title' => 'Connect through TikTok Business Center', 'note' => 'If an agency or a team manages your account.', 'url' => null],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your account doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'حوّل إلى حساب أعمال', 'note' => 'لا يمكن ربط الحسابات الشخصية بالتطبيقات.', 'url' => null],
                        ['title' => 'اربط عبر TikTok Business Center', 'note' => 'إن كانت وكالة أو فريق يدير حسابك.', 'url' => null],
                        ['title' => 'دليل حل المشكلات', 'note' => 'حين لا يظهر حسابك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'TikTok · Business account', 'ar' => 'تيك توك · حساب أعمال'],
                'can' => ['en' => 'answer comments and DMs on your videos', 'ar' => 'الرد على التعليقات والرسائل في فيديوهاتك'],
                'reconnect_cta' => ['en' => 'Reconnect with TikTok', 'ar' => 'أعد الربط عبر تيك توك'],
                'waiting_in' => ['en' => 'the TikTok window', 'ar' => 'نافذة تيك توك'],
                'ineligible_title' => ['en' => 'This is a personal TikTok account.', 'ar' => 'هذا حساب شخصي في تيك توك.'],
                'ineligible_text' => [
                    'en' => 'Blexchat works with TikTok Business accounts. You can switch in your TikTok settings.',
                    'ar' => 'يعمل Blexchat مع حسابات الأعمال في تيك توك. يمكنك التحويل من إعدادات تيك توك.',
                ],
                'ineligible_kind' => ['en' => 'TikTok · Personal account', 'ar' => 'تيك توك · حساب شخصي'],
                'ineligible_how' => ['en' => 'How to switch to a Business account', 'ar' => 'كيف تحوّل إلى حساب أعمال'],
            ],
            [
                'platform' => 'messenger',
                'method' => 'meta',
                'name' => ['en' => 'Messenger', 'ar' => 'ماسنجر'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'اربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. You need a Facebook Page that you manage.',
                    'ar' => 'يرتبط Blexchat عبر Meta. تحتاج إلى صفحة على فيسبوك تديرها.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your Facebook Page', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى فيسبوك', 'اختر صفحتك على فيسبوك', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Create a Facebook Page', 'note' => 'If your shop doesn’t have one yet.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your Page doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'أنشئ صفحة على فيسبوك', 'note' => 'إن لم يكن لمتجرك صفحة بعد.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'حين لا تظهر صفحتك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'Facebook Page', 'ar' => 'صفحة فيسبوك'],
                'can' => ['en' => 'answer messages to your Page', 'ar' => 'الرد على رسائل صفحتك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'أعد الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This Facebook account doesn’t manage a Page.', 'ar' => 'حساب فيسبوك هذا لا يدير أي صفحة.'],
                'ineligible_text' => [
                    'en' => 'Blexchat answers messages sent to a Facebook Page. Create one, or ask to be made an admin of yours.',
                    'ar' => 'يرد Blexchat على الرسائل المرسلة إلى صفحة فيسبوك. أنشئ صفحة، أو اطلب أن تُضاف مشرفاً على صفحتك.',
                ],
                'ineligible_kind' => ['en' => 'Facebook profile · No Page', 'ar' => 'ملف فيسبوك · بلا صفحة'],
                'ineligible_how' => ['en' => 'How to create a Facebook Page', 'ar' => 'كيف تنشئ صفحة على فيسبوك'],
            ],
        ];
    }
}
