<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\LessonStageContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminHtmlSanitizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_stage_wysiwyg_content_is_sanitized_but_keeps_allowed_markup(): void
    {
        $admin = User::query()->forceCreate($this->userPayload('Admin User', 'admin', 'admin-sanitize@example.test'));

        $lesson = Lesson::create([
            'title' => 'Sanitize Stage Content',
            'description' => 'Initial description',
        ]);

        $dirtyHtml = '<p><strong>Keep</strong> <em>formatting</em>.</p>' .
            '<ul><li>One</li><li>Two</li></ul>' .
            '<table><tr><th>H</th></tr><tr><td colspan="2" onclick="evil()">Cell</td></tr></table>' .
            '<p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB" alt="tiny" onerror="evil()"></p>' .
            '<p><img src="https://example.com/size.png" width="640" height="abc" onload="evil()"></p>' .
            '<p><a href="javascript:alert(1)" onclick="evil()">bad link</a></p>' .
            '<p><a href="https://example.com" target="_blank" onclick="evil()">good link</a></p>' .
            '<script>alert("xss")</script>';

        $this->actingAs($admin)
            ->postJson(route('admin.lessons.stages.text', ['lesson' => $lesson, 'stage' => 'explain']), [
                'content' => $dirtyHtml,
                'content_type' => 'wysiwyg',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $saved = LessonStageContent::query()
            ->where('lesson_id', $lesson->id)
            ->where('stage', 'explain')
            ->value('content');

        $this->assertNotNull($saved);
        $this->assertStringContainsString('<strong>Keep</strong>', $saved);
        $this->assertStringContainsString('<em>formatting</em>', $saved);
        $this->assertStringContainsString('<ul>', $saved);
        $this->assertStringContainsString('<li>One</li>', $saved);
        $this->assertStringContainsString('<table>', $saved);
        $this->assertStringContainsString('<th>H</th>', $saved);
        $this->assertStringContainsString('<td colspan="2">Cell</td>', $saved);
        $this->assertStringContainsString('<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB" alt="tiny">', $saved);
        $this->assertStringContainsString('<img src="https://example.com/size.png" width="640">', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('onclick=', $saved);
        $this->assertStringNotContainsString('onload=', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringContainsString('href="https://example.com"', $saved);
        $this->assertStringContainsString('target="_blank"', $saved);
        $this->assertStringContainsString('rel="noopener noreferrer"', $saved);
    }

    #[Test]
    public function admin_lesson_description_update_is_sanitized(): void
    {
        $admin = User::query()->forceCreate($this->userPayload('Admin User', 'admin', 'admin-description@example.test'));

        $lesson = Lesson::create([
            'title' => 'Lesson For Description Sanitization',
            'description' => 'Old',
        ]);

        $dirtyDescription = '<h2>Heading</h2><p>Safe <u>underline</u></p><img src="https://example.com/photo.png" onerror="alert(1)"><img src="javascript:alert(2)"><script>hack()</script>';

        $this->actingAs($admin)
            ->put(route('admin.lessons.update', $lesson), [
                'title' => $lesson->title,
                'subject' => 'Science',
                'grade_level' => 'Grade 5',
                'description' => $dirtyDescription,
            ])
            ->assertRedirect(route('admin.lessons.edit', $lesson));

        $lesson->refresh();

        $this->assertStringContainsString('<h2>Heading</h2>', (string) $lesson->description);
        $this->assertStringContainsString('<u>underline</u>', (string) $lesson->description);
        $this->assertStringContainsString('<img src="https://example.com/photo.png">', (string) $lesson->description);
        $this->assertStringNotContainsString('<script', (string) $lesson->description);
        $this->assertStringNotContainsString('javascript:', (string) $lesson->description);
        $this->assertStringNotContainsString('onerror=', (string) $lesson->description);
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
