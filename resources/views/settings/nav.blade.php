@php
    $settingsNav = [
        ['settings.general', ['tab' => 'shop'], __('Shop'), 'shop'],
        ['settings.general', ['tab' => 'receipt'], __('Receipt & printing'), 'receipt'],
        ['settings.general', ['tab' => 'labels'], __('Barcode labels'), 'labels'],
        ['settings.general', ['tab' => 'pos'], __('POS & VAT'), 'pos'],
        ['settings.general', ['tab' => 'repair'], __('Repair'), 'repair'],
        ['settings.general', ['tab' => 'reports'], __('Reports'), 'reports'],
        ['settings.general', ['tab' => 'sms'], __('SMS'), 'sms'],
        ['settings.branches', [], __('Branches'), null],
        ['settings.users', [], __('Users'), null],
        ['settings.roles', [], __('Permissions'), null],
        ['settings.payment-methods', [], __('Payment methods'), null],
        ['settings.backup', [], __('Backup'), null],
        ['settings.activity', [], __('Audit log'), null],
    ];
    $currentTab = request()->routeIs('settings.general') ? (request('tab') ?: ($tab ?? 'shop')) : null;
@endphp
<x-page-header class="mb-0" :title="__('Settings')" :subtitle="__('Control how the shop, POS, receipts and staff work.')" />
<nav class="-mt-1 flex gap-5 overflow-x-auto border-b border-card-line">
    @foreach ($settingsNav as [$routeName, $params, $label, $tabKey])
        @php $active = request()->routeIs($routeName) && ($tabKey === null || $currentTab === $tabKey); @endphp
        <a href="{{ route($routeName, $params) }}" wire:navigate class="{{ $active ? 'tab-link-active' : 'tab-link' }}">{{ $label }}</a>
    @endforeach
</nav>
