@props(['options', 'placeholder' => null, 'exclude' => []])

{{-- Category picker that shows the tree with indentation. --}}
<select {{ $attributes->merge(['class' => 'form-input']) }}>
    @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
    @foreach ($options as $option)
        @continue(in_array($option['category']->id, $exclude, true))
        <option value="{{ $option['category']->id }}" title="{{ $option['path'] }}">{!! str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $option['depth']) !!}{{ $option['depth'] ? '└ ' : '' }}{{ $option['category']->localName() }}</option>
    @endforeach
</select>
