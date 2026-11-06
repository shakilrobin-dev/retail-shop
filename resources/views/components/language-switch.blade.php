@props(['dark' => false])

<form method="POST" action="{{ route('locale.switch') }}" {{ $attributes->merge(['class' => 'inline-flex rounded-md bg-surface p-0.5 text-xs']) }}>
    @csrf
    @foreach (['en' => 'EN', 'bn' => 'বাং'] as $code => $label)
        <button type="submit" name="locale" value="{{ $code }}" title="{{ $code === 'en' ? 'English' : 'বাংলা' }}"
            @class([
                'rounded px-2.5 py-0.5 font-medium transition-colors',
                'bg-layer text-foreground shadow-[0_1px_2px_rgb(0_0_0/0.08)]' => app()->getLocale() === $code,
                'text-muted-foreground-1 hover:text-foreground' => app()->getLocale() !== $code,
            ])
            @if (app()->getLocale() === $code) aria-current="true" @endif>
            {{ $label }}
        </button>
    @endforeach
</form>
