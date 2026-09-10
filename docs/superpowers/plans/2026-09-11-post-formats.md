# Post Formats Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add WordPress-style post formats (Standard, Aside, Gallery, Link, Image, Quote, Status, Video, Audio, Chat) to Sairox posts with admin editing/filtering and theme-driven template-part rendering.

**Architecture:** Store `post_format` on the posts table; expose it in the Filament form and posts table; add `ThemeManager::getPostContentView()` that resolves a `partials/content-{format}` view with a three-level fallback chain; slim `single.blade.php` to include the resolved part; ship 10 content parts and format classes in the default theme.

**Tech Stack:** Laravel 13, Filament 5, Blade, PHP 8.3, SQLite, PHPUnit.

---

## File Map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_09_11_000001_add_post_format_to_posts_table.php` | Add `post_format` column (default `standard`, indexed) |
| `app/Models/Post.php` | Fillable + `FORMATS` const + `chatLines()`/`galleryImages()` HTML helpers |
| `app/Sairox/ThemeManager.php` | `getPostContentView(string $format, ?string $themePath = null)` resolver |
| `app/Filament/Resources/Posts/Schemas/PostForm.php` | `post_format` Select field |
| `app/Filament/Resources/Posts/Tables/PostsTable.php` | `post_format` badge column + filter |
| `themes/sairox-default/views/single.blade.php` | Include resolved content part |
| `resources/views/front/single.blade.php` | Same include (core fallback path) |
| `themes/sairox-default/views/partials/content-standard.blade.php` | Extracted full article markup |
| `resources/views/front/partials/content-standard.blade.php` | Minimal core-fallback article |
| `themes/sairox-default/views/partials/content-{aside,gallery,link,image,quote,status,video,audio,chat}.blade.php` | Thin format layouts |
| `themes/sairox-default/views/partials/post-card.blade.php` | Add `format-{format}` class + badge |
| `resources/views/front/partials/post-card.blade.php` | Same (core fallback path) |
| `tests/Feature/PostFormatTest.php` | All feature tests |

---

### Task 1: Migration + Model (column, fillable, FORMATS const)

**Files:**
- Create: `database/migrations/2026_09_11_000001_add_post_format_to_posts_table.php`
- Modify: `app/Models/Post.php`
- Test: `tests/Feature/PostFormatTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PostFormatTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('posts', 'post_format'));
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
```

> Note: the last test relies on `HasRevisions` storing `$model->fresh()->toArray()`, which already includes every column. It passes once the column exists.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: FAIL — `SQLSTATE[HY000]: no such column: posts.post_format` and `Undefined constant ... FORMATS`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_09_11_000001_add_post_format_to_posts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('post_format', 20)->default('standard')->after('post_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['post_format']);
            $table->dropColumn('post_format');
        });
    }
};
```

- [ ] **Step 4: Update the Post model**

In `app/Models/Post.php`:

1. Add `'post_format'` to `$fillable` (after `'post_status'`).
2. Add the constant (after the `casts()` block):

```php
public const FORMATS = [
    'standard' => 'Standard',
    'aside' => 'Aside',
    'gallery' => 'Gallery',
    'link' => 'Link',
    'image' => 'Image',
    'quote' => 'Quote',
    'status' => 'Status',
    'video' => 'Video',
    'audio' => 'Audio',
    'chat' => 'Chat',
];
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: PASS (4 tests).

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_09_11_000001_add_post_format_to_posts_table.php app/Models/Post.php tests/Feature/PostFormatTest.php
git commit -m "DEV2-5.3 Post Formats: add post_format column, fillable, and FORMATS constant"
```

---

### Task 2: Admin — form Select, table badge, and filter

**Files:**
- Modify: `app/Filament/Resources/Posts/Schemas/PostForm.php`
- Modify: `app/Filament/Resources/Posts/Tables/PostsTable.php`
- Test: `tests/Feature/PostFormatTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PostFormatTest.php` (and add the `use` lines shown below to the top of the file):

```php
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: FAIL — the edit/list pages do not contain `post_format`; the create page does not persist a format.

- [ ] **Step 3: Add the form Select**

In `app/Filament/Resources/Posts/Schemas/PostForm.php`:

1. Add `use App\Models\Post;` to the imports.
2. Insert after the `post_status` Select (inside `Media & Settings`):

```php
Select::make('post_format')
    ->label('Post Format')
    ->options(Post::FORMATS)
    ->default('standard')
    ->helperText('Controls how the theme renders this post'),
```

- [ ] **Step 4: Add the table badge and filter**

In `app/Filament/Resources/Posts/Tables/PostsTable.php`:

