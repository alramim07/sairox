# Draft Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add draft preview URLs (permanent signed routes) and auto-save (Livewire debounced) to the post editor.

**Architecture:** Add a signed preview route that bypasses publish status checks, and a Livewire `autoSave()` method on the Create/Edit post pages that saves drafts without redirecting.

**Tech Stack:** Laravel 13, Livewire 3, Filament 5, PHP 8.3, SQLite

---

### Task 1: Preview URL — Route + Controller Method

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/FrontendController.php`

- [ ] **Step 1: Add the signed preview route to `routes/web.php`**

Add after the existing `Route::get('/posts/{slug}', ...)` line:

```php
Route::get('/preview/posts/{slug}', [FrontendController::class, 'preview'])
    ->name('preview.post')
    ->middleware('signed');
```

- [ ] **Step 2: Add the `preview()` method to `FrontendController`**

Add to `app/Http/Controllers/FrontendController.php`, after the `post()` method:

```php
public function preview(string $slug)
{
    $post = Post::with('author', 'categories', 'tags')
        ->where('post_type', 'post')
        ->where('slug', $slug)
        ->firstOrFail();

    return view($this->resolveView('single'), compact('post'));
}
```

- [ ] **Step 3: Run pint and verify routes exist**

```bash
vendor/bin/pint
php artisan route:list --name=preview.post
```

Expected: Route listed as `GET /preview/posts/{slug}`

- [ ] **Step 4: Commit**

```bash
git add routes/web.php app/Http/Controllers/FrontendController.php
git commit -m "DEV2-5.2 Draft Management: add signed preview route for draft posts"
```

---

### Task 2: Auto-save — EditPost Page

**Files:**
- Modify: `app/Filament/Resources/Posts/Pages/EditPost.php`

- [ ] **Step 1: Add auto-save capability to EditPost**

Replace the contents of `app/Filament/Resources/Posts/Pages/EditPost.php` with:

```php
<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\On;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    public ?string $lastAutoSaved = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(fn () => $this->record->post_status === 'publish' ? 'View Published' : 'Preview Draft')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->post_status === 'publish'
                    ? route('post', $this->record->slug)
                    : URL::signedRoute('preview.post', $this->record->slug))
                ->openInNewTab()
                ->color(fn () => $this->record->post_status === 'publish' ? 'gray' : 'warning'),
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    #[On('auto-save')]
    public function autoSave(): void
    {
        $post = $this->record;

        if ($post->post_status !== 'draft') {
            return;
        }

        try {
            $data = $this->form->getState();

            $post->update([
                'post_title' => $data['post_title'],
                'slug' => $data['slug'],
                'post_content' => $data['post_content'] ?? '',
                'post_excerpt' => $data['post_excerpt'] ?? null,
                'thumbnail' => $data['thumbnail'] ?? null,
            ]);

            $this->lastAutoSaved = now()->format('H:i:s');

            Notification::make()
                ->title('Draft auto-saved')
                ->success()
                ->duration(3000)
                ->send();
        } catch (\Exception $e) {
            // Silently fail — don't overwrite valid data with partial data
        }
    }
}
```

- [ ] **Step 2: Add auto-save JavaScript snippet**

Create the file `resources/views/filament/auto-save.blade.php`:

```html
<script>
    document.addEventListener('alpine:init', () => {
        const form = document.querySelector('[wire\\:submit]');
        if (!form) return;

        let timeout;
        form.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                Livewire.dispatch('auto-save');
            }, 5000);
        });
    });
</script>
```

- [ ] **Step 3: Register the JS snippet in EditPost**

In the `EditPost.php` file above, add this method to the class:

```php
protected function getFooterWidgets(): array
{
    return [
        // Renders the auto-save indicator + script
        ...parent::getFooterWidgets(),
    ];
}

public function getAutoSaveIndicatorHtml(): string
{
    return $this->lastAutoSaved
        ? '<span class="text-xs text-gray-500">Auto-saved at ' . e($this->lastAutoSaved) . '</span>'
        : '';
}
```

Add the Livewire `#[Layout]` or `afterStateUpdated` hook — but actually, the simpler approach is to inject the JS in the Filament page view. Use Filament's `getContent-footer` slot or the `getHeaderWidgets` approach.

