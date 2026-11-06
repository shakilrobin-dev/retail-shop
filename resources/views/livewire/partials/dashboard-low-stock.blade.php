<x-section :title="__('Low stock items')" :padding="false">
    @can('products.manage')
        <x-slot:actions><a href="{{ route('products.index', ['stockFilter' => 'low']) }}" wire:navigate class="text-xs font-medium text-primary-600 hover:underline">{{ __('View all') }} →</a></x-slot:actions>
    @endcan
    <table class="data-table">
        <tbody>
            @forelse ($lowStock as $stock)
                <tr wire:key="dlow-{{ $stock->id }}">
                    <td>{{ $stock->product->localName() }}</td>
                    <td class="text-right"><x-badge tone="amber">{{ shop_digits($stock->quantity + 0) }} / {{ shop_digits($stock->product->reorder_level + 0) }}</x-badge></td>
                </tr>
            @empty
                <x-empty :colspan="2" icon="check" :title="__('All products have enough stock.')" />
            @endforelse
        </tbody>
    </table>
</x-section>
