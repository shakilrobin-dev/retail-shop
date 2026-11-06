@php
    $settings = app(\App\Services\Settings::class);
    $shopName = (app()->getLocale() === 'bn' ? $settings->get('shop_name_bn') : null) ?: $settings->get('shop_name');
    $logo = $settings->get('shop_logo');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" data-theme="theme-saas">
<head>
    @include('layouts.partials.theme-script')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }} · {{ $shopName }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-primary-950 p-12 text-white lg:flex lg:flex-col">
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-primary-600/30 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 size-96 rounded-full bg-primary-500/20 blur-3xl"></div>
            <div class="relative flex items-center gap-3">
                @if ($logo)
                    <img src="{{ asset('storage/'.$logo) }}" alt="" class="size-11 rounded-xl bg-layer object-contain p-1">
                @else
                    <span class="flex size-11 items-center justify-center rounded-xl bg-primary-600 font-bold">{{ mb_strtoupper(mb_substr($shopName, 0, 2)) }}</span>
                @endif
                <span class="text-lg font-semibold">{{ $shopName }}</span>
            </div>
            <div class="relative mt-auto max-w-md">
                <h2 class="text-3xl font-semibold leading-tight">{{ __('Sales, stock and repairs in one place.') }}</h2>
                <p class="mt-4 text-muted-foreground">{{ __('Scan to sell, track every piece of stock across branches, and follow each repair from intake to delivery.') }}</p>
                <div class="mt-8 grid grid-cols-3 gap-4 text-sm">
                    @foreach ([['cart', __('POS')], ['archive', __('Inventory')], ['wrench', __('Repairs')]] as [$icon, $label])
                        <div class="rounded-xl border border-white/10 bg-layer/5 p-3"><x-icon :name="$icon" class="size-5 text-primary-300" /><div class="mt-2 font-medium">{{ $label }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="flex flex-col px-6 py-8 sm:px-12">
            <div class="flex justify-end"><x-language-switch /></div>
            <div class="flex flex-1 items-center justify-center">
                {{ $slot }}
            </div>
            <p class="text-center text-xs text-muted-foreground">&copy; {{ date('Y') }} {{ $shopName }}</p>
        </div>
    </div>
</body>
</html>
