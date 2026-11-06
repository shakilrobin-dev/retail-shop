@php
    $toggle = fn (string $key, string $label, ?string $help = null) => ['key' => $key, 'label' => $label, 'help' => $help];
@endphp
<div class="mx-auto max-w-6xl space-y-5">
    @include('settings.nav')
    <x-flash />

    <form wire:submit="save" class="card space-y-5 p-6">
        @if ($tab === 'shop')
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="form-label">{{ __('Shop name (English)') }} *</label><input wire:model="values.shop_name" type="text" class="form-input">@error('values.shop_name') <p class="form-error">{{ $message }}</p> @enderror</div>
                <div><label class="form-label">{{ __('Shop name (Bangla)') }}</label><input wire:model="values.shop_name_bn" type="text" class="form-input"></div>
                <div><label class="form-label">{{ __('Phone') }}</label><input wire:model="values.shop_phone" type="text" class="form-input"></div>
                <div><label class="form-label">{{ __('Currency symbol') }}</label><input wire:model="values.currency_symbol" type="text" class="form-input w-24"></div>
                <div>
                    <label class="form-label">{{ __('Default language') }}</label>
                    <select wire:model="values.default_locale" class="form-input"><option value="en">English</option><option value="bn">বাংলা</option></select>
                </div>
                <div>
                    <label class="form-label">{{ __('Numbers in Bangla mode') }}</label>
                    <select wire:model="values.digits" class="form-input"><option value="en">123 (English digits)</option><option value="bn">১২৩ (বাংলা সংখ্যা)</option></select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Logo (for receipts)') }}</label>
                    <div class="flex items-center gap-4">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" alt="" class="h-16 rounded ring-1 ring-slate-200">
                        @elseif ($logoPath)
                            <img src="{{ asset('storage/'.$logoPath) }}" alt="" class="h-16 rounded ring-1 ring-slate-200">
                            <button type="button" wire:click="removeLogo" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                        @endif
                        <input wire:model="logo" type="file" accept="image/*" class="text-sm">
                    </div>
                    @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-500">{{ __('Branch address and phone are set in Branches.') }}</p>
        @endif

        @if ($tab === 'receipt')
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="form-label">{{ __('Print receipt after sale') }}</label>
                    <select wire:model="values.receipt_print_mode" class="form-input">
                        <option value="on">{{ __('ON - print automatically') }}</option>
                        <option value="ask">{{ __('ASK - show print button') }}</option>
                        <option value="off">{{ __('OFF - do not print') }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Paper size') }}</label>
                    <select wire:model="values.receipt_paper" class="form-input">
                        <option value="80">80 mm ({{ __('thermal') }})</option>
                        <option value="58">58 mm ({{ __('thermal') }})</option>
                        <option value="a4">A4</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">{{ __('Receipt language') }}</label>
                    <select wire:model="values.receipt_language" class="form-input"><option value="en">English</option><option value="bn">বাংলা</option></select>
                </div>
                <div><label class="form-label">{{ __('Header (English)') }}</label><input wire:model="values.receipt_header" type="text" class="form-input"></div>
                <div><label class="form-label">{{ __('Header (Bangla)') }}</label><input wire:model="values.receipt_header_bn" type="text" class="form-input"></div>
                <div><label class="form-label">{{ __('Return policy (days, 0 = none)') }}</label><input wire:model="values.return_policy_days" type="number" min="0" class="form-input">@error('values.return_policy_days') <p class="form-error">{{ $message }}</p> @enderror</div>
                <div class="md:col-span-1"><label class="form-label">{{ __('Footer (English)') }}</label><input wire:model="values.receipt_footer" type="text" class="form-input"></div>
                <div class="md:col-span-2"><label class="form-label">{{ __('Footer (Bangla)') }}</label><input wire:model="values.receipt_footer_bn" type="text" class="form-input"></div>
            </div>
            <div class="space-y-2">
                @foreach ([$toggle('receipt_show_logo', __('Show logo on receipt')), $toggle('cash_drawer_enabled', __('Cash drawer connected to printer'), __('Set "open cash drawer" in the Rongta driver. Adds an "Open cash drawer" button in POS.'))] as $field)
                    <label class="flex items-start gap-2 text-sm"><input wire:model="values.{{ $field['key'] }}" type="checkbox" class="mt-0.5 rounded border-slate-300"><span>{{ $field['label'] }}@if ($field['help'])<span class="block text-xs text-slate-500">{{ $field['help'] }}</span>@endif</span></label>
                @endforeach
            </div>
            <div class="rounded-lg bg-slate-50 p-4 text-xs text-slate-600">
                <p class="font-medium text-slate-800">{{ __('Rongta printer setup (one time)') }}</p>
                <ol class="mt-1 list-decimal space-y-0.5 pl-4">
                    <li>{{ __('Install the Rongta Windows driver and set paper width to 80mm (or 58mm).') }}</li>
                    <li>{{ __('Make Rongta the default printer in Windows.') }}</li>
                    <li>{{ __('For printing without the print dialog, start Chrome with --kiosk-printing (add it to the Chrome shortcut).') }}</li>
                    <li>{{ __('In the print dialog once: set Margins to None and turn off Headers and footers.') }}</li>
                </ol>
            </div>
        @endif

        @if ($tab === 'labels')
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="form-label">{{ __('Print labels on') }}</label>
                    <select wire:model.live="values.label_output" class="form-input">
                        <option value="a4">{{ __('A4 sticker sheet') }}</option>
                        <option value="thermal">{{ __('Thermal label printer') }}</option>
                    </select>
                </div>
                @if ($values['label_output'] === 'thermal')
                    <div>
                        <label class="form-label">{{ __('Label size (width x height mm)') }}</label>
                        <select wire:model="values.label_size" class="form-input">
                            @foreach (['38x25', '40x30', '50x25', '50x30', '60x40'] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="form-label">{{ __('A4 layout (columns x rows)') }}</label>
                        <select wire:model="values.label_a4_layout" class="form-input">
                            @foreach (['3x7', '3x8', '4x10', '5x13'] as $layout)<option value="{{ $layout }}">{{ $layout }} ({{ (int) explode('x', $layout)[0] * (int) explode('x', $layout)[1] }} {{ __('labels') }})</option>@endforeach
                        </select>
                    </div>
                @endif
                <div><label class="form-label">{{ __('Barcode prefix (numbers)') }}</label><input wire:model="values.barcode_prefix" type="text" class="form-input w-28">@error('values.barcode_prefix') <p class="form-error">{{ $message }}</p> @enderror</div>
            </div>
            <div class="space-y-2">
                @foreach ([$toggle('label_show_shop_name', __('Show shop name on label')), $toggle('label_show_price', __('Show price on label'))] as $field)
                    <label class="flex items-center gap-2 text-sm"><input wire:model="values.{{ $field['key'] }}" type="checkbox" class="rounded border-slate-300"> {{ $field['label'] }}</label>
                @endforeach
            </div>
        @endif

        @if ($tab === 'pos')
            <div class="grid gap-4 md:grid-cols-3">
                <label class="flex items-center gap-2 text-sm md:col-span-3"><input wire:model.live="values.vat_enabled" type="checkbox" class="rounded border-slate-300"> <span class="font-medium">{{ __('Charge VAT') }}</span></label>
                @if ($values['vat_enabled'])
                    <div><label class="form-label">{{ __('Default VAT % for new products') }}</label><input wire:model="values.vat_default_rate" type="number" step="0.01" min="0" class="form-input"></div>
                    <div><label class="form-label">{{ __('VAT / BIN number') }}</label><input wire:model="values.vat_bin" type="text" class="form-input"></div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm"><input wire:model="values.vat_inclusive" type="checkbox" class="rounded border-slate-300"> {{ __('Prices already include VAT') }}</label>
                @endif
                <div class="border-t border-slate-200 pt-4 md:col-span-3"></div>
                <div><label class="form-label">{{ __('Max discount for staff (%)') }}</label><input wire:model="values.pos_max_discount_percent" type="number" step="0.5" min="0" max="100" class="form-input"></div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm"><input wire:model="values.pos_allow_price_edit" type="checkbox" class="rounded border-slate-300"> {{ __('Staff can change price in POS') }}</label>
                <label class="flex items-center gap-2 self-end pb-2 text-sm"><input wire:model="values.pos_allow_negative_stock" type="checkbox" class="rounded border-slate-300"> {{ __('Allow selling with zero stock') }}</label>
            </div>
        @endif

        @if ($tab === 'repair')
            <div class="grid gap-4 md:grid-cols-3">
                <div><label class="form-label">{{ __('Default repair warranty (days)') }}</label><input wire:model="values.repair_default_warranty_days" type="number" min="0" class="form-input"></div>
                <div class="md:col-span-2"><label class="form-label">{{ __('Job slip terms (English)') }}</label><textarea wire:model="values.repair_terms" rows="2" class="form-input"></textarea></div>
                <div class="md:col-span-3"><label class="form-label">{{ __('Job slip terms (Bangla)') }}</label><textarea wire:model="values.repair_terms_bn" rows="2" class="form-input"></textarea></div>
                <label class="flex items-center gap-2 text-sm md:col-span-3"><input wire:model.live="values.technicians_enabled" type="checkbox" class="rounded border-slate-300"> <span class="font-medium">{{ __('Use technicians (assign jobs to technicians)') }}</span></label>
            </div>
        @endif

        @if ($tab === 'sms')
            <label class="flex items-center gap-2 text-sm"><input wire:model.live="values.sms_enabled" type="checkbox" class="rounded border-slate-300"> <span class="font-medium">{{ __('Send SMS to customers') }}</span></label>
            @if ($values['sms_enabled'])
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="form-label">{{ __('SMS API URL') }}</label>
                        <input wire:model="values.sms_api_url" type="text" class="form-input font-mono text-xs" placeholder="https://bulksmsbd.net/api/smsapi?api_key={api_key}&type=text&number={phone}&senderid={sender}&message={message}">
                        <p class="mt-1 text-xs text-slate-500">{{ __('Use {phone}, {message}, {api_key} and {sender} where your SMS company needs them.') }}</p>
                        @error('values.sms_api_url') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div><label class="form-label">{{ __('API key') }}</label><input wire:model="values.sms_api_key" type="password" class="form-input" autocomplete="off"></div>
                    <div><label class="form-label">{{ __('Sender ID') }}</label><input wire:model="values.sms_sender_id" type="text" class="form-input"></div>
                    <div class="md:col-span-2">
                        <label class="form-label">{{ __('Send SMS when') }}</label>
                        <div class="flex flex-wrap gap-4 text-sm">
                            @foreach ($smsEvents as $event => $label)
                                <label class="flex items-center gap-2"><input wire:model="values.sms_events" type="checkbox" value="{{ $event }}" class="rounded border-slate-300"> {{ __($label) }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif

        <div class="flex justify-end border-t border-slate-200 pt-4">
            <button type="submit" class="btn-primary">{{ __('Save settings') }}</button>
        </div>
    </form>

    @if ($tab === 'repair' && $values['technicians_enabled'])
        <div class="card p-6">
            <h3 class="mb-3 font-semibold">{{ __('Technicians') }}</h3>
            <form wire:submit="addTechnician" class="mb-3 flex flex-wrap gap-2">
                <input wire:model="technicianName" type="text" placeholder="{{ __('Name') }}" class="form-input w-56">
                <input wire:model="technicianPhone" type="text" placeholder="{{ __('Phone') }}" class="form-input w-44">
                <button type="submit" class="btn-secondary">{{ __('Add') }}</button>
            </form>
            @error('technicianName') <p class="form-error">{{ $message }}</p> @enderror
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($technicians as $technician)
                    <li wire:key="tech-{{ $technician->id }}" @class(['flex justify-between py-2', 'opacity-50' => ! $technician->is_active])>
                        <span>{{ $technician->name }} <span class="text-slate-400">{{ $technician->phone }}</span></span>
                        <button type="button" wire:click="toggleTechnician({{ $technician->id }})" class="text-xs text-slate-500 hover:underline">{{ $technician->is_active ? __('Deactivate') : __('Activate') }}</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($tab === 'sms' && $values['sms_enabled'])
        <form wire:submit="sendTestSms" class="card flex flex-wrap items-end gap-2 p-6">
            <div><label class="form-label">{{ __('Test phone number') }}</label><input wire:model="testPhone" type="text" class="form-input"></div>
            <button type="submit" class="btn-secondary">{{ __('Send test SMS') }}</button>
            <p class="w-full text-xs text-slate-500">{{ __('Save settings first, then send a test.') }}</p>
        </form>
    @endif
</div>
