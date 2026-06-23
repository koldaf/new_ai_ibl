<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_typed_boolean_values_with_defaults(): void
    {
        $this->assertFalse((bool) AppSetting::getValue('ai_memory_enabled', false));

        AppSetting::putValue('ai_memory_enabled', true, 'boolean');
        $this->assertTrue((bool) AppSetting::getValue('ai_memory_enabled', false));

        AppSetting::putValue('ai_memory_enabled', false, 'boolean');
        $this->assertFalse((bool) AppSetting::getValue('ai_memory_enabled', true));
    }
}