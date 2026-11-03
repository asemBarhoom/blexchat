<?php

use App\Models\Plan;
use App\Models\PricingCompareCell;
use App\Models\PricingCompareRow;
use App\Models\PricingPage;

it('reads the page-wide figures from the plans', function () {
    $plans = collect([
        Plan::factory()->free()->make(['contacts_limit' => 1500]),
        Plan::factory()->make(['name' => ['en' => 'Essential'], 'monthly_price' => 20, 'annual_price' => 15, 'trial_days' => 7]),
        Plan::factory()->make(['monthly_price' => 100, 'annual_price' => 60]),
    ]);

    expect(PricingPage::figures($plans))->toBe(['max_saving' => 40, 'free_contacts' => 1500, 'trial_days' => 7, 'trial_plan' => 'Essential']);
});

it('fills figures in nested texts and leaves per-plan placeholders alone', function () {
    $figures = ['max_saving' => 30, 'free_contacts' => 2500, 'trial_days' => 14, 'trial_plan' => 'Essential'];

    expect(PricingPage::fillFigures(['hero' => ['save' => 'Save up to {max_saving}%'], 'card' => ['choose' => 'Choose {plan}'], 'faq' => '{free_contacts} contacts'], $figures))
        ->toBe(['hero' => ['save' => 'Save up to 30%'], 'card' => ['choose' => 'Choose {plan}'], 'faq' => '2,500 contacts']);
});

it('fills every text a locale lacks from English and says it lacks some', function () {
    $page = new PricingPage;
    $page->setTranslations('texts', [
        'en' => ['hero' => ['title' => 'Title', 'lead' => 'Lead']],
        'ar' => ['hero' => ['title' => 'عنوان', 'lead' => '']],
    ]);

    expect($page->copyIn('ar'))->toBe(['hero' => ['title' => 'عنوان', 'lead' => 'Lead']])
        ->and($page->lacksTextsIn('ar'))->toBeTrue()
        ->and($page->lacksTextsIn('en'))->toBeFalse();
});

it('gives a plan its cell, its plan field, or a dash', function () {
    $free = Plan::factory()->free()->make(['id' => 1, 'contacts_limit' => 25]);
    $pro = Plan::factory()->make(['id' => 2, 'has_ai' => true, 'contacts_limit' => 2500]);

    $row = (new PricingCompareRow)->setRelation('cells', collect([
        new PricingCompareCell(['plan_id' => 2, 'included' => false, 'text' => ['en' => 'Up to {contacts}']]),
        new PricingCompareCell(['plan_id' => 1, 'included' => true]),
    ]));

    expect($row->cellFor($pro))->toBe('Up to 2,500')
        ->and($row->cellFor($free))->toBeTrue()
        ->and($row->cellFor(Plan::factory()->make(['id' => 3])))->toBeFalse()
        ->and($row->fill(['source' => 'has_ai'])->cellFor($pro))->toBeTrue()
        ->and($row->cellFor($free))->toBeFalse()
        ->and($row->fill(['source' => 'paid'])->cellFor($pro))->toBeTrue()
        ->and($row->cellFor($free))->toBeFalse();
});
