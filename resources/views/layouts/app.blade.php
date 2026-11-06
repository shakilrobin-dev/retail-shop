@php
    use App\Enums\RepairStatus;
    use App\Enums\TransferStatus;
    use App\Models\RepairJob;
    use App\Models\StockTransfer;

    $settings = app(\App\Services\Settings::class);
    $currentBranch = app(\App\Services\CurrentBranch::class);
    $branch = $currentBranch->get();
    $branches = $currentBranch->available();
    $user = auth()->user();
    $shopName = (app()->getLocale() === 'bn' ? $settings->get('shop_name_bn') : null) ?: $settings->get('shop_name');
    $logo = $settings->get('shop_logo');

    $readyRepairs = $user->can('repairs.manage') ? RepairJob::query()->where('branch_id', $branch?->id)->where('status', RepairStatus::Ready)->count() : 0;
    $incomingTransfers = $user->can('stock.transfer') ? StockTransfer::query()->where('to_branch_id', $branch?->id)->where('status', TransferStatus::InTransit)->count() : 0;

    /**
     * Sidebar: groups of [label, icon, route, permission, active patterns, badge].
     */
    $menu = [
        [null, [
            [__('Dashboard'), 'home', 'dashboard', null, 'dashboard', 0],
        ]],
        [__('Sales'), [
            [__('POS / Sale'), 'cart', 'pos', 'pos.sell', 'pos', 0],
            [__('Sales'), 'receipt', 'sales.index', 'pos.sell', 'sales.*', 0],
            [__('Customers'), 'users', 'customers.index', 'pos.sell', 'customers.*', 0],
            [__('Cash register'), 'banknotes', 'cash-register', 'cash-shifts.manage', 'cash-register', 0],
        ]],
        [__('Service'), [
            [__('Repairs'), 'wrench', 'repairs.index', 'repairs.manage', 'repairs.*', $readyRepairs],
        ]],
        [__('Inventory'), [
            [__('Products'), 'cube', 'products.index', 'products.manage', 'products.*', 0],
            [__('Categories & brands'), 'folder', 'catalog.setup', 'products.manage', 'catalog.*', 0],
            [__('Stock'), 'archive', 'stock.index', 'stock.adjust', 'stock.index|stock.movements|stock.adjust', 0],
            [__('Transfers'), 'transfer', 'stock.transfers.index', 'stock.transfer', 'stock.transfers.*', $incomingTransfers],
            [__('Barcode labels'), 'tag', 'labels.index', 'products.manage', 'labels.*', 0],
        ]],
        [__('Purchasing'), [
            [__('Purchases'), 'truck', 'purchases.index', 'purchases.manage', 'purchases.*', 0],
            [__('Suppliers'), 'building', 'suppliers.index', 'purchases.manage', 'suppliers.*', 0],
        ]],
        [__('Finance'), [
            [__('Expenses'), 'banknotes', 'expenses.index', 'expenses.manage', 'expenses.*', 0],
            [__('Reports'), 'chart', 'reports.index', 'reports.view', 'reports.*', 0],
        ]],
        [__('Administration'), [
            [__('Settings'), 'settings', 'settings.general', 'settings.manage', 'settings.*', 0],
        ]],
    ];

    $crumbGroup = null;
    $crumbPage = null;

    foreach ($menu as [$groupLabel, $items]) {
        foreach ($items as [$label, , $routeName, , $patterns]) {
            if (request()->routeIs(...explode('|', $patterns))) {
                $crumbGroup = $groupLabel;
                $crumbPage = $label;
            }
        }
    }
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
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased"
    x-data="{ mobileMenu: false, collapsed: $persist(false).as('sidebar-collapsed') }">

    <x-flash global />

    {{-- Mobile overlay --}}
    <div x-cloak x-show="mobileMenu" x-transition.opacity x-on:click="mobileMenu = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        x-bind:class="[mobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[72px]' : 'lg:w-64']"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-nav text-slate-300 transition-all duration-200 lg:translate-x-0">
        <div class="flex h-16 shrink-0 items-center gap-3 px-4">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                @if ($logo)
                    <img src="{{ asset('storage/'.$logo) }}" alt="" class="size-9 shrink-0 rounded-lg bg-white object-contain p-0.5">
                @else
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($shopName, 0, 2)) }}</span>
                @endif
                <span x-show="!collapsed" class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-white">{{ $shopName }}</span>
                    <span class="block truncate text-[11px] text-slate-400">{{ $branch?->localName() }}</span>
                </span>
            </a>
            <button type="button" class="ml-auto text-slate-400 hover:text-white lg:hidden" x-on:click="mobileMenu = false" aria-label="{{ __('Close menu') }}">
                <x-icon name="close" />
            </button>
        </div>

        <nav class="scroll-thin flex-1 space-y-5 overflow-y-auto px-3 py-3">
            @foreach ($menu as [$groupLabel, $items])
                @php $visible = collect($items)->filter(fn ($item) => ! $item[3] || $user->can($item[3])); @endphp
                @continue($visible->isEmpty())
                <div>
                    @if ($groupLabel)
                        <p x-show="!collapsed" class="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-500">{{ $groupLabel }}</p>
                        <div x-show="collapsed" x-cloak class="mx-3 mb-2 border-t border-slate-800"></div>
                    @endif
                    <div class="space-y-0.5">
                        @foreach ($visible as [$label, $icon, $routeName, $permission, $patterns, $badge])
                            @php $active = request()->routeIs(...explode('|', $patterns)); @endphp
                            <a href="{{ route($routeName) }}" wire:navigate title="{{ $label }}"
                                @class([
                                    'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition',
                                    'bg-white/10 text-white' => $active,
                                    'text-slate-400 hover:bg-nav-hover hover:text-white' => ! $active,
                                ])>
                                @if ($active)<span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-400"></span>@endif
                                <x-icon :name="$icon" @class(['size-[18px]', 'text-brand-300' => $active]) />
                                <span x-show="!collapsed" class="flex-1 truncate">{{ $label }}</span>
                                @if ($badge > 0)
                                    <span x-show="!collapsed" class="rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-white">{{ shop_digits($badge) }}</span>
                                    <span x-show="collapsed" x-cloak class="absolute right-2 top-1.5 size-2 rounded-full bg-brand-400"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="hidden border-t border-slate-800 p-3 lg:block">
            <button type="button" x-on:click="collapsed = !collapsed" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[13px] text-slate-400 hover:bg-nav-hover hover:text-white">
                <span x-show="!collapsed"><x-icon name="collapse" class="size-[18px]" /></span>
                <span x-show="collapsed" x-cloak><x-icon name="expand" class="size-[18px]" /></span>
                <span x-show="!collapsed">{{ __('Collapse menu') }}</span>
            </button>
        </div>
    </aside>

    {{-- Main area --}}
    <div x-bind:class="collapsed ? 'lg:pl-[72px]' : 'lg:pl-64'" class="transition-all duration-200 lg:pl-64">
        <header class="no-print sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="text-slate-500 lg:hidden" x-on:click="mobileMenu = true" aria-label="{{ __('Open menu') }}">
                <x-icon name="menu" class="size-6" />
            </button>

            <nav class="hidden min-w-0 items-center gap-1.5 text-sm md:flex" aria-label="Breadcrumb">
                <a href="{{ route('dashboard') }}" wire:navigate class="text-slate-400 hover:text-slate-600"><x-icon name="home" class="size-4" /></a>
                @if ($crumbGroup)
                    <x-icon name="chevron-right" class="size-3.5 text-slate-300" />
                    <span class="text-slate-500">{{ $crumbGroup }}</span>
                @endif
                @if ($crumbPage && $crumbPage !== __('Dashboard') && $crumbPage !== $crumbGroup)
                    <x-icon name="chevron-right" class="size-3.5 text-slate-300" />
                    <span class="truncate font-medium text-slate-800">{{ $crumbPage }}</span>
                @endif
            </nav>

            <div class="flex-1"></div>

            <livewire:global-search />

            @if ($branches->count() > 1)
                <form method="POST" action="{{ route('branch.switch') }}" class="hidden sm:block">
                    @csrf
                    <label for="branch_id" class="sr-only">{{ __('Branch') }}</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="form-input w-auto py-1.5 text-[13px]">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch?->id)>{{ $option->localName() }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <x-language-switch class="hidden sm:inline-flex" />

            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 hover:bg-slate-100">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="hidden text-left text-[13px] leading-tight lg:block">
                        <span class="block font-medium text-slate-800">{{ $user->name }}</span>
                        <span class="block text-[11px] text-slate-500">{{ __(ucfirst($user->getRoleNames()->first() ?? '')) }}</span>
                    </span>
                    <x-icon name="chevron-down" class="size-4 text-slate-400" />
                </button>
                <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                    <div class="px-3 py-2 text-xs text-slate-500">{{ __('Signed in as') }} <span class="font-medium text-slate-700">{{ $user->username }}</span></div>
                    <div class="px-3 pb-2 sm:hidden"><x-language-switch /></div>
                    <div class="my-1 border-t border-slate-100"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                            <x-icon name="logout" class="size-4" /> {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
