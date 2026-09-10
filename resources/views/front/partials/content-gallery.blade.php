<article class="format-gallery">
    @php $images = $post->galleryImages(); @endphp
    @if($images)
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        @foreach($images as $src)
        <img src="{{ $src }}" alt="{{ $post->post_title }}" class="w-full h-48 object-cover rounded-lg" loading="lazy">
        @endforeach
    </div>
    @endif
    <div class="prose prose-lg max-w-none">{!! $post->post_content !!}</div>
</article>