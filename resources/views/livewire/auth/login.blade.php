<div class="w-full max-w-sm">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Welcome back') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('Log in to continue') }}</p>

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <label for="username" class="form-label">{{ __('Username or email') }}</label>
            <input wire:model="username" id="username" type="text" autocomplete="username" autofocus class="form-input py-2.5">
            @error('username') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <div class="relative">
                <input wire:model="password" id="password" x-bind:type="show ? 'text' : 'password'" type="password" autocomplete="current-password" class="form-input py-2.5 pr-10">
                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" aria-label="{{ __('Show password') }}">
                    <span x-show="!show"><x-icon name="eye" class="size-4" /></span>
                    <span x-show="show" x-cloak><x-icon name="eye-off" class="size-4" /></span>
                </button>
            </div>
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-brand-600">
            {{ __('Remember me') }}
        </label>

        <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full py-2.5">
            <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
            <span wire:loading wire:target="login">{{ __('Please wait...') }}</span>
        </button>
    </form>
</div>
