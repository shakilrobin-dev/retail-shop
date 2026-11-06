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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" data-theme="theme-saas">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }} · {{ $shopName }}</title>
    @include('layouts.partials.theme-script')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background-1 text-foreground"
    x-data="{ mobileMenu: false, collapsed: $persist(false).as('sidebar-collapsed') }">

    <x-flash global />

    <div x-cloak x-show="mobileMenu" x-transition.opacity x-on:click="mobileMenu = false" class="fixed inset-0 z-50 bg-gray-900/40 lg:hidden"></div>

    {{-- Sidebar --}}
    <aside
        x-bind:class="[mobileMenu ? 'translate-x-0 bg-background shadow-xl' : '-translate-x-full lg:translate-x-0', collapsed ? 'lg:w-[60px]' : 'lg:w-60']"
        class="fixed inset-y-0 start-0 z-60 flex w-60 -translate-x-full flex-col bg-background-1 transition-[width,transform] duration-200 lg:translate-x-0"
        aria-label="{{ __('Sidebar') }}">

        {{-- Workspace switcher --}}
        <div class="px-2.5 pt-3">
            <div class="hs-dropdown relative [--placement:bottom-left] [--offset:4]">
                <button id="workspace-menu" type="button" class="hs-dropdown-toggle flex w-full items-center gap-2 rounded-md px-1.5 py-1.5 text-start hover:bg-muted-hover focus:outline-hidden" aria-haspopup="menu" aria-expanded="false">
                    @if ($logo)
                        <img src="{{ asset('storage/'.$logo) }}" alt="" class="size-6 shrink-0 rounded-md bg-layer object-contain ring-1 ring-layer-line">
                    @else
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary text-[10px] font-bold text-primary-foreground">{{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}</span>
                    @endif
                    <span x-show="!collapsed" class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold leading-tight text-foreground">{{ $shopName }}</span>
                        <span class="block truncate text-[11px] leading-tight text-muted-foreground-1">{{ $branch?->localName() }}</span>
                    </span>
                    <svg x-show="!collapsed" class="size-3.5 shrink-0 text-muted-foreground" viewBox="0 0 16 16" fill="currentColor"><path d="M8 3.5 11 7H5l3-3.5Zm0 9L5 9h6l-3 3.5Z"/></svg>
                </button>
                <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 z-70 hidden w-56 rounded-lg border border-dropdown-line bg-dropdown p-1 opacity-0 shadow-lg transition-[opacity,margin] duration" role="menu" aria-labelledby="workspace-menu">
                    <p class="px-2 py-1.5 text-[11px] font-medium text-muted-foreground-1">{{ __('Branches') }}</p>
                    @foreach ($branches as $option)
                        <form method="POST" action="{{ route('branch.switch') }}">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $option->id }}">
                            <button type="submit" class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[13px] text-dropdown-item-foreground hover:bg-dropdown-item-hover">
                                {{ $option->localName() }}
                                @if ($option->id === $branch?->id)<x-icon name="check" class="size-4 text-primary" />@endif
                            </button>
                        </form>
                    @endforeach
                    @can('settings.manage')
                        <div class="my-1 border-t border-dropdown-divider"></div>
                        <a href="{{ route('settings.general') }}" wire:navigate class="flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-dropdown-item-foreground hover:bg-dropdown-item-hover"><x-icon name="settings" class="size-4 opacity-70" /> {{ __('Shop settings') }}</a>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Search --}}
        <div class="px-2.5 pb-1 pt-2" x-show="!collapsed">
            <livewire:global-search />
        </div>

        <nav class="scroll-thin flex-1 overflow-y-auto px-2.5 pb-3">
            @foreach ($menu as [$groupLabel, $items])
                @php $visible = collect($items)->filter(fn ($item) => ! $item[3] || $user->can($item[3])); @endphp
                @continue($visible->isEmpty())
                <div class="mt-3 first:mt-1">
                    @if ($groupLabel)
                        <p x-show="!collapsed" class="mb-0.5 px-2 text-[11px] font-medium text-muted-foreground-1">{{ $groupLabel }}</p>
                    @endif
                    <ul class="space-y-px">
                        @foreach ($visible as [$label, $icon, $routeName, $permission, $patterns, $badge])
                            @php $active = request()->routeIs(...explode('|', $patterns)); @endphp
                            <li>
                                <a href="{{ route($routeName) }}" wire:navigate @if ($active) aria-current="page" @endif
                                    x-bind:title="collapsed ? @js($label) : null"
                                    :class="collapsed ? 'lg:justify-center' : ''"
                                    @class([
                                        'relative flex h-7 items-center gap-x-2 rounded-md px-2 text-[13px] transition-colors focus:outline-hidden',
                                        'bg-layer font-medium text-foreground shadow-[0_1px_2px_rgb(0_0_0/0.06)] ring-1 ring-layer-line' => $active,
                                        'text-muted-foreground-2 hover:bg-muted-hover hover:text-foreground' => ! $active,
                                    ])>
                                    <x-icon :name="$icon" @class(['size-4 shrink-0', 'text-primary' => $active, 'text-muted-foreground-1' => ! $active]) />
                                    <span x-show="!collapsed" class="flex-1 truncate">{{ $label }}</span>
                                    @if ($badge > 0)
                                        <span x-show="!collapsed" class="rounded bg-primary/10 px-1.5 text-[11px] font-semibold text-primary">{{ shop_digits($badge) }}</span>
                                        <span x-show="collapsed" x-cloak class="absolute end-1 top-1 size-1.5 rounded-full bg-primary"></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        {{-- Profile --}}
        <div class="border-t border-sidebar-divider p-2.5">
            <div class="hs-dropdown relative [--placement:top-left] [--offset:6]">
                <button id="user-menu" type="button" class="hs-dropdown-toggle flex w-full items-center gap-2 rounded-md px-1.5 py-1.5 text-start hover:bg-muted-hover focus:outline-hidden" aria-haspopup="menu" aria-expanded="false" aria-label="{{ __('User menu') }}">
                    <x-avatar :name="$user->name" size="size-6" class="text-[10px]" />
                    <span x-show="!collapsed" class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-medium leading-tight text-foreground">{{ $user->name }}</span>
                        <span class="block truncate text-[11px] leading-tight text-muted-foreground-1">{{ __(ucfirst($user->getRoleNames()->first() ?? '')) }}</span>
                    </span>
                </button>
                <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 z-70 hidden w-56 rounded-lg border border-dropdown-line bg-dropdown p-1 opacity-0 shadow-lg transition-[opacity,margin] duration" role="menu" aria-labelledby="user-menu">
                    <div class="px-2 py-1.5 text-[11px] text-muted-foreground-1">{{ __('Signed in as') }} <span class="font-medium text-foreground">{{ $user->username }}</span></div>
                    <div class="px-2 py-1.5"><x-language-switch /></div>
                    <div class="my-1 border-t border-dropdown-divider"></div>
                    <button type="button" x-on:click="collapsed = !collapsed" class="hidden w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-dropdown-item-foreground hover:bg-dropdown-item-hover lg:flex">
                        <x-icon name="collapse" class="size-4 opacity-70" /> <span x-text="collapsed ? @js(__('Expand menu')) : @js(__('Collapse menu'))"></span>
                    </button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-dropdown-item-foreground hover:bg-dropdown-item-hover"><x-icon name="logout" class="size-4 opacity-70" /> {{ __('Log out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Content panel --}}
    <div x-bind:class="collapsed ? 'lg:ps-[60px]' : 'lg:ps-60'" class="transition-[padding] duration-200 lg:ps-60">
        <div class="min-h-screen bg-background lg:my-2 lg:me-2 lg:min-h-[calc(100vh-1rem)] lg:rounded-xl lg:border lg:border-card-line lg:shadow-[0_1px_3px_rgb(0_0_0/0.04)]">
            <header class="no-print sticky top-0 z-40 flex h-12 items-center gap-x-3 border-b border-card-line bg-background/90 px-4 backdrop-blur lg:rounded-t-xl sm:px-6">
                <button type="button" class="flex size-8 items-center justify-center rounded-md text-muted-foreground-2 hover:bg-muted-hover lg:hidden" x-on:click="mobileMenu = true" aria-label="{{ __('Open menu') }}">
                    <x-icon name="menu" class="size-4" />
                </button>

                <ol class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px]" aria-label="Breadcrumb">
                    @if ($crumbGroup)
                        <li class="text-muted-foreground-1">{{ $crumbGroup }}</li>
                        <li class="text-muted-foreground">/</li>
                    @endif
                    <li class="truncate font-medium text-foreground" aria-current="page">{{ $crumbPage ?? ($title ?? '') }}</li>
                </ol>

                <div class="flex-1"></div>

                <button type="button" class="hs-dark-mode hs-dark-mode-active:hidden flex size-8 items-center justify-center rounded-md text-muted-foreground-2 hover:bg-muted-hover" data-hs-theme-click-value="dark" aria-label="{{ __('Dark mode') }}">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>
                <button type="button" class="hs-dark-mode hs-dark-mode-active:flex hidden size-8 items-center justify-center rounded-md text-muted-foreground-2 hover:bg-muted-hover" data-hs-theme-click-value="light" aria-label="{{ __('Light mode') }}">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                </button>
            </header>

            <main class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
