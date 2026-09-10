<article class="format-image">
    @if($post->thumbnail)
    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->post_title }}" class="w-full h-64 md:h-96 object-cover rounded-lg shadow-sm mb-6" loading="lazy">
    @endif
    <h1 class="text-2xl md:text-3xl font-bold mb-4">{{ $post->post_title }}</h1>
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>