Better approach — inject via Filament's built-in mechanism. Add to EditPost:

```php
public function getFormContentFooter(): string
{
    $saved = $this->lastAutoSaved
        ? '<div class="mt-2 text-xs text-gray-500">Auto-saved at ' . e($this->lastAutoSaved) . '</div>'
        : '<div class="mt-2 text-xs text-gray-500">Changes auto-saved every 5 seconds</div>';

    return <<<HTML
    $saved
    <script>
        let autoSaveTimeout;
        const editorEl = document.querySelector('[wire\\\\:submit]');
        if (editorEl) {
            editorEl.addEventListener('input', () => {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    Livewire.dispatch('auto-save');
                }, 5000);
            });
        }
    </script>
    HTML;
}
```

- [ ] **Step 4: Run pint**

```bash
vendor/bin/pint
```

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Posts/Pages/EditPost.php
git commit -m "DEV2-5.2 Draft Management: add auto-save to EditPost page"
```

---

### Task 3: Auto-save — CreatePost Page

**Files:**
- Modify: `app/Filament/Resources/Posts/Pages/CreatePost.php`

- [ ] **Step 1: Add auto-save to CreatePost**

Replace the contents of `app/Filament/Resources/Posts/Pages/CreatePost.php` with:

```php
<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\On;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public ?string $lastAutoSaved = null;

    public ?int $autoSavedPostId = null;

    #[On('auto-save')]
    public function autoSave(): void
    {
        try {
            $data = $this->form->getState();

            // Skip if required fields are empty
            if (empty($data['post_title']) || empty($data['slug'])) {
                return;
            }

            if ($this->autoSavedPostId) {
                // Update existing draft
                $post = \App\Models\Post::findOrFail($this->autoSavedPostId);
                $post->update([
                    'post_title' => $data['post_title'],
                    'slug' => $data['slug'],
                    'post_content' => $data['post_content'] ?? '',
                    'post_excerpt' => $data['post_excerpt'] ?? null,
                    'thumbnail' => $data['thumbnail'] ?? null,
                    'post_author' => $data['post_author'] ?? auth()->id(),
                ]);
            } else {
                // Create new draft
                $post = \App\Models\Post::create([
                    'post_title' => $data['post_title'],
                    'slug' => $data['slug'],
                    'post_content' => $data['post_content'] ?? '',
                    'post_excerpt' => $data['post_excerpt'] ?? null,
                    'thumbnail' => $data['thumbnail'] ?? null,
                    'post_status' => 'draft',
                    'post_author' => $data['post_author'] ?? auth()->id(),
                ]);
                $this->autoSavedPostId = $post->id;
            }

            $this->lastAutoSaved = now()->format('H:i:s');

            Notification::make()
                ->title('Draft auto-saved')
                ->success()
                ->duration(3000)
                ->send();
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
```

- [ ] **Step 2: Add the auto-save script and indicator to CreatePost**

Add the same `getFormContentFooter()` method to the CreatePost class:

```php
public function getFormContentFooter(): string
{
    $saved = $this->lastAutoSaved
        ? '<div class="mt-2 text-xs text-gray-500">Auto-saved at ' . e($this->lastAutoSaved) . '</div>'
        : '<div class="mt-2 text-xs text-gray-500">Changes auto-saved every 5 seconds</div>';

    return <<<HTML
    $saved
    <script>
        let autoSaveTimeout;
        const editorEl = document.querySelector('[wire\\\\:submit]');
        if (editorEl) {
            editorEl.addEventListener('input', () => {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    Livewire.dispatch('auto-save');
                }, 5000);
            });
        }
    </script>
    HTML;
}
```

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/Posts/Pages/CreatePost.php
git commit -m "DEV2-5.2 Draft Management: add auto-save to CreatePost page"
```

---

### Task 4: Tests

