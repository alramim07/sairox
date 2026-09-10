<article class="format-status">
    <p class="text-lg text-gray-800">{{ strip_tags($post->post_content) }}</p>
    <span class="text-sm text-gray-500">{{ $post->created_at->format('F j, Y g:i A') }}</span>
</article>