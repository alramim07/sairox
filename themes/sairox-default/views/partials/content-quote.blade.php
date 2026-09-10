<article class="format-quote">
    <blockquote class="border-l-4 border-amber-500 pl-6 my-8 prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </blockquote>
    <p class="text-sm text-gray-500">— {{ $post->post_title }}@if($post->author?->name) ({{ $post->author?->name }}) @endif</p>
</article>