@props(['dark' => false])

<form method="POST" action="{{ route('locale.switch') }}" {{ $attributes->merge(['class' => 'inline-flex rounded-lg p-0.5 text-sm '.($dark ? 'bg-slate-800' : 'bg-slate-100')]) }}>
    @csrf
    @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $label)
        <button type="submit" name="locale" value="{{ $code }}"
            @class([
                'rounded-md px-3 py-1 font-medium transition',
                'bg-white text-slate-900 shadow-sm' => app()->getLocale() === $code,
                'text-slate-500 hover:text-slate-900' => app()->getLocale() !== $code && ! $dark,
                'text-slate-400 hover:text-white' => app()->getLocale() !== $code && $dark,
            ])
            @if (app()->getLocale() === $code) aria-current="true" @endif>
            {{ $label }}
        </button>
    @endforeach
</form>
