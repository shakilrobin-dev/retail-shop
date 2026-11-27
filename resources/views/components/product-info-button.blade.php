@props(['productId'])

{{-- Small (i) button that opens the product information pop-up (stock and prices). --}}
<button type="button" wire:click="$dispatch('show-product-info', { productId: {{ (int) $productId }} })"
    {{ $attributes->merge(['class' => 'no-print inline-flex shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-primary-600 focus:outline-hidden focus-visible:text-primary-600']) }}
    title="{{ __('Product information') }}" aria-label="{{ __('Product information') }}">
    <x-icon name="info" class="size-4" />
</button>
