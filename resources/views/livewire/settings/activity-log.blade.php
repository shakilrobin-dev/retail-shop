@php use Illuminate\Support\Str; @endphp
<div class="space-y-5">
    @include('settings.nav')

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 border-b border-card-divider px-4 py-3">
            <p class="flex-1 text-sm text-muted-foreground-1">{{ __('Who changed what and when. Prices, products, customers, users and repairs are recorded.') }}</p>
            <select wire:model.live="type" class="form-input w-44">
                <option value="">{{ __('All records') }}</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="userId" class="form-input w-44">
                <option value="">{{ __('All users') }}</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><x-table.sort field="date" :sort="$sortField" :dir="$sortDirection">{{ __('When') }}</x-table.sort><th>{{ __('User') }}</th><th>{{ __('Record') }}</th><th>{{ __('Change') }}</th></tr></thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php
                            $new = (array) ($activity->properties['attributes'] ?? []);
                            $old = (array) ($activity->properties['old'] ?? []);
                            $subject = $activity->subject;
                            $subjectName = $subject?->name ?? $subject?->job_no ?? ($subject?->product?->name ?? null) ?? '#'.$activity->subject_id;
                        @endphp
                        <tr wire:key="al-{{ $activity->id }}">
                            <td class="whitespace-nowrap text-xs text-muted-foreground-2">{{ shop_digits($activity->created_at->format('d M Y, h:i A')) }}</td>
                            <td><div class="flex items-center gap-2"><x-avatar :name="$activity->causer?->name ?? __('System')" size="size-6" /> {{ $activity->causer?->name ?? __('System') }}</div></td>
                            <td><span class="text-xs text-muted-foreground-1">{{ Str::headline(class_basename($activity->subject_type)) }}</span><div class="font-medium text-foreground">{{ $subjectName }}</div></td>
                            <td class="text-xs">
                                @if ($activity->event === 'updated')
                                    @foreach ($new as $field => $value)
                                        <div><span class="text-muted-foreground-1">{{ __(Str::headline($field)) }}:</span> <span class="text-muted-foreground line-through">{{ is_bool($old[$field] ?? null) ? (($old[$field] ?? false) ? __('Yes') : __('No')) : Str::limit((string) ($old[$field] ?? '—'), 40) }}</span> → <span class="font-medium text-foreground">{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : Str::limit((string) ($value ?? '—'), 40) }}</span></div>
                                    @endforeach
                                @else
                                    <x-badge :tone="$activity->event === 'deleted' ? 'red' : ($activity->event === 'created' ? 'green' : 'slate')">{{ $activity->event ? __(ucfirst($activity->event)) : $activity->description }}</x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="4" icon="shield" :title="__('No changes recorded yet.')" />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$activities" />
    </div>
</div>