1. Add `use App\Models\Post;` to the imports.
2. Insert after the `post_type` TextColumn:

```php
TextColumn::make('post_format')
    ->label('Format')
    ->badge()
    ->formatStateUsing(fn (string $state): string => Post::FORMATS[$state] ?? ucfirst($state)),
```

3. Insert after the `post_status` SelectFilter:

```php
SelectFilter::make('post_format')
    ->label('Format')
    ->options(Post::FORMATS),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Filament/Resources/Posts/Schemas/PostForm.php app/Filament/Resources/Posts/Tables/PostsTable.php tests/Feature/PostFormatTest.php
git commit -m "DEV2-5.3 Post Formats: add format select, badge column, and filter to admin"
```

---

### Task 3: ThemeManager resolver + content-standard parts + slim single view

**Files:**
- Modify: `app/Sairox/ThemeManager.php`
- Modify: `themes/sairox-default/views/single.blade.php`
- Modify: `resources/views/front/single.blade.php`
- Create: `themes/sairox-default/views/partials/content-standard.blade.php`
- Create: `resources/views/front/partials/content-standard.blade.php`
- Test: `tests/Feature/PostFormatTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PostFormatTest.php`:

```php
public function test_theme_manager_resolves_standard_and_falls_back(): void
{
    $manager = app(\App\Sairox\ThemeManager::class);

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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: FAIL — `Call to undefined method App\Sairox\ThemeManager::getPostContentView()`.

- [ ] **Step 3: Add the resolver to ThemeManager**

In `app/Sairox/ThemeManager.php`, add after `getThemeView()`:

```php
public function getPostContentView(string $format, ?string $themePath = null): string
{
    $themePath ??= $this->getActiveTheme()['path'] ?? base_path('themes/sairox-default');

    if ($format !== '' && $format !== 'standard' && File::exists("{$themePath}/views/partials/content-{$format}.blade.php")) {
        return "theme::partials.content-{$format}";
    }

    if (File::exists("{$themePath}/views/partials/content-standard.blade.php")) {
        return 'theme::partials.content-standard';
    }

    return 'sairox-core::partials.content-standard';
}
```

- [ ] **Step 4: Create the content-standard parts**

Create `themes/sairox-default/views/partials/content-standard.blade.php` (extracted from the current `single.blade.php` article):

```blade
<article class="format-{{ $post->post_format }}">
    @if($post->thumbnail)
    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->post_title }}" class="w-full h-64 md:h-96 object-cover rounded-lg shadow-sm mb-8" loading="lazy">
    @endif

    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ $post->post_title }}</h1>

    <div class="flex items-center text-sm text-gray-500 mb-8">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
        @if($post->categories->isNotEmpty())
        <span class="mx-2">·</span>
        <span>
            @foreach($post->categories as $category)
            <a href="{{ url('/categories/' . $category->slug) }}" class="text-amber-600 hover:text-amber-700">{{ $category->name }}</a>@if(!$loop->last), @endif
            @endforeach
        </span>
        @endif
    </div>

    <div class="prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </div>

    @if($post->tags->isNotEmpty())
    <div class="flex flex-wrap gap-2 mt-8 pt-6 border-t">
        @foreach($post->tags as $tag)
        <a href="{{ url('/tags/' . $tag->slug) }}" class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full hover:bg-amber-50 hover:text-amber-600 transition-colors">
            #{{ $tag->name }}
        </a>
        @endforeach
    </div>
    @endif
</article>
```

Create `resources/views/front/partials/content-standard.blade.php` (minimal core fallback):

```blade
<article class="format-{{ $post->post_format }}">
    <h1 class="text-3xl font-bold mb-6">{{ $post->post_title }}</h1>
    <div class="text-gray-500 text-sm mb-6">{{ $post->created_at->format('F j, Y') }}</div>
    <div class="prose max-w-none">{!! $post->post_content !!}</div>
</article>
```

- [ ] **Step 5: Slim both single views**

Replace the whole `themes/sairox-default/views/single.blade.php` with:

```blade
@extends('theme::layouts.app')

@section('title', $post->post_title . ' - ' . get_option('site_title', 'Sairox CMS'))

@section('meta_description', Str::limit(strip_tags($post->post_excerpt ?? $post->post_content), 160))

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @php
        $formatView = app(\App\Sairox\ThemeManager::class)->getPostContentView($post->post_format);
    @endphp

    @include($formatView, ['post' => $post])

    @include('theme::partials.comments')
</div>
@endsection
```

Replace the whole `resources/views/front/single.blade.php` with:

```blade
@extends('theme::layouts.app')

