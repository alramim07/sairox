<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostFormatTest extends TestCase
{
    use RefreshDatabase;

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
}
