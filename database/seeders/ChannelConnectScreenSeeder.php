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
            'ar' => ['title' => 'استخدم Meta Business Suite', 'note' => 'إذا كانت وكالة أو فريق يدير صفحتك.', 'url' => null],
        ];

        return [
            [
                'platform' => 'instagram',
                'method' => 'meta',
                'name' => ['en' => 'Instagram', 'ar' => 'Instagram'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'الربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. Your Instagram needs to be a professional account — Business or Creator.',
                    'ar' => 'يرتبط Blexchat عبر Meta. يجب أن يكون حسابك على Instagram حسابًا احترافيًا — تجاريًا أو لصانع محتوى.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your Instagram account', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى Facebook', 'اختر حسابك على Instagram', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Connect with Instagram login', 'note' => 'For accounts without a Facebook Page.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your account doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'الربط بتسجيل الدخول إلى Instagram', 'note' => 'للحسابات التي ليس لها صفحة Facebook.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'إذا لم يظهر حسابك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'Instagram · Professional account', 'ar' => 'Instagram · حساب احترافي'],
                'can' => ['en' => 'answer comments and DMs on your account', 'ar' => 'الرد على التعليقات والرسائل الخاصة في حسابك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'إعادة الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This is a personal account.', 'ar' => 'هذا حساب شخصي.'],
                'ineligible_text' => [
                    'en' => 'Blexchat works with professional accounts. Switching is free and takes a minute in the Instagram app.',
                    'ar' => 'يعمل Blexchat مع الحسابات الاحترافية. التحويل مجاني ويستغرق دقيقة من تطبيق Instagram.',
                ],
                'ineligible_kind' => ['en' => 'Instagram · Personal account', 'ar' => 'Instagram · حساب شخصي'],
                'ineligible_how' => ['en' => 'How to switch to a professional account', 'ar' => 'طريقة التحويل إلى حساب احترافي'],
            ],
            [
                'platform' => 'whatsapp',
                'method' => 'meta',
                'name' => ['en' => 'WhatsApp', 'ar' => 'WhatsApp'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'الربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. You need a WhatsApp Business account and a number that isn’t in use in the WhatsApp app.',
                    'ar' => 'يرتبط Blexchat عبر Meta. تحتاج إلى حساب WhatsApp Business ورقم غير مستخدم في تطبيق WhatsApp.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your WhatsApp Business account and number', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى Facebook', 'اختر حساب WhatsApp Business والرقم', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Move a number from the WhatsApp Business app', 'note' => 'Keep the number your customers already know.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your number doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'انقل رقمًا من تطبيق WhatsApp Business', 'note' => 'احتفظ بالرقم الذي يعرفه عملاؤك.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'إذا لم يظهر رقمك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'WhatsApp Business', 'ar' => 'WhatsApp Business'],
                'can' => ['en' => 'answer chats on your number', 'ar' => 'الرد على المحادثات على رقمك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'إعادة الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This number isn’t on WhatsApp Business.', 'ar' => 'هذا الرقم غير مسجّل في WhatsApp Business.'],
                'ineligible_text' => [
                    'en' => 'Blexchat needs a WhatsApp Business account, with a number that isn’t in use in the WhatsApp app.',
                    'ar' => 'يحتاج Blexchat إلى حساب WhatsApp Business برقم غير مستخدم في تطبيق WhatsApp.',
                ],
                'ineligible_kind' => ['en' => 'WhatsApp · Personal number', 'ar' => 'WhatsApp · رقم شخصي'],
                'ineligible_how' => ['en' => 'How to set up WhatsApp Business', 'ar' => 'طريقة إعداد WhatsApp Business'],
            ],
            [
                'platform' => 'tiktok',
                'method' => 'tiktok',
                'name' => ['en' => 'TikTok', 'ar' => 'TikTok'],
                'cta' => ['en' => 'Continue with TikTok', 'ar' => 'المتابعة عبر TikTok'],
                'lead' => [
                    'en' => 'Blexchat connects through TikTok. Your TikTok needs to be a Business account.',
                    'ar' => 'يرتبط Blexchat عبر TikTok. يجب أن يكون حسابك على TikTok حسابًا تجاريًا.',
                ],
                'steps' => [
                    'en' => ['Log in to TikTok', 'Choose your Business account', 'Review the permissions and authorize'],
                    'ar' => ['سجّل الدخول إلى TikTok', 'اختر حسابك التجاري', 'راجع الأذونات ووافق عليها'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Switch to a Business account', 'note' => 'Personal accounts can’t connect to apps.', 'url' => null],
                        ['title' => 'Connect through TikTok Business Center', 'note' => 'If an agency or a team manages your account.', 'url' => null],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your account doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'التحويل إلى حساب تجاري', 'note' => 'لا يمكن ربط الحسابات الشخصية بالتطبيقات.', 'url' => null],
                        ['title' => 'الربط عبر TikTok Business Center', 'note' => 'إذا كانت وكالة أو فريق يدير حسابك.', 'url' => null],
                        ['title' => 'دليل حل المشكلات', 'note' => 'إذا لم يظهر حسابك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'TikTok · Business account', 'ar' => 'TikTok · حساب تجاري'],
                'can' => ['en' => 'answer comments and DMs on your videos', 'ar' => 'الرد على التعليقات والرسائل الخاصة على فيديوهاتك'],
                'reconnect_cta' => ['en' => 'Reconnect with TikTok', 'ar' => 'إعادة الربط عبر TikTok'],
                'waiting_in' => ['en' => 'the TikTok window', 'ar' => 'نافذة TikTok'],
                'ineligible_title' => ['en' => 'This is a personal TikTok account.', 'ar' => 'هذا حساب TikTok شخصي.'],
                'ineligible_text' => [
                    'en' => 'Blexchat works with TikTok Business accounts. You can switch in your TikTok settings.',
                    'ar' => 'يعمل Blexchat مع حسابات TikTok التجارية. يمكنك التحويل من إعدادات TikTok.',
                ],
                'ineligible_kind' => ['en' => 'TikTok · Personal account', 'ar' => 'TikTok · حساب شخصي'],
                'ineligible_how' => ['en' => 'How to switch to a Business account', 'ar' => 'طريقة التحويل إلى حساب تجاري'],
            ],
            [
                'platform' => 'messenger',
                'method' => 'meta',
                'name' => ['en' => 'Messenger', 'ar' => 'Messenger'],
                'cta' => ['en' => 'Connect via Meta', 'ar' => 'الربط عبر Meta'],
                'lead' => [
                    'en' => 'Blexchat connects through Meta. You need a Facebook Page that you manage.',
                    'ar' => 'يرتبط Blexchat عبر Meta. تحتاج إلى صفحة Facebook تديرها.',
                ],
                'steps' => [
                    'en' => ['Log in to Facebook', 'Choose your Facebook Page', 'Review the permissions and save'],
                    'ar' => ['سجّل الدخول إلى Facebook', 'اختر صفحتك على Facebook', 'راجع الأذونات واحفظ'],
                ],
                'options' => [
                    'en' => [
                        ['title' => 'Create a Facebook Page', 'note' => 'If your shop doesn’t have one yet.', 'url' => null],
                        $metaHelp['en'],
                        ['title' => 'Troubleshooting guide', 'note' => 'When your Page doesn’t show up in the list.', 'url' => null],
                    ],
                    'ar' => [
                        ['title' => 'أنشئ صفحة Facebook', 'note' => 'إذا لم يكن لمتجرك صفحة بعد.', 'url' => null],
                        $metaHelp['ar'],
                        ['title' => 'دليل حل المشكلات', 'note' => 'إذا لم تظهر صفحتك في القائمة.', 'url' => null],
                    ],
                ],
                'account_kind' => ['en' => 'Facebook Page', 'ar' => 'صفحة Facebook'],
                'can' => ['en' => 'answer messages to your Page', 'ar' => 'الرد على الرسائل المرسلة إلى صفحتك'],
                'reconnect_cta' => ['en' => 'Reconnect via Meta', 'ar' => 'إعادة الربط عبر Meta'],
                'waiting_in' => ['en' => 'the Meta window', 'ar' => 'نافذة Meta'],
                'ineligible_title' => ['en' => 'This Facebook account doesn’t manage a Page.', 'ar' => 'حساب Facebook هذا لا يدير أي صفحة.'],
                'ineligible_text' => [
                    'en' => 'Blexchat answers messages sent to a Facebook Page. Create one, or ask to be made an admin of yours.',
                    'ar' => 'يرد Blexchat على الرسائل المرسلة إلى صفحة Facebook. أنشئ صفحة، أو اطلب أن تُضاف مشرفًا على صفحتك.',
                ],
                'ineligible_kind' => ['en' => 'Facebook profile · No Page', 'ar' => 'ملف Facebook شخصي · بدون صفحة'],
                'ineligible_how' => ['en' => 'How to create a Facebook Page', 'ar' => 'طريقة إنشاء صفحة Facebook'],
            ],
        ];
    }
}
