<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutoSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        config(['app.env' => 'testing']);

        parent::tearDown();
    }

    public function test_autosave_updates_a_draft_post(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft title',
            'post_content' => 'Old content',
            'post_excerpt' => '',
            'slug' => 'draft-title',
            'post_status' => 'draft',
        ]);

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'post_title' => 'Draft title (updated)',
                'post_content' => 'New content',
                'slug' => 'draft-title',
            ])
            ->call('autoSave')
            ->assertSet('lastAutoSaved', fn (?string $value): bool => is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1);

        $post->refresh();

        $this->assertSame('Draft title (updated)', $post->post_title);
        $this->assertSame('<p>New content</p>', $post->post_content);
        $this->assertSame('draft', $post->post_status);
    }

    public function test_autosave_does_not_touch_published_posts(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Published title',
            'post_content' => 'Old content',
            'slug' => 'published-title',
            'post_status' => 'publish',
        ]);

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['post_title' => 'Should not autosave'])
            ->call('autoSave');

        $post->refresh();

        $this->assertSame('Published title', $post->post_title);
    }

    public function test_autosave_never_clobbers_valid_data_when_required_fields_are_missing(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Original title',
            'post_content' => 'Original content',
            'slug' => 'original-title',
            'post_status' => 'draft',
        ]);

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['post_title' => ''])
            ->call('autoSave');

        $post->refresh();

        $this->assertSame('Original title', $post->post_title);
        $this->assertSame('Original content', $post->post_content);
    }

    public function test_autosave_indicator_renders_on_the_post_edit_page(): void
    {
        config(['app.env' => 'local']);

        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft title',
            'post_content' => 'Hello',
            'slug' => 'draft-title',
            'post_status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('filament.admin.resources.posts.edit', $post))
            ->assertOk()
            ->assertSee('auto-save-indicator', false)
            ->assertSee('auto-saved', false);
    }

    public function test_autosave_indicator_does_not_render_on_unrelated_pages(): void
    {
        config(['app.env' => 'local']);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('auto-save-indicator', false);
    }
}
