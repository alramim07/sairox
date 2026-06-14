# Draft Management Design

## Overview

Add preview URLs and auto-save for draft posts in Sairox CMS. Preview URLs allow viewing unpublished posts via permanent signed URLs. Auto-save periodically saves draft changes via Livewire debounced methods.

## Preview URLs

**Route:** `GET /preview/posts/{slug}` — signed, no expiry, bypasses publish check.

- Uses Laravel `URL::signedRoute()` without `expires` parameter (permanent link).
- `FrontendController::preview($slug)` loads the post by slug regardless of `post_status`, verifies the signed request via `$request->hasValidSignature()`.
- Renders through the same theme `single` view as published posts.
- If the signature is invalid, returns a 403 error.
- The existing `FrontendController::post()` route remains unchanged (still scoped to `post_status = 'publish'`).

**Filament button:** On `EditPost` page, add a "Preview" action/button that:
- Generates the signed URL for the current post
- Opens in a new tab
- Disabled when `post_status` is not `draft` or `scheduled`
- For published posts, shows "View" linking to the normal frontend URL instead

**Security:** Only shows draft content if the signature is valid. The signed URL is permanent (no `expires` parameter). Changing `APP_KEY` invalidates all existing preview links.

## Auto-save

**Mechanism:** Livewire debounced method on `EditPost` and `CreatePost` pages.

- Add `autoSave()` method to both `EditPost` and `CreatePost` Filament pages.
- The method:
  - Only saves if `post_status` is `draft` (not for published posts).
  - Validates required fields (`post_title`, `slug`).
  - Calls `$this->form->getState()` and saves the model.
  - Dispatches a flash notification "Draft auto-saved".
  - Sets `$this->lastAutoSaved = now()` for the indicator.
- **Indicator:** Show "Auto-saved at HH:MM:SS" text below the form.
- **On create page:** Creates the post with `draft` status on first auto-save, then subsequent auto-saves update it.
- Debounce handled by Livewire's built-in `wire:model.live.debounce.5000ms` on form fields.
- Skip auto-save if post was just created (no changes yet). Handle validation errors silently (no notification).

## Files Changed

| File | Change |
|------|--------|
| `routes/web.php` | Add `Route::get('/preview/posts/{slug}', ...)->name('preview.post')` with `signed` middleware |
| `app/Http/Controllers/FrontendController.php` | Add `preview(string $slug)` method |
| `app/Filament/Resources/Posts/Pages/EditPost.php` | Add `autoSave()` method, preview button action, auto-save indicator |
| `app/Filament/Resources/Posts/Pages/CreatePost.php` | Add `autoSave()` method, auto-save indicator |

## Testing

### Preview URLs
- `test_preview_url_shows_draft_post()` — access signed URL for draft post, assert status 200 and content
- `test_preview_url_without_signature_returns_403()` — access preview route without valid signature
- `test_preview_url_shows_published_post()` — preview URL works for published posts too
- `test_preview_url_generation_in_filament()` — verify the Preview button generates a valid URL

### Auto-save
- `test_auto_save_updates_draft_post()` — call auto-save on a draft, verify fields updated
- `test_auto_save_does_not_change_published_post()` — call auto-save on published post, verify no change
- `test_auto_save_sets_draft_status_on_new_post()` — auto-save creates the post as draft

## Edge Cases

- **Already published post:** Preview button shows "View Published" linking to normal frontend URL, not preview.
- **Deleted post:** Preview route returns 404 (signed URL for soft-deleted post is invalid).
- **Scheduled post:** Preview URL works (same as draft — not yet public).
- **Auto-save with validation errors:** Silently fails (doesn't overwrite valid data with partial data).
- **Rapid auto-saves:** Livewire debounce coalesces multiple saves within 5s window.
