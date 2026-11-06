@php
    $currentBranch = app(\App\Services\CurrentBranch::class);
    $branch = $currentBranch->get();
    $branches = $currentBranch->available();
    $shopName = app(\App\Services\Settings::class)->get(app()->getLocale() === 'bn' ? 'shop_name_bn' : 'shop_name')
        ?: app(\App\Services\Settings::class)->get('shop_name');

    /** Menu: [label, icon, route name or null if not built yet, permission or null, active route pattern] */
    $menu = [
        [__('Dashboard'), 'home', 'dashboard', null, 'dashboard'],
        [__('POS / Sale'), 'cart', 'pos', 'pos.sell', 'pos'],
        [__('Cash register'), 'banknotes', 'cash-register', 'cash-shifts.manage', 'cash-register'],
        [__('Sales'), 'receipt', 'sales.index', 'pos.sell', 'sales.*'],
        [__('Repairs'), 'wrench', 'repairs.index', 'repairs.manage', 'repairs.*'],
        [__('Products'), 'cube', 'products.index', 'products.manage', 'products.*|catalog.*|labels.*'],
        [__('Purchases'), 'truck', 'purchases.index', 'purchases.manage', 'purchases.*'],
        [__('Stock'), 'archive', 'stock.index', 'stock.adjust', 'stock.*'],
        [__('Customers'), 'users', 'customers.index', 'pos.sell', 'customers.*'],
        [__('Suppliers'), 'building', 'suppliers.index', 'purchases.manage', 'suppliers.*'],
        [__('Expenses'), 'banknotes', 'expenses.index', 'expenses.manage', 'expenses.*'],
        [__('Reports'), 'chart', 'reports.index', 'reports.view', 'reports.*'],
        [__('Settings'), 'settings', 'settings.general', 'settings.manage', 'settings.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }} · {{ $shopName }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased" x-data="{ menuOpen: false }">
    {{-- Mobile overlay --}}
    <div x-cloak x-show="menuOpen" x-on:click="menuOpen = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside x-bind:class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center justify-between gap-2 border-b border-slate-800 px-5">
            <a href="{{ route('dashboard') }}" class="truncate text-lg font-semibold text-white">{{ $shopName }}</a>
            <button type="button" class="lg:hidden" x-on:click="menuOpen = false" aria-label="{{ __('Close menu') }}">
                <x-icon name="close" />
            </button>
        </div>

        <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
            @foreach ($menu as $item)
                @php [$label, $icon, $routeName, $permission] = $item; $activePattern = explode('|', $item[4] ?? (string) $routeName); @endphp
                @continue($permission && ! auth()->user()->can($permission))

                @if ($routeName)
                    <a href="{{ route($routeName) }}" wire:navigate
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                            'bg-indigo-600 text-white' => request()->routeIs(...$activePattern),
                            'hover:bg-slate-800 hover:text-white' => ! request()->routeIs(...$activePattern),
                        ])>
                        <x-icon :name="$icon" />
                        <span>{{ $label }}</span>
                    </a>
                @else
                    <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-500">
                        <x-icon :name="$icon" />
                        <span class="flex-1">{{ $label }}</span>
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-slate-400">{{ __('Soon') }}</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-slate-800 p-4">
            <div class="mb-3 text-sm">
                <div class="font-medium text-white">{{ auth()->user()->name }}</div>
                <div class="text-xs text-slate-400">{{ __(ucfirst(auth()->user()->getRoleNames()->first() ?? '')) }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-800 hover:text-white">
                    <x-icon name="logout" />
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </aside>

    {{-- Main area --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
            <button type="button" class="text-slate-600 lg:hidden" x-on:click="menuOpen = true" aria-label="{{ __('Open menu') }}">
                <x-icon name="menu" class="size-6" />
            </button>

            <h1 class="flex-1 truncate text-lg font-semibold">{{ $title ?? '' }}</h1>

            @if ($branches->count() > 1)
                <form method="POST" action="{{ route('branch.switch') }}">
                    @csrf
                    <label for="branch_id" class="sr-only">{{ __('Branch') }}</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white py-1.5 pl-3 pr-8 text-sm">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch?->id)>
                                {{ app()->getLocale() === 'bn' && $option->name_bn ? $option->name_bn : $option->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @elseif ($branch)
                <span class="hidden rounded-lg bg-slate-100 px-3 py-1.5 text-sm text-slate-600 sm:inline">
                    {{ app()->getLocale() === 'bn' && $branch->name_bn ? $branch->name_bn : $branch->name }}
                </span>
            @endif

            <x-language-switch />
        </header>

        <main class="p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
