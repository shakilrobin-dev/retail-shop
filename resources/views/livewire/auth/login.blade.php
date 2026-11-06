<div class="w-full max-w-sm">
    <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-center text-2xl font-semibold">{{ app(\App\Services\Settings::class)->get('shop_name') }}</h1>
        <p class="mt-1 text-center text-sm text-slate-500">{{ __('Log in to continue') }}</p>

        <form wire:submit="login" class="mt-8 space-y-5">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700">{{ __('Username or email') }}</label>
                <input wire:model="username" id="username" type="text" autocomplete="username" autofocus
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                <input wire:model="password" id="password" type="password" autocomplete="current-password"
                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-indigo-600">
                {{ __('Remember me') }}
            </label>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
                <span wire:loading wire:target="login">{{ __('Please wait...') }}</span>
            </button>
        </form>
    </div>
</div>
