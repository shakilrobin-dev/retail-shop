@props(['dark' => false])

<form method="POST" action="{{ route('locale.switch') }}" {{ $attributes->merge(['class' => 'inline-flex rounded-lg p-0.5 text-sm '.($dark ? 'bg-slate-800' : 'bg-surface')]) }}>
    @csrf
    @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $label)
        <button type="submit" name="locale" value="{{ $code }}"
            @class([
                'rounded-md px-3 py-1 font-medium transition',
                'bg-layer text-foreground shadow-sm' => app()->getLocale() === $code,
                'text-muted-foreground-1 hover:text-foreground' => app()->getLocale() !== $code && ! $dark,
                'text-muted-foreground hover:text-white' => app()->getLocale() !== $code && $dark,
            ])
            @if (app()->getLocale() === $code) aria-current="true" @endif>
            {{ $label }}
        </button>
    @endforeach
</form>
