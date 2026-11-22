<?php

namespace Tests\Feature;

use Tests\TestCase;

/** التطبيق يسأل عن أحدث إصدار وأقدم إصدار مسموح ليعرض «حدّث التطبيق». */
class AppVersionApiTest extends TestCase
{
    public function test_the_app_gets_the_latest_and_minimum_versions(): void
    {
        config([
            'mobile.android.latest_version' => '1.2.0',
            'mobile.android.min_version' => '1.1.0',
            'mobile.android.store_url' => 'https://play.google.com/store/apps/details?id=x',
        ]);

        $this->getJson('/api/v1/app-version')->assertOk()->assertExactJson([
            'latest_version' => '1.2.0',
            'min_version' => '1.1.0',
            'store_url' => 'https://play.google.com/store/apps/details?id=x',
        ]);
    }
}
