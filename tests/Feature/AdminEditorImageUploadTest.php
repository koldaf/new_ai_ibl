<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminEditorImageUploadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_upload_editor_image_and_receive_public_url(): void
    {
        Storage::fake('public');

        $admin = User::query()->forceCreate([
            'name' => 'Editor Admin',
            'email' => 'editor-admin@example.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.editor-images.store'), [
                'image' => UploadedFile::fake()->image('paste.png', 120, 80),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['path', 'url'],
            ]);

        $path = $response->json('data.path');
        $url = $response->json('data.url');

        $this->assertNotEmpty($path);
        $this->assertStringContainsString('/storage/editor-images/', (string) $url);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function non_admin_cannot_upload_editor_image(): void
    {
        $student = User::query()->forceCreate([
            'name' => 'Student User',
            'email' => 'student-editor@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $this->actingAs($student)
            ->post(route('admin.editor-images.store'), [
                'image' => UploadedFile::fake()->image('paste.png', 120, 80),
            ])
            ->assertStatus(403);
    }
}
