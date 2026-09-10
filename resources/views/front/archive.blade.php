@extends('theme::layouts.app')

@section('title', $title)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">{{ $title }}</h1>
    <div class="grid gap-6">
        @forelse($posts as $post)
        @include('theme::partials.post-card')
        @empty
        <p class="text-gray-500 text-center py-12">No posts found.</p>
        @endforelse
    </div>
</div>
@endsection
