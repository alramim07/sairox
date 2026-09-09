<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
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
        // The panel HTTP tests below flip app.env to 'local'; always restore it
        // so the change can't leak into other test classes.
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

    public function test_autosave_preserves_an_existing_excerpt(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft title',
            'post_content' => 'Old content',
            'post_excerpt' => 'My custom excerpt',
            'slug' => 'draft-title',
            'post_status' => 'draft',
        ]);

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'post_title' => 'Draft title (updated)',
                'post_content' => 'Old content',
                'slug' => 'draft-title',
            ])
            ->call('autoSave');

        $post->refresh();

        $this->assertSame('My custom excerpt', $post->post_excerpt);
        $this->assertSame('Draft title (updated)', $post->post_title);
    }

    public function test_autosave_preserves_an_existing_thumbnail_as_a_string(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft title',
            'post_content' => 'Old content',
            'slug' => 'draft-title',
            'post_status' => 'draft',
            'thumbnail' => 'thumbnails/cat.jpg',
        ]);

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'post_title' => 'Draft title (updated)',
                'post_content' => 'Old content',
                'slug' => 'draft-title',
            ])
            ->call('autoSave')
            ->assertSet('lastAutoSaved', fn (?string $value): bool => is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1);

        $post->refresh();

        $this->assertSame('thumbnails/cat.jpg', $post->thumbnail);
    }

    public function test_autosave_does_not_create_revisions(): void
    {
        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft title',
            'post_content' => 'Old content',
            'slug' => 'draft-title',
            'post_status' => 'draft',
        ]);

        $this->assertSame(1, $post->revisions()->count());

        Livewire::actingAs($author)
            ->test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm([
                'post_title' => 'Draft title (updated)',
                'slug' => 'draft-title',
            ])
            ->call('autoSave');

        $post->refresh();

        $this->assertSame(1, $post->revisions()->count());
    }

    public function test_autosave_indicator_renders_on_the_post_edit_page(): void
    {
        // The User model does not implement FilamentUser, so the panel's auth
        // middleware (Authenticate.php) only lets requests through when
        // app.env === 'local'. Tests run under 'testing', hence the toggle.
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
        // Same app.env === 'local' requirement as the sibling HTTP test above.
        config(['app.env' => 'local']);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('auto-save-indicator', false);
    }

    public function test_auto_save_on_create_creates_draft_and_updates_it(): void
    {
        $author = User::factory()->create();

        $component = Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Draft title',
                'slug' => 'draft-title',
                'post_content' => 'First content',
            ])
            ->call('autoSave');

        $draft = Post::where('slug', 'draft-title')->firstOrFail();

        $this->assertSame('draft', $draft->post_status);
        $this->assertSame('<p>First content</p>', $draft->post_content);

        $component
            ->assertSet('autoSavedPostId', $draft->id)
            ->fillForm([
                'post_title' => 'Draft title (updated)',
                'slug' => 'draft-title',
                'post_content' => 'Updated content',
            ])
            ->call('autoSave');

        $draft->refresh();

        $this->assertSame(1, Post::where('slug', 'draft-title')->count());
        $this->assertSame('Draft title (updated)', $draft->post_title);
        $this->assertSame('<p>Updated content</p>', $draft->post_content);
        $this->assertSame('draft', $draft->post_status);
    }

    public function test_create_reuses_auto_saved_draft_not_duplicates(): void
    {
        $author = User::factory()->create();

        Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Final title',
                'slug' => 'final-slug',
                'post_content' => 'Final content',
                'post_status' => 'publish',
                'post_author' => $author->getKey(),
            ])
            ->call('autoSave')
            ->call('create');

        $post = Post::where('slug', 'final-slug')->first();

        $this->assertNotNull($post);
        $this->assertSame(1, Post::where('slug', 'final-slug')->count());
        $this->assertSame('Final title', $post->post_title);
        $this->assertSame('publish', $post->post_status);
        $this->assertSame('<p>Final content</p>', $post->post_content);

        $revision = $post->revisions()->orderBy('id', 'desc')->first();

        $this->assertNotNull($revision);
        $this->assertSame('Final title', $revision->data['post_title']);
        $this->assertSame('publish', $revision->data['post_status']);
    }

    public function test_auto_save_on_create_skips_if_title_empty(): void
    {
        $author = User::factory()->create();

        Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm(['post_title' => ''])
            ->call('autoSave')
            ->assertSet('autoSavedPostId', null)
            ->assertSet('lastAutoSaved', null);

        $this->assertSame(0, Post::count());
    }

    public function test_create_autosave_draft_does_not_accumulate_revisions(): void
    {
        $author = User::factory()->create();

        Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Draft title',
                'slug' => 'draft-title',
                'post_content' => 'First content',
            ])
            ->call('autoSave');

        $draft = Post::where('slug', 'draft-title')->firstOrFail();

        $this->assertSame(0, $draft->revisions()->count());
    }

    public function test_create_uses_author_and_status_defaults(): void
    {
        $author = User::factory()->create();

        $component = Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Final title',
                'slug' => 'final-slug',
                'post_content' => 'Final content',
            ]);

        $component->call('autoSave');

        $draft = Post::where('slug', 'final-slug')->firstOrFail();

        $this->assertSame($author->getKey(), $draft->post_author);
        $this->assertSame('draft', $draft->post_status);

        $component->call('create');

        $post = Post::where('slug', 'final-slug')->firstOrFail();

        $this->assertSame($author->getKey(), $post->post_author);
        $this->assertSame('draft', $post->post_status);
        $this->assertSame(1, Post::where('slug', 'final-slug')->count());
    }
}
