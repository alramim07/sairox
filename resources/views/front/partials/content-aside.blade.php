<article class="format-aside">
    <div class="text-lg italic text-gray-700 leading-relaxed prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </div>
    <div class="flex items-center text-sm text-gray-500 mt-4">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
    </div>
</article>