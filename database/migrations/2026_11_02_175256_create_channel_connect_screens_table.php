<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The copy of the connect-a-channel screen (W06), one row per channel. Texts are stored per language.
     */
    public function up(): void
    {
        Schema::create('channel_connect_screens', function (Blueprint $table): void {
            $table->id();
            $table->string('platform')->unique()->comment('instagram | whatsapp | tiktok | messenger');
            $table->string('method')->comment('meta | tiktok: whose sign-in connects it, which sets the button mark');
            $table->json('name');
            $table->json('cta');
            $table->json('lead');
            $table->json('steps')->comment('Three lines per language');
            $table->json('options')->nullable()->comment('"See more options": title, note and url per item, per language');
            $table->json('account_kind')->nullable()->comment('What a connectable account is, under its handle');
            $table->json('can')->nullable()->comment('What Blexchat can do once connected (W07)');
            $table->json('reconnect_cta');
            $table->json('waiting_in');
            $table->json('ineligible_title');
            $table->json('ineligible_text');
            $table->json('ineligible_kind')->nullable();
            $table->json('ineligible_how')->nullable();
            $table->string('ineligible_help_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connect_screens');
    }
};
