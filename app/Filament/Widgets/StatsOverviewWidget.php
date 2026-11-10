<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MessageDeliveryAttemptResource;
use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDeliveryAttempt;
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
            Stat::make(__('admin/dashboard.channels'), $totalChannels)
                ->description(__('admin/dashboard.channels_note'))
                ->descriptionIcon('heroicon-o-signal')
                ->color('info'),

            Stat::make(__('admin/dashboard.automations'), $activeAutomations)
                ->description(__('admin/dashboard.automations_note'))
                ->descriptionIcon('heroicon-o-bolt')
                ->color('success'),

            Stat::make(__('admin/dashboard.open_conversations'), $openConversations)
                ->description(__('admin/dashboard.open_conversations_note'))
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('warning'),

            Stat::make(__('admin/dashboard.messages_today'), $messagesToday)
                ->description(__('admin/dashboard.messages_today_note'))
                ->descriptionIcon('heroicon-o-paper-airplane')
                ->color('primary'),

            Stat::make(__('admin/dashboard.auto_replies_today'), $automatedComments)
                ->description(__('admin/dashboard.auto_replies_today_note'))
                ->descriptionIcon('heroicon-o-cpu-chip')
                ->color('gray'),

            Stat::make(__('admin/dashboard.ai_replies_today'), Message::where('by_ai', true)->whereDate('sent_at', today())->count())
                ->description(__('admin/dashboard.ai_replies_today_note'))
                ->descriptionIcon('heroicon-o-sparkles')
                ->color('primary'),

            Stat::make(__('admin/dashboard.handovers_today'), Message::where('type', Message::TYPE_HANDOVER)->whereDate('sent_at', today())->count())
                ->description(__('admin/dashboard.handovers_today_note'))
                ->descriptionIcon('heroicon-o-user')
                ->color('danger'),

            Stat::make(__('admin/dashboard.channels_by_platform'), $totalChannels)
                ->description($this->channelsByPlatform())
                ->descriptionIcon('heroicon-o-squares-2x2')
                ->color('info'),

            Stat::make(__('admin/dashboard.paid_subscriptions'), $this->paidSubscriptions())
                ->description(__('admin/dashboard.running_trials', ['count' => Subscription::where('status', 'trialing')->where('trial_ends_at', '>', now())->count()]))
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('success'),

            Stat::make(__('admin/dashboard.failed_deliveries_today'), MessageDeliveryAttempt::where('status', 'failed')->whereDate('last_attempt_at', today())->count())
                ->description(__('admin/dashboard.failed_deliveries_today_note'))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(MessageDeliveryAttemptResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'failed']]])),
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
