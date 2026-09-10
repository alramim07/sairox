<article class="format-link">
    @php
        $linkUrl = trim(strip_tags($post->post_content));
        if (! in_array(parse_url($linkUrl, PHP_URL_SCHEME), ['http', 'https', 'mailto'], true)) {
            $linkUrl = '#';
        }
    @endphp
    <h1 class="text-2xl md:text-3xl font-bold mb-4">
        <a href="{{ $linkUrl }}" class="text-amber-600 hover:text-amber-700">{{ $post->post_title }}</a>
    </h1>
    <div class="flex items-center text-sm text-gray-500">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
    </div>
</article>