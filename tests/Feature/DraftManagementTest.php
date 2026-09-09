<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DraftManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_url_shows_draft_post(): void
    {
        $post = Post::create([
            'post_title' => 'Draft Preview',
            'post_content' => '<p>Draft body content</p>',
            'slug' => 'draft-preview',
            'post_status' => 'draft',
        ]);

        $url = URL::signedRoute('preview.post', ['slug' => $post->slug]);

        $this->get($url)
            ->assertStatus(200)
            ->assertSee('Draft Preview')
            ->assertSee('Draft body content');
    }

    public function test_preview_url_requires_valid_signature(): void
    {
        Post::create([
            'post_title' => 'Private Draft',
            'post_content' => 'Secret',
            'slug' => 'private-draft',
            'post_status' => 'draft',
        ]);

        $this->get('/preview/posts/private-draft')
            ->assertStatus(403);
    }

    public function test_preview_url_shows_published_post(): void
    {
        $post = Post::create([
            'post_title' => 'Published Preview',
            'post_content' => 'Content',
            'slug' => 'published-preview',
            'post_status' => 'publish',
        ]);

        $this->get(URL::signedRoute('preview.post', ['slug' => $post->slug]))
            ->assertStatus(200)
            ->assertSee('Published Preview');
    }

    public function test_preview_url_shows_scheduled_post(): void
    {
        $post = Post::create([
            'post_title' => 'Scheduled Preview',
            'post_content' => 'Content',
            'slug' => 'scheduled-preview',
            'post_status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->get(URL::signedRoute('preview.post', ['slug' => $post->slug]))
            ->assertStatus(200)
            ->assertSee('Scheduled Preview');
    }

    public function test_preview_url_for_unknown_slug_returns_404(): void
    {
        $url = URL::signedRoute('preview.post', ['slug' => 'does-not-exist']);

        $this->get($url)->assertStatus(404);
    }

    public function test_preview_url_does_not_expose_pages(): void
    {
        Page::create([
            'post_title' => 'About',
            'post_content' => 'About content',
            'slug' => 'about',
            'post_status' => 'publish',
        ]);

        $url = URL::signedRoute('preview.post', ['slug' => 'about']);

        $this->get($url)->assertStatus(404);
    }

    public function test_preview_link_stays_valid(): void
    {
        $post = Post::create([
            'post_title' => 'Valid Link',
            'post_content' => 'Content',
            'slug' => 'valid-link',
            'post_status' => 'draft',
        ]);

        $url = URL::signedRoute('preview.post', ['slug' => $post->slug]);

        $this->assertStringNotContainsString('expires=', $url);
    }

    public function test_draft_is_not_publicly_accessible(): void
    {
        $post = Post::create([
            'post_title' => 'Hidden Draft',
            'post_content' => 'Content',
            'slug' => 'hidden-draft',
            'post_status' => 'draft',
        ]);

        $this->get(route('post', $post->slug))->assertStatus(404);
    }

    public function test_preview_url_for_soft_deleted_post_returns_404(): void
    {
        $post = Post::create([
            'post_title' => 'Deleted Draft',
            'post_content' => 'Content',
            'slug' => 'deleted-draft',
            'post_status' => 'draft',
        ]);

        $post->delete();

        $url = URL::signedRoute('preview.post', ['slug' => $post->slug]);

        $this->get($url)->assertStatus(404);
    }
}
