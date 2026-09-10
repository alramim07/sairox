<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\User;
use App\Sairox\ThemeManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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

    public function test_theme_manager_resolves_standard_and_falls_back(): void
    {
        $manager = app(ThemeManager::class);

        $this->assertSame('theme::partials.content-standard', $manager->getPostContentView('standard'));
        $this->assertSame('theme::partials.content-standard', $manager->getPostContentView('unknown-format'));
        $this->assertSame('sairox-core::partials.content-standard', $manager->getPostContentView('quote', base_path('themes/does-not-exist')));
    }

    public function test_single_page_renders_standard_part_with_format_class(): void
    {
        Post::create([
            'post_title' => 'Standard Post',
            'post_content' => '<p>Hello format</p>',
            'slug' => 'standard-post',
            'post_status' => 'publish',
        ]);

        $this->get('/posts/standard-post')
            ->assertOk()
            ->assertSee('Hello format', false)
            ->assertSee('format-standard', false);
    }

    public function test_theme_manager_resolves_each_format_part(): void
    {
        $manager = app(ThemeManager::class);

        foreach (array_keys(Post::FORMATS) as $format) {
            if ($format === 'standard') {
                continue;
            }

            $this->assertSame("theme::partials.content-{$format}", $manager->getPostContentView($format));
        }
    }

    public function test_each_format_renders_its_part_on_the_single_page(): void
    {
        $cases = [
            'aside' => ['class' => 'format-aside', 'needle' => 'Aside body', 'marker' => 'italic'],
            'gallery' => ['class' => 'format-gallery', 'needle' => 'Gallery body', 'marker' => 'grid grid-cols-2', 'content' => '<p>Gallery body</p><img src="/grid/a.jpg">'],
            'link' => ['class' => 'format-link', 'needle' => 'https://example.com', 'marker' => 'href="https://example.com"'],
            'image' => ['class' => 'format-image', 'needle' => 'Image body'],
            'quote' => ['class' => 'format-quote', 'needle' => 'To be or not to be', 'marker' => '<blockquote'],
            'status' => ['class' => 'format-status', 'needle' => 'Status body', 'dontSee' => '<h1'],
            'video' => ['class' => 'format-video', 'needle' => 'Video body'],
            'audio' => ['class' => 'format-audio', 'needle' => 'Audio body'],
            'chat' => ['class' => 'format-chat', 'needle' => 'First line', 'marker' => 'bg-amber-50', 'content' => '<p>First line</p><p>Second line</p>'],
        ];

        foreach ($cases as $format => $expect) {
            Post::create([
                'post_title' => 'Formatted',
                'post_content' => $expect['content'] ?? "<p>{$expect['needle']}</p>",
                'slug' => "fmt-{$format}",
                'post_status' => 'publish',
                'post_format' => $format,
            ]);

            $response = $this->get("/posts/fmt-{$format}")
                ->assertOk()
                ->assertSee($expect['class'], false)
                ->assertSee($expect['needle'], false);

            if (isset($expect['marker'])) {
                $response->assertSee($expect['marker'], false);
            }

            if (isset($expect['dontSee'])) {
                $response->assertDontSee($expect['dontSee'], false);
            }
        }
    }

    public function test_quote_part_renders_a_blockquote(): void
    {
        Post::create([
            'post_title' => 'Quote',
            'post_content' => '<p>A quotable line.</p>',
            'slug' => 'quote-part',
            'post_status' => 'publish',
            'post_format' => 'quote',
        ]);

        $this->get('/posts/quote-part')
            ->assertOk()
            ->assertSee('<blockquote', false);
    }

    public function test_chat_part_renders_chat_bubbles(): void
    {
        Post::create([
            'post_title' => 'Chat',
            'post_content' => '<p>Hello there</p><p>Hi back</p>',
            'slug' => 'chat-part',
            'post_status' => 'publish',
            'post_format' => 'chat',
        ]);

        $this->get('/posts/chat-part')
            ->assertOk()
            ->assertSee('format-chat', false)
            ->assertSee('bg-amber-50', false)
            ->assertSee('Hello there', false)
            ->assertSee('Hi back', false);
    }

    public function test_gallery_part_renders_an_image_grid(): void
    {
        Post::create([
            'post_title' => 'Gallery',
            'post_content' => '<p><img src="/img/a.jpg"><img src="/img/b.jpg"></p>',
            'slug' => 'gallery-part',
            'post_status' => 'publish',
            'post_format' => 'gallery',
        ]);

        $this->get('/posts/gallery-part')
            ->assertOk()
            ->assertSee('/img/a.jpg', false)
            ->assertSee('/img/b.jpg', false)
            ->assertSee('grid grid-cols-2', false)
            ->assertSee('format-gallery', false);
    }

    public function test_preview_of_a_formatted_draft_uses_its_part(): void
    {
        Filament::setCurrentPanel('admin');
        config(['app.env' => 'local']);

        $author = User::factory()->create();

        $post = Post::create([
            'post_author' => $author->getKey(),
            'post_title' => 'Draft Quote',
            'post_content' => '<p>Preview me</p>',
            'slug' => 'draft-quote',
            'post_status' => 'draft',
            'post_format' => 'quote',
        ]);

        $url = URL::signedRoute('preview.post', ['slug' => $post->slug]);

        $this->get($url)
            ->assertOk()
            ->assertSee('format-quote', false)
            ->assertSee('Preview me', false);
    }

    public function test_chat_lines_helper_splits_paragraphs(): void
    {
        $post = Post::create([
            'post_title' => 'Chat',
            'post_content' => '<p>First</p><p class="x">Second</p>',
            'slug' => 'chat-helper',
            'post_status' => 'draft',
        ]);

        $this->assertSame(['First', 'Second'], $post->chatLines());
    }

    public function test_gallery_images_helper_extracts_src_attributes(): void
    {
        $post = Post::create([
            'post_title' => 'Gallery',
            'post_content' => '<p><img src="/a.jpg" alt=""><img src="/b.jpg"></p>',
            'slug' => 'gallery-helper',
            'post_status' => 'draft',
        ]);

        $this->assertSame(['/a.jpg', '/b.jpg'], $post->galleryImages());
    }
}
