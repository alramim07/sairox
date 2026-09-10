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
