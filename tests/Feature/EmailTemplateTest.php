<?php

use App\Filament\Resources\EmailTemplateResource;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Mail\LoginLinkMail;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Sends a sign-in link in the language and returns the email that went out.
 */
function sentLoginLinkMail(string $mode, string $locale = 'en'): LoginLinkMail
{
    Mail::fake();
    test()->postJson("/api/v1/auth/email-link?lang={$locale}", ['email' => 'sara@nourboutique.com', 'mode' => $mode])->assertAccepted();
    test()->travel(1)->minutes();

    return Mail::sent(LoginLinkMail::class)->first();
}

describe('the sign-in link email', function () {
    it('uses the admin’s texts in English and Arabic, with the placeholders filled in', function () {
        EmailTemplate::factory()->create([
            'key' => 'login_link.signin',
            'subject' => ['en' => 'Your Blexchat key', 'ar' => 'مفتاح بلكس تشات'],
            'line' => ['en' => 'One tap and you are in.', 'ar' => 'ضغطة واحدة وتدخل.'],
            'button' => ['en' => 'Open Blexchat', 'ar' => 'افتح بلكس تشات'],
            'expiry' => ['en' => 'Hurry: :minutes minutes left.', 'ar' => 'أسرع: بقي :minutes دقيقة.'],
            'ignore' => ['en' => 'Not you? Ignore it.', 'ar' => 'لست أنت؟ تجاهلها.'],
        ]);

        $mail = sentLoginLinkMail('signin');
        $mail->assertSeeInHtml('One tap and you are in.')->assertSeeInHtml('Open Blexchat')
            ->assertSeeInHtml('Hurry: 15 minutes left.')->assertSeeInHtml('Not you? Ignore it.')
            ->assertDontSeeInHtml(':minutes');
        expect($mail->envelope()->subject)->toBe('Your Blexchat key');

        $mail = sentLoginLinkMail('signin', 'ar');
        $mail->assertSeeInHtml('ضغطة واحدة وتدخل.')->assertSeeInHtml('افتح بلكس تشات')->assertSeeInHtml('أسرع: بقي 15 دقيقة.');
        expect($mail->envelope()->subject)->toBe('مفتاح بلكس تشات');
    });

    it('falls back to the lang/ text for an empty language or text, and without a row', function () {
        EmailTemplate::factory()->create([
            'key' => 'login_link.signup',
            'subject' => ['en' => 'Welcome aboard'],
            'line' => ['en' => '', 'ar' => 'نص المدير.'],
            'button' => [],
        ]);

        $mail = sentLoginLinkMail('signup', 'ar');
        $mail->assertSeeInHtml('نص المدير.')->assertSeeInHtml('تأكيد بريدي')->assertSeeInHtml('يعمل هذا الرابط لمدة 15 دقيقة.');
        expect($mail->envelope()->subject)->toBe('أكّد بريدك الإلكتروني');

        $mail = sentLoginLinkMail('signup');
        $mail->assertSeeInHtml('Tap the button to confirm your email')->assertSeeInHtml('Confirm my email');
        expect($mail->envelope()->subject)->toBe('Welcome aboard');

        sentLoginLinkMail('signin')->assertSeeInHtml('Sign in to Blexchat')->assertSeeInHtml('This link works for 15 minutes.');
    });
});

describe('EmailTemplateSeeder', function () {
    it('adds the lang/ texts in both languages and keeps admin edits when run again', function () {
        $this->seed(EmailTemplateSeeder::class);

        $signin = EmailTemplate::where('key', 'login_link.signin')->firstOrFail();
        expect(EmailTemplate::count())->toBe(2)
            ->and($signin->getTranslations('subject'))->toBe(['en' => 'Your sign-in link', 'ar' => 'رابط تسجيل الدخول'])
            ->and($signin->getTranslation('expiry', 'en'))->toBe('This link works for :minutes minutes.');

        $signin->setTranslation('subject', 'en', 'Edited')->save();
        $this->seed(EmailTemplateSeeder::class);

        expect(EmailTemplate::count())->toBe(2)
            ->and($signin->fresh()->getTranslation('subject', 'en'))->toBe('Edited');
    });
});

describe('the admin panel', function () {
    beforeEach(function () {
        $this->seed(EmailTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $this->template = EmailTemplate::where('key', 'login_link.signin')->firstOrFail();
    });

    it('edits the texts in both languages, which the next email uses', function () {
        Livewire::test(EditEmailTemplate::class, ['record' => $this->template->getRouteKey()])
            ->fillForm([
                'subject' => ['en' => 'Sign in, Sara', 'ar' => 'ادخلي يا سارة'],
                'expiry' => ['en' => 'Good for :minutes minutes.', 'ar' => ''],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->template->fresh()->getTranslations('subject'))->toBe(['en' => 'Sign in, Sara', 'ar' => 'ادخلي يا سارة']);

        $mail = sentLoginLinkMail('signin');
        $mail->assertSeeInHtml('Good for 15 minutes.');
        expect($mail->envelope()->subject)->toBe('Sign in, Sara');

        sentLoginLinkMail('signin', 'ar')->assertSeeInHtml('يعمل هذا الرابط لمدة 15 دقيقة.');
    });

    it('refuses dropping a required placeholder or using an unknown one', function () {
        Livewire::test(EditEmailTemplate::class, ['record' => $this->template->getRouteKey()])
            ->fillForm([
                'expiry' => ['en' => 'Good for a while.', 'ar' => 'صالح لمدة :minutes دقيقة.'],
                'line' => ['en' => 'Hi :name, tap the button.'],
            ])
            ->call('save')
            ->assertHasFormErrors(['expiry.en', 'line.en'])
            ->assertHasNoFormErrors(['expiry.ar']);

        expect($this->template->fresh()->getTranslation('expiry', 'en'))->toBe('This link works for :minutes minutes.');
    });

    it('lists the fixed templates without create or delete, and previews the email in both languages', function () {
        Livewire::test(ListEmailTemplates::class)
            ->assertCanSeeTableRecords(EmailTemplate::all())
            ->assertActionDoesNotExist('create');

        expect(EmailTemplateResource::canDelete($this->template))->toBeFalse();

        Livewire::test(EditEmailTemplate::class, ['record' => $this->template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview');

        expect(EmailTemplateResource::previewHtml($this->template, 'en'))->toContain('Sign in to Blexchat')
            ->and(EmailTemplateResource::previewHtml($this->template, 'ar'))->toContain('الدخول إلى بلكس تشات');
    });
});
