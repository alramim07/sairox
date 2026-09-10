# Post Formats Design

## Overview

Add WordPress-style post formats to Sairox CMS: a per-post `post_format` flag (Standard, Aside, Gallery, Link, Image, Quote, Status, Video, Audio, Chat) that themes use to render each post differently. The admin stores/edits/filters the format; the frontend theme resolves a format-specific content template part with a safe fallback chain.

## Storage

- New migration `add_post_format_to_posts` (timestamp created via `php artisan make:migration`): `post_format` string, NOT NULL, default `'standard'`, with an index.
- Existing posts are unaffected (defaults to `standard`).
- Add `post_format` to `Post::$fillable`.
- No casts needed; the DB default guarantees a non-null value.

## Admin

- `app/Filament/Resources/Posts/Schemas/PostForm.php`: add a `Select` field `post_format` with the 10 WP formats (labels: Standard, Aside, Gallery, Link, Image, Quote, Status, Video, Audio, Chat), `->default('standard')`, placed beside Status.
- `app/Filament/Resources/Posts/Tables/PostsTable.php`: add a `post_format` badge column and a SelectFilter for formats.
- Existing behavior is unchanged: auto-save drafts and revision snapshots include `post_format` automatically because they operate on the resolved form state.

## Frontend Rendering

- **View resolution:** `ThemeManager::getPostContentView(string $format): string` resolves, in order:
  1. Active theme's `partials/content-{format}` (e.g. `theme::partials.content-quote`)
  2. Active theme's `partials/content-standard`
  3. Core fallback `sairox-core::partials.content-standard`
  - Unknown/unsupported formats always fall back gracefully (no `ViewNotFound`).
- **`single.blade.php`:** computes the view via `app(ThemeManager::class)->getPostContentView($post->post_format)`, then `@include($contentView, compact('post'))`. The existing article markup moves to `partials/content-standard.blade.php` (title, meta, thumbnail, content, tags).
- **Format parts:** nine thin views (`content-aside`, `content-gallery`, `content-link`, `content-image`, `content-quote`, `content-status`, `content-video`, `content-audio`, `content-chat`) exist in the starter theme. Each uses a shared simple article wrapper and varies the layout, e.g.:
  - `quote` — styled blockquote presentation
  - `link` — title rendered as a hyperlink
  - `gallery` — image grid from content `<img>` tags when present
  - `status` — minimal single-line layout
  - `chat` — content `<p>` paragraphs rendered as alternating chat bubbles
  - `aside` — italic, minimal header
  - others — thematic variations on the shared wrapper
- **Format class:** every article in `single` and every post-card in archive views gets a `format-{format}` class for CSS styling.
- **Archives:** keep the existing `post-card` for list/archive/loop rendering (add the `format-{format}` class + a format badge whose label comes from the design's 10-format map; hidden when `standard`). No per-format loop parts (scope control).

## Preview

- The signed `preview.post` route already renders the `single` view, so previews inherit format parts with no extra code.

## Validation & Error Handling

- `post_format` is validated `in:standard,aside,gallery,link,image,quote,status,video,audio,chat`.
- The resolution fallback chain prevents `ViewNotFound` for any value, including unknown legacy data.
- Views read `$post->post_format` which is never null due to the DB default.

## Out of Scope (later)

- `theme.json` `post_formats` declaration / theme-support gating of the admin field.
- Format-specific template parts in archive loops.
- Rich-media-specific capture (video/audio/gallery uploader interactions).

## Files Changed

| File | Change |
|------|--------|
| `database/migrations/XXXX_create_post_format_on_posts.php` | New migration (add column + index) |
| `app/Models/Post.php` | Add `post_format` to `$fillable` |
| `app/Sairox/ThemeManager.php` | Add `getPostContentView(string $format): string` |
| `app/Filament/Resources/Posts/Schemas/PostForm.php` | Add `post_format` Select |
| `app/Filament/Resources/Posts/Tables/PostsTable.php` | Add `post_format` badge + filter |
| `themes/sairox-default/views/single.blade.php` | Include resolved content part, add format class |
| `themes/sairox-default/views/partials/content-standard.blade.php` | New (extracted article markup) |
| `themes/sairox-default/views/partials/content-{aside,gallery,link,image,quote,status,video,audio,chat}.blade.php` | New, thin format layouts |
| `themes/sairox-default/views/partials/post-card.blade.php` | Add `format-{format}` class + optional badge |
| `tests/Feature/PostFormatTest.php` | New test file |

## Testing

### Storage & Model
- Schema: `post_format` column exists, defaults to `standard`.
- Model: creating/updating a post persists `post_format`.

### Admin
- Form: `post_format` field renders with 10 options and defaults to `standard`.
- Table: badge column and filter appear; filtering by `post_format` returns matching posts.
- Create/update through the Filament page persists the selected format.

### Frontend
- For each format, the single page renders the corresponding format part.
- Unknown format falls back to `content-standard`.
- The article carries the `format-{format}` class.
- Archive post-card carries the `format-{format}` class.

### Preview
- The signed preview of a formatted post renders its format part.

### Integration
- Revision snapshot for a format change contains `post_format`.
- Auto-save create draft persists the format default.