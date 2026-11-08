<?php

namespace Database\Factories;

use App\Models\AlignmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlignmentItem>
 */
class AlignmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('FE-###'),
            'title' => fake()->sentence(5),
            'owner' => 'frontend',
            'priority' => 'p1',
            'status' => 'open',
            'screen' => 'Inbox',
            'figma_url' => AlignmentItem::figmaNode('55:6087'),
            'endpoint' => 'GET /conversations',
            'frontend_ref' => 'src/app/liveInbox.ts:118',
            'backend_ref' => null,
            'details' => fake()->paragraph(),
        ];
    }

    public function backend(): static
    {
        return $this->state(fn (): array => ['code' => fake()->unique()->bothify('BE-###'), 'owner' => 'backend']);
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => 'done']);
    }
}
