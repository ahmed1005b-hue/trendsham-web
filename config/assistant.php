<?php

return [
    /*
    | "العقل" الذي يفهم رسائل المساعد ويرد عليها.
    | rules = مجاني بالكامل (قواعد + فهم للعامية) — الافتراضي.
    | لاحقاً يمكن إضافة سائق ذكاء اصطناعي هنا وتفعيله من ASSISTANT_DRIVER.
    */
    'driver' => env('ASSISTANT_DRIVER', 'rules'),

    'drivers' => [
        'rules' => \App\Assistant\Brains\RuleBasedBrain::class,
    ],

    // عقل البائع (أرقام متجره ونصائح وأوصاف) — مستقل ليمكن ترقيته وحده
    'seller_driver' => env('ASSISTANT_SELLER_DRIVER', 'rules'),

    'seller_drivers' => [
        'rules' => \App\Assistant\Brains\SellerRuleBrain::class,
    ],
];
