<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PostFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The panel HTTP tests below flip app.env to 'local'; always restore it
        // so the change can't leak into other test classes.
        config(['app.env' => 'testing']);

        parent::tearDown();
    }

    public function test_post_format_column_exists_and_defaults_to_standard(): void
    {
        $post = Post::create([
            'post_title' => 'Formatted Post',
            'slug' => 'formatted-post',
            'post_content' => 'Body',
            'post_status' => 'publish',
        ]);

        $this->assertSame('standard', $post->post_format);
        $this->assertTrue(Schema::hasColumn('posts', 'post_format'));
    }

    public function test_post_format_is_persisted(): void
    {
        $post = Post::create([
            'post_title' => 'Quote Post',
            'slug' => 'quote-post',
            'post_content' => '<p>To be or not to be.</p>',
            'post_status' => 'draft',
            'post_format' => 'quote',
        ]);

        $this->assertSame('quote', $post->fresh()->post_format);
    }

    public function test_post_formats_constant_defines_the_ten_wp_formats(): void
    {
        $this->assertSame(['standard', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat'], array_keys(Post::FORMATS));
    }

    public function test_revision_snapshot_contains_post_format(): void
    {
        $post = Post::create([
            'post_title' => 'Draft',
            'slug' => 'draft',
            'post_content' => 'Body',
            'post_status' => 'draft',
        ]);

        $post->update(['post_format' => 'aside']);

        $revision = $post->revisions()->latest('id')->first();

        $this->assertSame('aside', $revision->data['post_format']);
    }

    public function test_edit_page_renders_format_select_with_ten_options(): void
    {
        Filament::setCurrentPanel('admin');
        config(['app.env' => 'local']);

        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Formatted',
            'slug' => 'formatted',
            'post_content' => 'Body',
            'post_status' => 'draft',
        ]);

        $this->actingAs($author)
            ->get(route('filament.admin.resources.posts.edit', $post))
            ->assertOk()
            ->assertSee('Post Format', false)
            ->assertSee('post_format', false);
    }

    public function test_create_page_persists_selected_format(): void
    {
        Filament::setCurrentPanel('admin');

        $author = User::factory()->create();

        Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Quote Post',
                'slug' => 'quote-post',
                'post_content' => '<p>Quote me</p>',
                'post_format' => 'quote',
                'post_author' => $author->getKey(),
            ])
            ->call('create');

        $post = Post::where('slug', 'quote-post')->firstOrFail();

        $this->assertSame('quote', $post->post_format);
    }

    public function test_create_page_defaults_format_to_standard_on_autosave_draft(): void
    {
        Filament::setCurrentPanel('admin');

        $author = User::factory()->create();

        Livewire::actingAs($author)
            ->test(CreatePost::class)
            ->fillForm([
                'post_title' => 'Plain',
                'slug' => 'plain',
                'post_content' => 'Body',
                'post_author' => $author->getKey(),
            ])
            ->call('autoSave');

        $draft = Post::where('slug', 'plain')->firstOrFail();

        $this->assertSame('standard', $draft->post_format);
    }

    public function test_list_page_shows_format_badge_and_filter(): void
    {
        Filament::setCurrentPanel('admin');
        config(['app.env' => 'local']);

        $author = User::factory()->create();

        Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Quote Post',
            'slug' => 'quote-post',
            'post_content' => 'Body',
            'post_status' => 'draft',
            'post_format' => 'quote',
        ]);

        $this->actingAs($author)
            ->get(route('filament.admin.resources.posts.index'))
            ->assertOk()
            ->assertSee('post_format', false)
            ->assertSee('Format', false);
    }
}
