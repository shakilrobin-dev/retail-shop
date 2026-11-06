<div class="space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Stock transfers')" :subtitle="__('Send stock to another branch. The receiving branch confirms what arrived.')">
        <a href="{{ route('stock.transfers.create') }}" class="btn-primary" wire:navigate><x-icon name="transfer" class="size-4" /> {{ __('Send stock to branch') }}</a>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-right">{{ __('Items') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr wire:key="tr-{{ $transfer->id }}">
                        <td class="whitespace-nowrap">{{ shop_digits($transfer->sent_at?->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $transfer->reference_no }}</td>
                        <td>{{ $transfer->fromBranch->localName() }}</td>
                        <td>{{ $transfer->toBranch->localName() }}</td>
                        <td class="text-right">{{ shop_digits($transfer->items_count) }}</td>
                        <td>
                            <span @class(['badge', 'bg-amber-50 text-amber-700' => $transfer->status === \App\Enums\TransferStatus::InTransit, 'bg-emerald-50 text-emerald-700' => $transfer->status === \App\Enums\TransferStatus::Received, 'bg-surface text-muted-foreground-1' => $transfer->status === \App\Enums\TransferStatus::Cancelled])>{{ $transfer->status->label() }}</span>
                            @if ($transfer->status === \App\Enums\TransferStatus::InTransit && $transfer->to_branch_id === $branchId)<span class="badge bg-red-50 text-red-700">{{ __('Waiting for you') }}</span>@endif
                        </td>
                        <td class="text-right"><a href="{{ route('stock.transfers.show', $transfer) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <x-empty :colspan="7" icon="transfer" :title="__('No transfers yet.')" />
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $transfers->links() }}
</div>
