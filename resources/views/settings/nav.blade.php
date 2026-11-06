@php
    $settingsNav = [
        ['settings.general', ['tab' => 'shop'], __('Shop'), 'shop'],
        ['settings.general', ['tab' => 'receipt'], __('Receipt & printing'), 'receipt'],
        ['settings.general', ['tab' => 'labels'], __('Barcode labels'), 'labels'],
        ['settings.general', ['tab' => 'pos'], __('POS & VAT'), 'pos'],
        ['settings.general', ['tab' => 'repair'], __('Repair'), 'repair'],
        ['settings.general', ['tab' => 'sms'], __('SMS'), 'sms'],
        ['settings.branches', [], __('Branches'), null],
        ['settings.users', [], __('Users'), null],
        ['settings.roles', [], __('Permissions'), null],
        ['settings.payment-methods', [], __('Payment methods'), null],
        ['settings.backup', [], __('Backup'), null],
    ];
    $currentTab = request()->routeIs('settings.general') ? (request('tab') ?: ($tab ?? 'shop')) : null;
@endphp
<nav class="flex flex-wrap gap-1">
    @foreach ($settingsNav as [$routeName, $params, $label, $tabKey])
        @php $active = request()->routeIs($routeName) && ($tabKey === null || $currentTab === $tabKey); @endphp
        <a href="{{ route($routeName, $params) }}" wire:navigate @class(['btn btn-sm', 'bg-indigo-600 text-white' => $active, 'btn-secondary' => ! $active])>{{ $label }}</a>
    @endforeach
</nav>
