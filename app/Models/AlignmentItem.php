<?php

namespace App\Models;

use Database\Factories\AlignmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One gap between the Figma design, the API and the frontend, tracked in the admin panel
 * until it is closed: who owns it, how urgent it is, and where it lives in each place.
 *
 * @property string $code e.g. FE-07, BE-08, DS-02
 * @property string $title
 * @property string $owner one of self::OWNERS
 * @property string $priority one of self::PRIORITIES
 * @property string $status one of self::STATUSES
 * @property string|null $screen
 * @property string|null $figma_url
 * @property string|null $endpoint
 * @property string|null $frontend_ref
 * @property string|null $backend_ref
 * @property string|null $details
 */
#[Fillable(['code', 'title', 'owner', 'priority', 'status', 'screen', 'figma_url', 'endpoint', 'frontend_ref', 'backend_ref', 'details'])]
class AlignmentItem extends Model
{
    /** @use HasFactory<AlignmentItemFactory> */
    use HasFactory;

    public const FIGMA_FILE = 'https://www.figma.com/design/3F9ollZMdHJWqeWTCnhoQT/blexchat--Copy-';

    /** @return array<string, string> */
    public static function owners(): array
    {
        return ['frontend' => __('admin/alignment.owner_frontend'), 'backend' => __('admin/alignment.owner_backend'), 'design' => __('admin/alignment.owner_design')];
    }

    /** @return array<string, string> */
    public static function priorities(): array
    {
        return ['p0' => __('admin/alignment.priority_p0'), 'p1' => 'P1', 'p2' => 'P2'];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return ['open' => __('admin/alignment.status_open'), 'partial' => __('admin/alignment.status_partial'), 'done' => __('admin/alignment.status_done')];
    }

    /**
     * A link to a node of the Figma file, from its id as Figma writes it ("49:10433" or "49-10433").
     */
    public static function figmaNode(string $nodeId): string
    {
        return self::FIGMA_FILE.'?node-id='.str_replace(':', '-', $nodeId);
    }
}
