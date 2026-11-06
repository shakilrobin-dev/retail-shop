@props(['title' => null, 'description' => null, 'actions' => null, 'padding' => true])

{{-- Card with an optional header. --}}
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || $actions)
        <div class="card-header">
            <div>
                @if ($title)<h2 class="card-title">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-xs text-muted-foreground-1">{{ $description }}</p>@endif
            </div>
            @if ($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endif
        </div>
    @endif
    <div @class(['p-5' => $padding])>{{ $slot }}</div>
</section>