@section('title', $post->post_title)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @php
        $formatView = app(\App\Sairox\ThemeManager::class)->getPostContentView($post->post_format);
    @endphp

    @include($formatView, ['post' => $post])
</div>
@endsection
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Sairox/ThemeManager.php themes/sairox-default/views/single.blade.php resources/views/front/single.blade.php themes/sairox-default/views/partials/content-standard.blade.php resources/views/front/partials/content-standard.blade.php tests/Feature/PostFormatTest.php
git commit -m "DEV2-5.3 Post Formats: resolve content parts with fallback chain and slim single views"
```

---

### Task 4: Format template parts + HTML helpers

**Files:**
- Create: `themes/sairox-default/views/partials/content-aside.blade.php`
- Create: `themes/sairox-default/views/partials/content-gallery.blade.php`
- Create: `themes/sairox-default/views/partials/content-link.blade.php`
- Create: `themes/sairox-default/views/partials/content-image.blade.php`
- Create: `themes/sairox-default/views/partials/content-quote.blade.php`
- Create: `themes/sairox-default/views/partials/content-status.blade.php`
- Create: `themes/sairox-default/views/partials/content-video.blade.php`
- Create: `themes/sairox-default/views/partials/content-audio.blade.php`
- Create: `themes/sairox-default/views/partials/content-chat.blade.php`
- Modify: `app/Models/Post.php`
- Test: `tests/Feature/PostFormatTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PostFormatTest.php`:

```php
public function test_theme_manager_resolves_each_format_part(): void
{
    $manager = app(\App\Sairox\ThemeManager::class);

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
        'aside' => ['class' => 'format-aside', 'needle' => 'Aside body'],
        'gallery' => ['class' => 'format-gallery', 'needle' => 'Gallery body'],
        'link' => ['class' => 'format-link', 'needle' => 'https://example.com'],
        'image' => ['class' => 'format-image', 'needle' => 'Image body'],
        'quote' => ['class' => 'format-quote', 'needle' => 'To be or not to be'],
        'status' => ['class' => 'format-status', 'needle' => 'Status body'],
        'video' => ['class' => 'format-video', 'needle' => 'Video body'],
        'audio' => ['class' => 'format-audio', 'needle' => 'Audio body'],
        'chat' => ['class' => 'format-chat', 'needle' => 'First line'],
    ];

    foreach ($cases as $format => $expect) {
        Post::create([
            'post_title' => 'Formatted',
            'post_content' => "<p>{$expect['needle']}</p>",
            'slug' => "fmt-{$format}",
            'post_status' => 'publish',
            'post_format' => $format,
        ]);

        $this->get("/posts/fmt-{$format}")
            ->assertOk()
            ->assertSee($expect['class'], false)
            ->assertSee($expect['needle'], false);
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
        ->assertSee('format-gallery', false);
}

