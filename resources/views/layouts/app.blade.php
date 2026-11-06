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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }} · {{ $shopName }}</title>
    @include('layouts.partials.theme-script')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background-1 text-foreground antialiased"
    x-data="{ mobileMenu: false, collapsed: $persist(false).as('sidebar-collapsed') }">

    <x-flash global />

    {{-- Mobile overlay --}}
    <div x-cloak x-show="mobileMenu" x-transition.opacity x-on:click="mobileMenu = false" class="fixed inset-0 z-50 bg-gray-900/50 dark:bg-neutral-900/80 lg:hidden"></div>

    {{-- Sidebar (Preline) --}}
    <aside
        x-bind:class="[mobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[72px]' : 'lg:w-65']"
        class="fixed inset-y-0 start-0 z-60 flex w-65 -translate-x-full flex-col border-e border-sidebar-line bg-sidebar transition-all duration-300 lg:translate-x-0"
        role="dialog" tabindex="-1" aria-label="{{ __('Sidebar') }}">
        <div class="flex h-16 shrink-0 items-center gap-x-3 px-4">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-x-3 rounded-lg focus:outline-hidden">
                @if ($logo)
                    <img src="{{ asset('storage/'.$logo) }}" alt="" class="size-9 shrink-0 rounded-lg border border-sidebar-line bg-layer object-contain p-0.5">
                @else
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">{{ mb_strtoupper(mb_substr($shopName, 0, 2)) }}</span>
                @endif
                <span x-show="!collapsed" class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-foreground">{{ $shopName }}</span>
                    <span class="block truncate text-xs text-muted-foreground-1">{{ $branch?->localName() }}</span>
                </span>
            </a>
            <button type="button" class="ms-auto flex size-8 items-center justify-center rounded-lg text-muted-foreground-1 hover:bg-sidebar-nav-hover lg:hidden" x-on:click="mobileMenu = false" aria-label="{{ __('Close menu') }}">
                <x-icon name="close" class="size-4" />
            </button>
        </div>

        <nav class="scroll-thin flex-1 overflow-y-auto px-3 pb-4">
            <ul class="flex flex-col space-y-0.5">
                @foreach ($menu as [$groupLabel, $items])
                    @php $visible = collect($items)->filter(fn ($item) => ! $item[3] || $user->can($item[3])); @endphp
                    @continue($visible->isEmpty())
                    @if ($groupLabel)
                        <li x-show="!collapsed" class="px-2.5 pb-1 pt-4 text-xs font-medium uppercase text-muted-foreground">{{ $groupLabel }}</li>
                        <li x-show="collapsed" x-cloak class="mx-2 my-2 border-t border-sidebar-divider"></li>
                    @endif
                    @foreach ($visible as [$label, $icon, $routeName, $permission, $patterns, $badge])
                        @php $active = request()->routeIs(...explode('|', $patterns)); @endphp
                        <li>
                            <a href="{{ route($routeName) }}" wire:navigate
                                @if ($active) aria-current="page" @endif
                                :class="collapsed ? 'lg:justify-center' : ''"
                                @class([
                                    'relative flex w-full items-center gap-x-3.5 rounded-lg px-2.5 py-2 text-sm focus:outline-hidden focus:bg-sidebar-nav-focus',
                                    'bg-sidebar-nav-active font-medium text-primary' => $active,
                                    'text-sidebar-nav-foreground hover:bg-sidebar-nav-hover' => ! $active,
                                ])
                                x-bind:title="collapsed ? @js($label) : null">
                                <x-icon :name="$icon" @class(['size-4 shrink-0', 'text-primary' => $active, 'text-muted-foreground-1' => ! $active]) />
                                <span x-show="!collapsed" class="flex-1 truncate">{{ $label }}</span>
                                @if ($badge > 0)
                                    <span x-show="!collapsed" class="inline-flex items-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold leading-none text-primary-foreground">{{ shop_digits($badge) }}</span>
                                    <span x-show="collapsed" x-cloak class="absolute end-1.5 top-1.5 size-2 rounded-full bg-primary"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </nav>

        <div class="hidden border-t border-sidebar-divider p-3 lg:block">
            <button type="button" x-on:click="collapsed = !collapsed" :class="collapsed ? 'justify-center' : ''" class="flex w-full items-center gap-x-3.5 rounded-lg px-2.5 py-2 text-sm text-muted-foreground-1 hover:bg-sidebar-nav-hover">
                <span x-show="!collapsed"><x-icon name="collapse" class="size-4" /></span>
                <span x-show="collapsed" x-cloak><x-icon name="expand" class="size-4" /></span>
                <span x-show="!collapsed">{{ __('Collapse menu') }}</span>
            </button>
        </div>
    </aside>

    {{-- Main area --}}
    <div x-bind:class="collapsed ? 'lg:ps-[72px]' : 'lg:ps-65'" class="transition-all duration-300 lg:ps-65">
        {{-- Header (Preline navbar) --}}
        <header class="no-print sticky top-0 z-48 flex h-16 w-full items-center gap-x-3 border-b border-navbar-line bg-navbar px-4 sm:px-6">
            <button type="button" class="flex size-9 items-center justify-center rounded-lg border border-layer-line bg-layer text-layer-foreground lg:hidden" x-on:click="mobileMenu = true" aria-label="{{ __('Open menu') }}">
                <x-icon name="menu" class="size-4" />
            </button>

            <ol class="hidden min-w-0 items-center whitespace-nowrap text-sm md:flex" aria-label="Breadcrumb">
                <li class="flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center text-muted-foreground-1 hover:text-primary-hover focus:outline-hidden"><x-icon name="home" class="size-4" /></a>
                </li>
                @if ($crumbGroup)
                    <li class="flex items-center"><x-icon name="chevron-right" class="mx-2 size-3.5 text-muted-foreground" /><span class="text-muted-foreground-1">{{ $crumbGroup }}</span></li>
                @endif
                @if ($crumbPage && $crumbPage !== __('Dashboard') && $crumbPage !== $crumbGroup)
                    <li class="flex items-center truncate font-semibold text-foreground" aria-current="page"><x-icon name="chevron-right" class="mx-2 size-3.5 text-muted-foreground" />{{ $crumbPage }}</li>
                @endif
            </ol>

            <div class="flex-1"></div>

            <livewire:global-search />

            @if ($branches->count() > 1)
                <form method="POST" action="{{ route('branch.switch') }}" class="hidden sm:block">
                    @csrf
                    <label for="branch_id" class="sr-only">{{ __('Branch') }}</label>
                    <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="form-input w-auto py-1.5 pe-8">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch?->id)>{{ $option->localName() }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <x-language-switch class="hidden sm:inline-flex" />

            {{-- Light / dark (Preline theme switch) --}}
            <button type="button" class="hs-dark-mode hs-dark-mode-active:hidden flex size-9 items-center justify-center rounded-full text-muted-foreground-2 hover:bg-muted-hover focus:outline-hidden" data-hs-theme-click-value="dark" aria-label="{{ __('Dark mode') }}">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>
            <button type="button" class="hs-dark-mode hs-dark-mode-active:flex hidden size-9 items-center justify-center rounded-full text-muted-foreground-2 hover:bg-muted-hover focus:outline-hidden" data-hs-theme-click-value="light" aria-label="{{ __('Light mode') }}">
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </button>

            {{-- User menu (Preline dropdown) --}}
            <div class="hs-dropdown relative inline-flex [--placement:bottom-right]">
                <button id="user-menu" type="button" class="hs-dropdown-toggle flex items-center gap-x-2 rounded-full py-1 ps-1 pe-2 hover:bg-muted-hover focus:outline-hidden" aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('User menu') }}">
                    <x-avatar :name="$user->name" />
                    <span class="hidden text-start text-[13px] leading-tight lg:block">
                        <span class="block font-medium text-foreground">{{ $user->name }}</span>
                        <span class="block text-xs text-muted-foreground-1">{{ __(ucfirst($user->getRoleNames()->first() ?? '')) }}</span>
                    </span>
                    <x-icon name="chevron-down" class="size-4 text-muted-foreground" />
                </button>
                <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 duration hidden min-w-60 rounded-xl bg-dropdown opacity-0 shadow-md transition-[opacity,margin]" role="menu" aria-orientation="vertical" aria-labelledby="user-menu">
                    <div class="rounded-t-xl bg-dropdown-header px-4 py-3">
                        <p class="text-sm text-muted-foreground-1">{{ __('Signed in as') }}</p>
                        <p class="text-sm font-medium text-foreground">{{ $user->username }}</p>
                    </div>
                    <div class="space-y-0.5 p-1.5">
                        <div class="px-2 py-1.5 sm:hidden"><x-language-switch /></div>
                        @can('settings.manage')
                            <a href="{{ route('settings.general') }}" wire:navigate class="flex items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm text-dropdown-item-foreground hover:bg-dropdown-item-hover focus:outline-hidden focus:bg-dropdown-item-focus"><x-icon name="settings" class="size-4" /> {{ __('Settings') }}</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm text-dropdown-item-foreground hover:bg-dropdown-item-hover focus:outline-hidden focus:bg-dropdown-item-focus">
                                <x-icon name="logout" class="size-4" /> {{ __('Log out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
