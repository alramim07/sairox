<article class="format-{{ $post->post_format }}">
    @if($post->thumbnail)
    <img src="{{ Storage::url($post->thumbnail) }}" alt="{{ $post->post_title }}" class="w-full h-64 md:h-96 object-cover rounded-lg shadow-sm mb-8" loading="lazy">
    @endif

    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ $post->post_title }}</h1>

    <div class="flex items-center text-sm text-gray-500 mb-8">
        <span>{{ $post->author?->name }}</span>
        <span class="mx-2">·</span>
        <span>{{ $post->created_at->format('F j, Y') }}</span>
        @if($post->categories->isNotEmpty())
        <span class="mx-2">·</span>
        <span>
            @foreach($post->categories as $category)
            <a href="{{ url('/categories/' . $category->slug) }}" class="text-amber-600 hover:text-amber-700">{{ $category->name }}</a>@if(!$loop->last), @endif
            @endforeach
        </span>
        @endif
    </div>

    <div class="prose prose-lg max-w-none">
        {!! $post->post_content !!}
    </div>

    @if($post->tags->isNotEmpty())
    <div class="flex flex-wrap gap-2 mt-8 pt-6 border-t">
        @foreach($post->tags as $tag)
        <a href="{{ url('/tags/' . $tag->slug) }}" class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full hover:bg-amber-50 hover:text-amber-600 transition-colors">
            #{{ $tag->name }}
        </a>
        @endforeach
    </div>
    @endif
</article>
