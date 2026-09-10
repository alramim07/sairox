<article class="format-{{ $post->post_format }}">
    <h1 class="text-3xl font-bold mb-6">{{ $post->post_title }}</h1>
    <div class="text-gray-500 text-sm mb-6">{{ $post->created_at->format('F j, Y') }}</div>
    <div class="prose max-w-none">{!! $post->post_content !!}</div>
</article>
