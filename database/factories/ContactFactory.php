<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Someone who messaged one of the owner's Instagram channels.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $handle = fake()->unique()->userName();

        return [
            'user_id' => User::factory(),
            'channel_id' => fn (array $attributes) => Channel::factory()->create(['user_id' => $attributes['user_id'], 'platform' => 'instagram'])->id,
            'platform' => 'instagram',
            'platform_id' => fake()->unique()->numerify('ig_user_#########'),
            'name' => fake()->name(),
            'handle' => $handle,
            'tags' => [],
            'subscribed' => true,
            'added_manually' => false,
            'first_seen_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'last_active_at' => fake()->dateTimeBetween('-1 day'),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    /**
     * Brought in by an automation answering their comment.
     */
    public function fromAutomation(?Automation $automation = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source_automation_id' => $automation?->id ?? Automation::factory()->create(['user_id' => $attributes['user_id']])->id,
        ]);
    }

    /**
     * Added by hand from Create New Contact: a WhatsApp number, with their consent.
     */
    public function addedManually(): static
    {
        return $this->state(function (array $attributes) {
            $phone = '+966 '.fake()->numerify('5# ### ####');

            return [
                'channel_id' => null,
                'platform' => 'whatsapp',
                'platform_id' => null,
                'handle' => $phone,
                'phone' => $phone,
                'added_manually' => true,
                'consented_at' => now(),
                'last_active_at' => null,
            ];
        });
    }
}
