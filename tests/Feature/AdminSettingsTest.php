<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_view_and_update_ai_memory_setting(): void
    {
        $admin = User::query()->forceCreate($this->userPayload('Admin User', 'admin', 'admin@example.test'));

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Enable AI memory across lessons');

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'ai_memory_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertTrue((bool) AppSetting::getValue('ai_memory_enabled', false));

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), [])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertFalse((bool) AppSetting::getValue('ai_memory_enabled', true));
    }

    #[Test]
    public function student_cannot_access_admin_settings(): void
    {
        $student = User::query()->forceCreate($this->userPayload('Student User', 'student', 'student@example.test'));

        $this->actingAs($student)
            ->get(route('admin.settings.index'))
            ->assertStatus(403);
    }

    private function userPayload(string $name, string $role, string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ];
    }
}