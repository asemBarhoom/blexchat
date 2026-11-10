<?php

namespace App\Filament\Widgets;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalChannels = Channel::count();
        $activeAutomations = Automation::where('active', true)->count();
        $openConversations = Conversation::where('status', 'open')->count();
        $messagesToday = Message::whereDate('sent_at', today())->count();
        $automatedComments = Comment::where('automated_reply', true)
            ->whereDate('posted_at', today())->count();

        return [
            Stat::make('القنوات المربوطة', $totalChannels)
                ->description('إجمالي القنوات النشطة')
                ->descriptionIcon('heroicon-o-signal')
                ->color('info'),

            Stat::make('الأتمتة النشطة', $activeAutomations)
                ->description('قواعد مفعّلة حالياً')
                ->descriptionIcon('heroicon-o-bolt')
                ->color('success'),

            Stat::make('محادثات مفتوحة', $openConversations)
                ->description('تحتاج متابعة')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('warning'),

            Stat::make('رسائل اليوم', $messagesToday)
                ->description('مُرسَلة ووارده')
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color('primary'),

            Stat::make('ردود تلقائية اليوم', $automatedComments)
                ->description('تعليقات ردّ عليها بوت')
                ->descriptionIcon('heroicon-o-cpu-chip')
                ->color('gray'),

            Stat::make('ردود Blexchat AI اليوم', Message::where('by_ai', true)->whereDate('sent_at', today())->count())
                ->description('رسائل كتبها الذكاء الاصطناعي')
                ->descriptionIcon('heroicon-o-sparkles')
                ->color('primary'),

            Stat::make('تحويلات لموظف اليوم', Message::where('type', Message::TYPE_HANDOVER)->whereDate('sent_at', today())->count())
                ->description('محادثات احتاجت إنساناً')
                ->descriptionIcon('heroicon-o-user')
                ->color('danger'),

            Stat::make('القنوات حسب المنصة', $totalChannels)
                ->description($this->channelsByPlatform())
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('info'),

            Stat::make('الاشتراكات المدفوعة', $this->paidSubscriptions())
                ->description('تجارب شغّالة: '.Subscription::where('status', 'trialing')->where('trial_ends_at', '>', now())->count())
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('success'),
        ];
    }

    /**
     * One line per platform, e.g. "Instagram 12 · WhatsApp 3 · Messenger 0 · TikTok 1".
     */
    private function channelsByPlatform(): string
    {
        $counts = Channel::query()->selectRaw('platform, count(*) as total')->groupBy('platform')->pluck('total', 'platform');

        return collect(['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp', 'messenger' => 'Messenger', 'tiktok' => 'TikTok'])
            ->map(fn (string $name, string $platform): string => $name.' '.($counts[$platform] ?? 0))
            ->implode(' · ');
    }

    /**
     * Active subscriptions on a plan that costs money (the free plan does not count).
     */
    private function paidSubscriptions(): int
    {
        return Subscription::where('status', 'active')
            ->whereHas('plan', fn (Builder $query): Builder => $query->where('monthly_price', '>', 0))
            ->count();
    }
}
