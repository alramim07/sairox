<article class="format-link">
    <h1 class="text-2xl md:text-3xl font-bold mb-4">
        <a href="{{ trim(strip_tags($post->post_content)) }}" class="text-amber-600 hover:text-amber-700">{{ $post->post_title }}</a>
    </h1>
    <div class="flex items-center text-sm text-gray-500">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
    </div>
</article>