public function test_preview_of_a_formatted_draft_uses_its_part(): void
{
    \Filament\Facades\Filament::setCurrentPanel('admin');
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

    $url = \Illuminate\Support\Facades\URL::signedRoute('preview.post', ['slug' => $post->slug]);

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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: FAIL — `Partial content-{format} view not found` (resolver returns `content-standard` because the parts don't exist yet) and missing model methods.

- [ ] **Step 3: Add the HTML helper methods to Post**

In `app/Models/Post.php`, after `getMeta()`:

```php
public function chatLines(): array
{
    preg_match_all('/<p[^>]*>(.*?)<\/p>/s', $this->post_content, $matches);

    return $matches[1] ?? [];
}

public function galleryImages(): array
{
    preg_match_all('/<img[^>]*src=["\']([^"\']+)["\']/i', $this->post_content, $matches);

    return $matches[1] ?? [];
}
```

- [ ] **Step 4: Create the format parts**

Create `themes/sairox-default/views/partials/content-aside.blade.php`:

```blade
<article class="format-aside">
    <div class="text-lg italic text-gray-700 leading-relaxed prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </div>
    <div class="flex items-center text-sm text-gray-500 mt-4">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
    </div>
</article>
```

Create `themes/sairox-default/views/partials/content-quote.blade.php`:

```blade
<article class="format-quote">
    <blockquote class="border-l-4 border-amber-500 pl-6 my-8 prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </blockquote>
    <p class="text-sm text-gray-500">— {{ $post->post_title }}@if($post->author?->name) ({{ $post->author?->name }}) @endif</p>
</article>
```

Create `themes/sairox-default/views/partials/content-link.blade.php`:

```blade
<article class="format-link">
    <h1 class="text-2xl md:text-3xl font-bold mb-4">
        <a href="{{ $post->post_content }}" class="text-amber-600 hover:text-amber-700">{{ $post->post_title }}</a>
    </h1>
    <div class="flex items-center text-sm text-gray-500">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
    </div>
</article>
```

Create `themes/sairox-default/views/partials/content-status.blade.php`:

```blade
<article class="format-status">
    <p class="text-lg text-gray-800">{{ strip_tags($post->post_content) }}</p>
    <span class="text-sm text-gray-500">{{ $post->created_at->format('F j, Y g:i A') }}</span>
</article>
```

Create `themes/sairox-default/views/partials/content-chat.blade.php`:

```blade
<article class="format-chat space-y-3">
    @foreach($post->chatLines() as $line)
    <div class="flex @if($loop->even) justify-end @endif">
        <div class="bg-gray-100 rounded-lg px-4 py-2 max-w-md @if($loop->even) bg-amber-50 @endif">
            <p class="text-sm">{!! $line !!}</p>
        </div>
    </div>
    @endforeach
</article>
```

Create `themes/sairox-default/views/partials/content-gallery.blade.php`:

```blade
<article class="format-gallery">
    @php $images = $post->galleryImages(); @endphp
    @if($images)
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        @foreach($images as $src)
        <img src="{{ $src }}" alt="{{ $post->post_title }}" class="w-full h-48 object-cover rounded-lg" loading="lazy">
        @endforeach
    </div>
    @endif
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>
```

Create `themes/sairox-default/views/partials/content-image.blade.php`:

```blade
<article class="format-image">
    @if($post->thumbnail)
    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->post_title }}" class="w-full h-64 md:h-96 object-cover rounded-lg shadow-sm mb-6" loading="lazy">
    @endif
    <h1 class="text-2xl md:text-3xl font-bold mb-4">{{ $post->post_title }}</h1>
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>
```

Create `themes/sairox-default/views/partials/content-video.blade.php`:

```blade
<article class="format-video">
    <h1 class="text-2xl md:text-3xl font-bold mb-4">{{ $post->post_title }}</h1>
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>
```

Create `themes/sairox-default/views/partials/content-audio.blade.php`:

```blade
<article class="format-audio">
    <h1 class="text-2xl md:text-3xl font-bold mb-4">{{ $post->post_title }}</h1>
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=PostFormatTest --compact`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add app/Models/Post.php themes/sairox-default/views/partials/content-aside.blade.php themes/sairox-default/views/partials/content-gallery.blade.php themes/sairox-default/views/partials/content-link.blade.php themes/sairox-default/views/partials/content-image.blade.php themes/sairox-default/views/partials/content-quote.blade.php themes/sairox-default/views/partials/content-status.blade.php themes/sairox-default/views/partials/content-video.blade.php themes/sairox-default/views/partials/content-audio.blade.php themes/sairox-default/views/partials/content-chat.blade.php tests/Feature/PostFormatTest.php
git commit -m "DEV2-5.3 Post Formats: add format template parts and HTML helpers"
```

---

### Task 5: Archive post-card format class + badge

**Files:**
- Modify: `themes/sairox-default/views/partials/post-card.blade.php`
- Modify: `resources/views/front/partials/post-card.blade.php`
- Test: `tests/Feature/PostFormatTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/PostFormatTest.php`:

```php
public function test_homepage_post_card_shows_format_class_and_badge(): void
{
    Post::create([
        'post_title' => 'Quote Card',
        'post_content' => '<p>Card body</p>',
        'slug' => 'quote-card',
        'post_status' => 'publish',
        'post_format' => 'quote',
    ]);

    Post::create([
        'post_title' => 'Plain Card',
        'post_content' => '<p>Plain body</p>',
        'slug' => 'plain-card',
        'post_status' => 'publish',
        'post_format' => 'standard',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('format-quote', false)
        ->assertSee('>Quote</span>', false)  // badge label on the quote card
        ->assertSee('format-standard', false)
        ->assertDontSee('>Standard</span>', false); // no badge for standard posts
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_homepage_post_card_shows_format_class_and_badge --compact`
Expected: FAIL — post-cards do not carry format classes or badges.

- [ ] **Step 3: Update the theme post-card**

Replace the whole `themes/sairox-default/views/partials/post-card.blade.php` with:

```blade
<article class="bg-white rounded-lg shadow-sm overflow-hidden format-{{ $post->post_format }}">
    @if($post->thumbnail)
    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->post_title }}" class="w-full h-48 object-cover" loading="lazy">
    @endif
    <div class="p-6">
        <h2 class="text-xl font-semibold mb-2">
            <a href="{{ url('/posts/' . $post->slug) }}" class="hover:text-amber-600 transition-colors">
                {{ $post->post_title }}
            </a>
        </h2>
        @if($post->post_format !== 'standard')
        <span class="inline-block text-xs uppercase tracking-wide bg-amber-100 text-amber-700 px-2 py-1 rounded mb-2">{{ \App\Models\Post::FORMATS[$post->post_format] ?? $post->post_format }}</span>
        @endif
        <p class="text-gray-600 mb-4">{{ Str::limit(strip_tags($post->post_content), 200) }}</p>
        <div class="flex items-center text-sm text-gray-500">
            <span>{{ $post->author?->name }}</span>
            <span class="mx-2">·</span>
            <span>{{ $post->created_at->format('M j, Y') }}</span>
        </div>
    </div>
</article>
```

- [ ] **Step 4: Update the core post-card**

Replace the whole `resources/views/front/partials/post-card.blade.php` with:

```blade
<article class="bg-white rounded-lg shadow-sm p-6 format-{{ $post->post_format }}">
    <h2 class="text-xl font-semibold mb-2">
        <a href="{{ url('/posts/' . $post->slug) }}" class="hover:text-amber-600 transition-colors">{{ $post->post_title }}</a>
    </h2>
    @if($post->post_format !== 'standard')
    <span class="inline-block text-xs uppercase tracking-wide bg-amber-100 text-amber-700 px-2 py-1 rounded mb-2">{{ \App\Models\Post::FORMATS[$post->post_format] ?? $post->post_format }}</span>
    @endif
    <p class="text-gray-600">{{ Str::limit(strip_tags($post->post_content), 200) }}</p>
</article>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_homepage_post_card_shows_format_class_and_badge --compact`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty
git add themes/sairox-default/views/partials/post-card.blade.php resources/views/front/partials/post-card.blade.php tests/Feature/PostFormatTest.php
git commit -m "DEV2-5.3 Post Formats: add format class and badge to archive post cards"
```

---

### Task 6: Full suite + README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS (all existing 87 tests plus the new PostFormatTest cases, ~18 new tests).

- [ ] **Step 2: Run pint over the whole project**

Run: `vendor/bin/pint`
Expected: all files formatted.

- [ ] **Step 3: Update the README Phase 3 table**

Insert a row after the Draft Management row in `README.md` (line ~78):

```markdown
| Post Formats (WordPress-style format flag, admin editor, theme template parts) | ✅ |
```

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "DEV2-5.3 Post Formats: complete feature, update README"
```

- [ ] **Step 5: Push**

```bash
git push origin main
```

---

## Self-Review Notes

- **Spec coverage:** Storage (Task 1), admin form/table (Task 2), resolver + single + standard parts (Task 3), nine format parts + helpers (Task 4), archive class + badge (Task 5), revision/autosave integration tests (in Tasks 1-2), preview integration (Task 4). All spec sections map to a task.
- **Out of scope (per spec):** `theme.json` format declaration/gating, format-specific archive loop parts, rich-media capture — none implemented.
- **Type consistency:** `getPostContentView(string $format, ?string $themePath = null): string`, `Post::FORMATS` array, `chatLines(): array`, `galleryImages(): array` used consistently across tasks.

## Execution Notes (verified during implementation)

- **Task 4 deviation (justified):** the 9 format parts were created BOTH in `themes/sairox-default/views/partials/` and byte-identical copies in `resources/views/front/partials/`. Reason: in the test env, `ThemeServiceProvider::boot()` runs before migrations, `get_option('active_theme')` throws, and `theme::` resolves solely to `resources/views/front`; without the front copies the HTTP render tests failed with `View [partials.content-gallery] not found`. Removing them reproducibly fails 5 tests. Mirrors the existing `content-standard` precedent (both locations). In production the theme path registers first, so theme copies win.
- **Task 5 deviation/amendment (justified):** the core-fallback views `resources/views/front/index.blade.php` and `archive.blade.php` inlined the card `<article>` instead of including `theme::partials.post-card`, so the planned scope (badge on the two partials) could never satisfy the planned homepage test. Both core views now `@include('theme::partials.post-card')` (distinct empty-state text kept). Card partial is byte-identical across both locations with thumbnail retained (the plan's slimmed core variant was rejected as a regression).
- **Task 4 review-fix commit:** content-link `href` sanitized with `trim(strip_tags(...))`; format render tests strengthened with format-distinctive markers (`italic`, `grid grid-cols-2`, `href="https://example.com"`, `<blockquote`, `bg-amber-50`, and `assertDontSee('<h1')` for status) so a silent fallback to `content-standard` cannot pass.