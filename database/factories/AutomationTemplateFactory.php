<?php

namespace Database\Factories;

use App\Models\AutomationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationTemplate>
 */
class AutomationTemplateFactory extends Factory
{
    /**
     * A quick comment template that fills the wizard.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->bothify('t##??'),
            'name' => ['en' => fake()->sentence(4)],
            'line' => ['en' => fake()->sentence()],
            'build' => 'quick',
            'goal' => fake()->randomElement(AutomationTemplate::GOALS),
            'trigger' => 'comment',
            'locked' => false,
            'match' => 'words',
            'keywords' => ['en' => ['price']],
            'public_reply' => ['en' => 'Sent you the details in DM 💌'],
            'dm_text' => ['en' => 'Hi! Here is everything you asked about:'],
            'dm_button' => ['en' => 'See it'],
            'link_url' => 'https://shop.example.com/item',
        ];
    }

    /**
     * A flow template: it only names the trigger.
     */
    public function flow(): static
    {
        return $this->state(fn (array $attributes) => [
            'build' => 'flow',
            'match' => null,
            'keywords' => null,
            'public_reply' => null,
            'dm_text' => null,
            'dm_button' => null,
            'link_url' => null,
        ]);
    }

    /**
     * The wizard's default for a trigger.
     */
    public function starter(string $trigger = 'comment'): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => "starter-{$trigger}",
            'is_starter' => true,
            'trigger' => $trigger,
            'goal' => null,
            'line' => null,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => ['locked' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}
