<?php

namespace Database\Factories;

use App\Models\SellerProfile;
use App\Models\SellerSupportGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerSupportGrant>
 */
class SellerSupportGrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_profile_id' => fn () => SellerProfile::create([
                'user_id' => User::factory()->create(['role' => 'seller'])->id,
                'store_name' => fake()->company(),
                'status' => 'approved',
            ])->id,
            'granted_by' => fn (array $attributes) => SellerProfile::find($attributes['seller_profile_id'])?->user_id,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
