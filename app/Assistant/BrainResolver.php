<?php

namespace App\Assistant;

use App\Assistant\Brains\SellerRuleBrain;
use App\Models\User;
use Illuminate\Contracts\Container\Container;

/** يختار العقل حسب المستخدم: البائع صاحب المتجر يحصل على عقل البائع، والباقي عقل العملاء */
class BrainResolver
{
    public function __construct(private readonly Container $app) {}

    public function for(?User $user): AssistantBrain
    {
        if ($user && $user->role === 'seller' && $user->sellerProfile) {
            $class = config('assistant.seller_drivers', [])[config('assistant.seller_driver')] ?? null;
            $brain = $class && class_exists($class) ? $this->app->make($class) : null;

            return $brain instanceof AssistantBrain ? $brain : $this->app->make(SellerRuleBrain::class);
        }

        return $this->app->make(AssistantBrain::class);
    }
}
