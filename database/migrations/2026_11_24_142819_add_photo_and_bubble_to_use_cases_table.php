<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A photo cut into each use-case card's shape, and a customer question with Blexchat's automatic
 * answer shown inside it. The cards already live on production, so they get the first bubbles here.
 */
return new class extends Migration
{
    /**
     * Each card's question and answer: [en, ar].
     *
     * @var array<string, array{question: array{0: string, 1: string}, answer: array{0: string, 1: string}}>
     */
    public const BUBBLES = [
        'creators' => ['question' => ['Link please 🔗', 'الرابط 🔗'], 'answer' => ['Sent to your DMs ✨', 'وصلك بالخاص ✨']],
        'ecommerce' => ['question' => ['How much is the abaya?', 'كم سعر العباية؟'], 'answer' => ['SAR 349, and here’s the order link 🛍️', '349 ريال، وهذا رابط الطلب 🛍️']],
        'restaurants' => ['question' => ['A table for 4 at 9?', 'فيه طاولة لـ 4 الساعة 9؟'], 'answer' => ['Booked ✓ See you at 9', 'تم الحجز ✓ نشوفكم 9']],
        'clinics' => ['question' => ['Any appointment tomorrow?', 'فيه موعد بكرة؟'], 'answer' => ['10:30 AM is free. Shall I book it?', '10:30 الصبح متاح، أأكده لك؟']],
        'agencies' => ['question' => ['How many accounts can I run?', 'كم حساب أقدر أدير؟'], 'answer' => ['All your clients, from one place ✓', 'كل عملائك من مكان واحد ✓']],
        'realestate' => ['question' => ['Is the apartment still available?', 'الشقة لسا متاحة؟'], 'answer' => ['Yes! When suits you for a viewing?', 'إيه، متى يناسبك المعاينة؟']],
    ];

    public function up(): void
    {
        Schema::table('use_cases', function (Blueprint $table): void {
            $table->string('photo')->nullable()->after('tone');
            $table->json('bubble_question')->nullable()->after('photo');
            $table->json('bubble_answer')->nullable()->after('bubble_question');
        });

        foreach (self::BUBBLES as $key => $bubble) {
            DB::table('use_cases')->where('key', $key)->whereNull('bubble_question')->update([
                'bubble_question' => json_encode(['en' => $bubble['question'][0], 'ar' => $bubble['question'][1]], JSON_UNESCAPED_UNICODE),
                'bubble_answer' => json_encode(['en' => $bubble['answer'][0], 'ar' => $bubble['answer'][1]], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('use_cases', function (Blueprint $table): void {
            $table->dropColumn(['photo', 'bubble_question', 'bubble_answer']);
        });
    }
};
