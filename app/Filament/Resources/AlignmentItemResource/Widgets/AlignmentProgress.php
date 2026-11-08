<?php

namespace App\Filament\Resources\AlignmentItemResource\Widgets;

use App\Models\AlignmentItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How far the alignment has got, for each owner: items done out of all, and what is still open.
 */
class AlignmentProgress extends BaseWidget
{
    protected function getStats(): array
    {
        $counts = AlignmentItem::query()
            ->selectRaw('owner, status, count(*) as total')
            ->groupBy('owner', 'status')
            ->get()
            ->groupBy('owner');

        $stats = [];

        foreach (AlignmentItem::OWNERS as $owner => $label) {
            $byStatus = ($counts[$owner] ?? collect())->pluck('total', 'status');
            $all = (int) $byStatus->sum();
            $done = (int) ($byStatus['done'] ?? 0);
            $open = (int) ($byStatus['open'] ?? 0);
            $partial = (int) ($byStatus['partial'] ?? 0);
            $p0 = AlignmentItem::where('owner', $owner)->where('priority', 'p0')->where('status', '!=', 'done')->count();

            $stats[] = Stat::make($label, "{$done} / {$all}")
                ->description("مفتوح {$open} · جزئي {$partial}".($p0 > 0 ? " · عاجل {$p0}" : ''))
                ->color($p0 > 0 ? 'danger' : ($done === $all ? 'success' : 'warning'));
        }

        return $stats;
    }
}
