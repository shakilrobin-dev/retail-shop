@props(['icon' => 'archive', 'title', 'text' => null, 'colspan' => null])


@if ($colspan)
    <tr class="hover:bg-transparent">
        <td colspan="{{ $colspan }}" class="px-4 py-12">
@endif
<div class="mx-auto flex max-w-sm flex-col items-center text-center">
    <div class="rounded-full bg-slate-100 p-3 text-slate-400"><x-icon :name="$icon" class="size-6" /></div>
    <p class="mt-3 text-sm font-medium text-slate-700">{{ $title }}</p>
    @if ($text)<p class="mt-1 text-sm text-slate-500">{{ $text }}</p>@endif
    @if (trim($slot))<div class="mt-4 flex gap-2">{{ $slot }}</div>@endif
</div>
@if ($colspan)
        </td>
    </tr>
@endif
