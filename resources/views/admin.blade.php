{{-- Blade comments never reach the browser, unlike <!-- HTML comments -->. --}}
<x-layouts::app :title="__('Administrator Workspace')">
    <div class="flex flex-col gap-6">

        {{-- $slot content starts here. No <html>, no sidebar — the layout owns those. --}}
        <div>
            <flux:heading size="xl">{{ __('Administrator Workspace') }}</flux:heading>

            {{-- auth()->user() is available in any view behind the `auth` middleware. --}}
            <flux:text class="mt-1">
                {{ __('Signed in as') }} {{ auth()->user()->name }}
                ({{ auth()->user()->role->value }})
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            {{-- $vendorCount comes from the route's view() data array. --}}
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text>{{ __('Vendors') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $vendorCount }}</flux:heading>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text>{{ __('Users') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $userCount }}</flux:heading>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text>{{ __('Matched') }}</flux:text>
                <flux:heading size="xl" class="mt-1">0</flux:heading>
            </div>
        </div>

        {{-- @if / @else / @endif — Blade's control structures mirror PHP's. --}}
        @if ($vendorCount === 0)
            <flux:callout variant="warning">
                {{ __('No vendors found. Run `php artisan db:seed`.') }}
            </flux:callout>
        @else
            <flux:callout>
                {{ __('Invoice upload and the review queue arrive in Phase 5.') }}
            </flux:callout>
        @endif

    </div>
</x-layouts::app>
