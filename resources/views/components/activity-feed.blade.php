@props(['subjects', 'limit' => 30])

{{--
    Change history ("chatter") for one or more records.
    $subjects = model or list of models, e.g. [$product, ...$product->units].
--}}
@php
    use Illuminate\Support\Str;
    use Spatie\Activitylog\Models\Activity;

    $subjects = collect(is_iterable($subjects) ? $subjects : [$subjects])->filter();
    $activities = $subjects->isEmpty() ? collect() : Activity::query()
        ->with('causer')
        ->where(function ($query) use ($subjects) {
            foreach ($subjects as $subject) {
                $query->orWhere(fn ($q) => $q->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey()));
            }
        })
        ->latest('id')
        ->limit($limit)
        ->get();

    $format = function ($value) {
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return Str::limit((string) $value, 60);
    };
    $events = ['created' => __('created'), 'updated' => __('changed'), 'deleted' => __('deleted')];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @forelse ($activities as $activity)
        @php
            $new = (array) ($activity->properties['attributes'] ?? []);
            $old = (array) ($activity->properties['old'] ?? []);
            $who = $activity->causer?->name ?? __('System');
        @endphp
        <div class="flex gap-3" wire:key="act-{{ $activity->id }}">
            <x-avatar :name="$who" size="size-7" />
            <div class="min-w-0 flex-1">
                <div class="text-[13px]">
                    <span class="font-medium text-foreground">{{ $who }}</span>
                    <span class="text-muted-foreground-1">{{ $events[$activity->event] ?? $activity->description }}</span>
                    @if ($subjects->count() > 1 && $activity->subject_type !== $subjects->first()->getMorphClass())
                        <span class="text-muted-foreground-1">· {{ class_basename($activity->subject_type) === 'ProductUnit' ? __('unit') : class_basename($activity->subject_type) }}</span>
                    @endif
                    <span class="text-xs text-muted-foreground" title="{{ $activity->created_at->format('d M Y h:i A') }}">· {{ $activity->created_at->diffForHumans() }}</span>
                </div>
                @if ($activity->event === 'updated' && $new)
                    <ul class="mt-1 space-y-0.5 text-xs text-muted-foreground-2">
                        @foreach ($new as $field => $value)
                            <li>
                                <span class="text-muted-foreground-1">{{ __(Str::headline($field)) }}:</span>
                                <span class="text-muted-foreground line-through">{{ $format($old[$field] ?? null) }}</span>
                                <span class="text-muted-foreground">→</span>
                                <span class="font-medium text-foreground">{{ $format($value) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($activity->event !== 'updated' && $activity->event !== 'created' && $activity->description)
                    <p class="mt-0.5 text-xs text-muted-foreground-2">{{ $activity->description }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-muted-foreground-1">{{ __('No changes recorded yet.') }}</p>
    @endforelse
</div>
