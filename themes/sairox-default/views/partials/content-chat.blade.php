<article class="format-chat space-y-3">
    @foreach($post->chatLines() as $line)
    <div class="flex @if($loop->even) justify-end @endif">
        <div class="bg-gray-100 rounded-lg px-4 py-2 max-w-md @if($loop->even) bg-amber-50 @endif">
            <p class="text-sm">{!! $line !!}</p>
        </div>
    </div>
    @endforeach
</article>