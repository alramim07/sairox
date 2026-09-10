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
