<div class="mx-auto max-w-4xl space-y-4">
    @include('settings.nav')
    <x-flash />

    <div class="card flex flex-wrap items-center justify-between gap-3 p-5">
        <div class="text-sm text-slate-600">
            <p>{{ __('A backup is made automatically every night (last 14 are kept).') }}</p>
            <p>{{ __('Download a backup at least once a week and keep it on your computer or Google Drive.') }}</p>
        </div>
        <button type="button" wire:click="backupNow" wire:loading.attr="disabled" class="btn-primary">
            <span wire:loading.remove wire:target="backupNow">{{ __('Backup now') }}</span>
            <span wire:loading wire:target="backupNow">{{ __('Please wait...') }}</span>
        </button>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('File') }}</th><th class="text-right">{{ __('Size') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($backups as $backup)
                    <tr wire:key="bk-{{ $backup['name'] }}">
                        <td class="whitespace-nowrap">{{ shop_digits(\Illuminate\Support\Carbon::createFromTimestamp($backup['date'])->timezone(config('app.timezone'))->format('d M Y h:i A')) }}</td>
                        <td class="font-mono text-xs">{{ $backup['name'] }}</td>
                        <td class="text-right">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" wire:click="download('{{ $backup['name'] }}')" class="btn-secondary btn-sm">{{ __('Download') }}</button>
                            <button type="button" wire:click="delete('{{ $backup['name'] }}')" wire:confirm="{{ __('Delete this backup?') }}" class="btn-secondary btn-sm">✕</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">{{ __('No backups yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