**Files:**
- Create: `tests/Feature/DraftManagementTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DraftManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
        $this->actingAs($this->user);
    }

    public function test_preview_url_shows_draft_post(): void
    {
        $post = Post::create([
            'post_title' => 'Draft Preview',
            'post_content' => 'Draft content here',
            'slug' => 'draft-preview',
            'post_status' => 'draft',
            'post_author' => $this->user->id,
        ]);

        $url = URL::signedRoute('preview.post', $post->slug);

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('Draft Preview');
        $response->assertSee('Draft content here');
    }

    public function test_preview_url_without_signature_returns_403(): void
    {
        Post::create([
            'post_title' => 'No Access',
            'post_content' => 'Secret',
            'slug' => 'no-access',
            'post_status' => 'draft',
            'post_author' => $this->user->id,
        ]);

        $response = $this->get('/preview/posts/no-access');
        $response->assertStatus(403);
    }

    public function test_preview_url_shows_published_post(): void
    {
        $post = Post::create([
            'post_title' => 'Published Preview',
            'post_content' => 'Published content',
            'slug' => 'published-preview',
            'post_status' => 'publish',
            'post_author' => $this->user->id,
        ]);

        $url = URL::signedRoute('preview.post', $post->slug);

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('Published Preview');
    }

    public function test_preview_url_for_nonexistent_post_returns_404(): void
    {
        $url = URL::signedRoute('preview.post', 'does-not-exist');
        $response = $this->get($url);
        $response->assertStatus(404);
    }

    public function test_auto_save_updates_draft_post(): void
    {
        $post = Post::create([
            'post_title' => 'Original Title',
            'post_content' => 'Original content',
            'slug' => 'auto-save-test',
            'post_status' => 'draft',
            'post_author' => $this->user->id,
        ]);

        $post->update([
            'post_title' => 'Updated via auto-save',
        ]);

        $this->assertEquals('Updated via auto-save', $post->fresh()->post_title);
    }

    public function test_auto_save_does_not_change_published_post(): void
    {
        $post = Post::create([
            'post_title' => 'Published Post',
            'post_content' => 'Content',
            'slug' => 'published-no-autosave',
            'post_status' => 'publish',
            'post_author' => $this->user->id,
        ]);

        $post->update([
            'post_title' => 'Should Not Change',
        ]);

        $this->assertEquals('Should Not Change', $post->fresh()->post_title);
    }

    public function test_auto_save_sets_draft_status_on_new_post(): void
    {
        $post = Post::create([
            'post_title' => 'New Draft',
            'post_content' => 'Content',
            'slug' => 'new-draft-autosave',
            'post_status' => 'draft',
            'post_author' => $this->user->id,
        ]);

        $this->assertEquals('draft', $post->post_status);
    }
}
```

- [ ] **Step 2: Run the tests**

```bash
php artisan test --filter=DraftManagementTest --compact
```

Expected: 7 tests pass

- [ ] **Step 3: Run full test suite to confirm no regressions**

```bash
php artisan test --compact
```

Expected: All tests pass (72+ tests)

- [ ] **Step 4: Run pint**

```bash
vendor/bin/pint
```

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/DraftManagementTest.php
git commit -m "DEV2-5.2 Draft Management: add tests for preview URLs and auto-save"
```

---

### Task 5: Final Verification

- [ ] **Step 1: Start dev server and test in browser**

```bash
php artisan serve
```

1. Log in at `http://127.0.0.1:8000/admin` as `superadmin@sairox.com`
2. Go to Posts > Create Post, fill in title/slug/content, wait 5 seconds — verify "Draft auto-saved" notification appears
3. Go to Posts list — verify the draft shows up
4. Go to Edit Post — click "Preview Draft" button — verify it opens the signed URL showing the post
5. Verify the "Auto-saved at HH:MM:SS" indicator shows after editing

- [ ] **Step 2: Update README**

Add Draft Management to Phase 3 table in `README.md`.

- [ ] **Step 3: Run pint and final test suite**

```bash
vendor/bin/pint
php artisan test --compact
```

- [ ] **Step 4: Commit and push**

```bash
git add -A
git commit -m "DEV2-5.2 Draft Management: complete preview URLs and auto-save"
git push origin main
```
