# Sairox CMS

Sairox is a modern, lightweight CMS built with Laravel and Filament as a WordPress alternative. It provides a clean admin interface and extensible architecture for building content-driven websites.

## Tech Stack

| Category    | Technology    |
| ----------- | ------------- |
| Backend     | Laravel 13    |
| Admin Panel | Filament 5    |
| PHP Version | 8.3           |
| Frontend    | TailwindCSS 4 |
| Build Tool  | Vite          |
| Database    | MySQL / SQLite |
| Testing     | PHPUnit       |

## Dependencies

### Composer Packages

- `filament/filament` (~5.0) — Admin panel builder
- `laravel/framework` (^13.0) — Core framework
- `laravel/tinker` (^3.0) — REPL
- `spatie/laravel-permission` — Roles & permissions
- `laravel/sanctum` — API tokens
- `blade-ui-kit/blade-heroicons` — Heroicons

### Dev Dependencies

- `laravel/boost` (^2.2)
- `laravel/pint` (^1.27) — Code formatter
- `phpunit/phpunit` (^12.5.12)
- `fakerphp/faker` (^1.23)

### NPM Packages

- `tailwindcss` (^4.0.0)
- `@tailwindcss/vite` (^4.0.0)
- `laravel-vite-plugin` (^3.0.0)
- `vite` (^8.0.0)
- `concurrently` (^9.0.1)

## What's Built

### Phase 1: Core Infrastructure (Completed)

| Feature | Status |
|---------|--------|
| Posts system (WordPress-like schema, soft deletes) | ✅ |
| Filament 5 admin panel (Posts CRUD) | ✅ |
| User Roles & Permissions (spatie/laravel-permission, 6 roles, 16 permissions) | ✅ |
| License System (phone-home verification, feature gating, Blade @feature directive) | ✅ |
| Settings/Options system (autoload caching, Filament settings page) | ✅ |
| Categories & Tags (polymorphic taxonomy system) | ✅ |
| Media Library (CRUD, grid/list, image optimizer with monthly caps) | ✅ |
| Pages (post_type=page scope, templates, parent selector) | ✅ |
| REST API (read-only, Sanctum auth, 13 endpoints, paginated) | ✅ |
| Front-end Routing (FrontendController, Blade views) | ✅ |

### Phase 2: Public CMS (Completed)

| Feature | Status |
|---------|--------|
| Theme System (ThemeManager, ThemeServiceProvider, theme:: namespace) | ✅ |
| Default Starter Theme (Tailwind, full template set) | ✅ |
| Menu Management (migration, Filament resource, front-end rendering) | ✅ |
| Widget System (widget areas, 5 widget types, Filament resource) | ✅ |
| Comments (polymorphic, moderation, rate limiting, honeypot, threaded) | ✅ |
| Custom Fields / Post Meta (key-value, Filament repeater) | ✅ |
| Full-Page Cache (middleware, cache-busting on content change) | ✅ |

### Phase 3: Headless & Advanced Features (In Progress)

| Feature | Status |
|---------|--------|
| Content Revisions (auto-snapshots, diff viewer, restore, 50 cap) | ✅ |
| Scheduled Publishing (scheduled_at field, status management, scheduler) | ✅ |
| Draft Management (permanent signed preview URLs, 5s auto-save on create/edit) | ✅ |

## Project Structure

```
app/
├── Console/Commands/
│   └── ClearSairoxCache.php        # php artisan sairox:clear-cache
├── Filament/Resources/
│   ├── Comments/                    # CommentResource (Content group)
│   ├── Media/                       # MediaFileResource
│   ├── Menus/                       # MenuResource (Appearance group)
│   ├── Pages/                       # PageResource
│   ├── Posts/                       # PostResource
│   │   ├── RelationManagers/        # RevisionsRelationManager
│   │   ├── Pages/ (CreatePost, EditPost, ListPosts, ViewPost)
│   │   ├── Schemas/ (PostForm, PostInfolist)
│   │   └── Tables/ (PostsTable)
│   ├── Roles/                       # RoleResource
│   ├── Settings/                    # SettingResource
│   ├── Taxonomies/                  # CategoryResource, TagResource
│   └── Widgets/                     # WidgetResource (Appearance group)
├── Http/
│   ├── Controllers/
│   │   ├── Api/                     # REST API controllers (read-only)
│   │   ├── CommentController.php    # Public comment submission
│   │   └── FrontendController.php   # Front-end routing
│   └── Middleware/
│       ├── FeatureMiddleware.php    # License feature gating
│       ├── PageCache.php            # Full-page cache
│       └── VerifyApiKey.php         # API key auth
├── Models/
│   ├── Comment.php, Menu.php, MenuItem.php
│   ├── Option.php, Page.php, Post.php, PostMeta.php
│   ├── Revision.php                 # Polymorphic revision snapshots
│   ├── Taxonomy.php, Term.php
│   ├── MediaFile.php, User.php
│   ├── Widget.php, WidgetArea.php
├── Providers/
│   ├── Filament/AdminPanelProvider.php
│   ├── ThemeServiceProvider.php     # Theme view namespaces
│   └── AppServiceProvider.php
├── Sairox/
│   ├── ThemeManager.php
│   ├── License/
│   │   └── LicenseService.php
│   └── Media/
│       └── ImageOptimizer.php
└── Traits/
    └── HasRevisions.php            # Auto-snapshot on save, restore, diff

themes/
└── sairox-default/                  # Default starter theme
    ├── theme.json
    └── views/ (layouts, partials, index, single, archive, etc.)
```
