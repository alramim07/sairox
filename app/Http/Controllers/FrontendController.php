<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Term;
use App\Sairox\ThemeManager;
use Illuminate\Support\Facades\View;

class FrontendController extends Controller
{
    protected ThemeManager $themeManager;

    public function __construct()
    {
        $this->themeManager = app(ThemeManager::class);
    }

    protected function resolveView(string $view): string
    {
        return $this->themeManager->getThemeView($view);
    }

    public function home()
    {
        $posts = Post::with('author', 'categories', 'tags')
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->latest()
            ->paginate((int) get_option('posts_per_page', 10));

        return view($this->resolveView('index'), compact('posts'));
    }

    public function post(string $slug)
    {
        $post = Post::with('author', 'categories', 'tags')
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->where('slug', $slug)
            ->firstOrFail();

        return view($this->resolveView('single'), compact('post'));
    }

    public function preview(string $slug)
    {
        $post = Post::with('author', 'categories', 'tags')
            ->where('post_type', 'post')
            ->where('slug', $slug)
            ->firstOrFail();

        return view($this->resolveView('single'), compact('post'));
    }

    public function page(string $slug)
    {
        $page = Page::with('author')
            ->where('post_status', 'publish')
            ->where('slug', $slug)
            ->firstOrFail();

        $template = $page->getAttribute('template') ?? 'default';

        $view = $template !== 'default' && View::exists("theme::{$template}")
            ? "theme::{$template}"
            : $this->resolveView('page');

        return view($view, compact('page'));
    }

    public function category(string $slug)
    {
        $category = Term::with('taxonomy')
            ->whereHas('taxonomy', fn ($q) => $q->where('taxonomy', 'category'))
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::with('author')
            ->whereHas('categories', fn ($q) => $q->where('slug', $slug))
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->latest()
            ->paginate((int) get_option('posts_per_page', 10));

        $title = 'Category: '.$category->name;
        $description = $category->description ?? '';

        return view($this->resolveView('archive'), compact('category', 'posts', 'title', 'description'));
    }

    public function tag(string $slug)
    {
        $tag = Term::with('taxonomy')
            ->whereHas('taxonomy', fn ($q) => $q->where('taxonomy', 'post_tag'))
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::with('author')
            ->whereHas('tags', fn ($q) => $q->where('slug', $slug))
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->latest()
            ->paginate((int) get_option('posts_per_page', 10));

        $title = 'Tag: '.$tag->name;
        $description = '';

        return view($this->resolveView('archive'), compact('tag', 'posts', 'title', 'description'));
    }
}
