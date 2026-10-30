<?php

namespace App\Filament\Widgets;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalChannels      = Channel::count();
        $activeAutomations  = Automation::where('active', true)->count();
        $openConversations  = Conversation::where('status', 'open')->count();
        $messagesToday      = Message::whereDate('sent_at', today())->count();
        $automatedComments  = Comment::where('automated_reply', true)
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
        ];
    }
}
