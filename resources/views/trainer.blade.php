{{-- Blade comments never reach the browser, unlike <!-- HTML comments -->. --}}
<x-layouts::app :title="__('AI Trainer Workspace')">
    <div class="flex flex-col gap-6">

        {{-- $slot content starts here. No <html>, no sidebar — the layout owns those. --}}
        <div>
            <flux:heading size="xl">{{ __('AI Trainer Workspace') }}</flux:heading>

            {{-- auth()->user() is available in any view behind the `auth` middleware. --}}
            <flux:text class="mt-1">
                {{ __('Signed in as') }} {{ auth()->user()->name }}
                ({{ auth()->user()->role->value }})
            </flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            {{-- $extractionRunCount comes from the route's view() data array. --}}
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text>{{ __('Extraction runs') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $extractionRunCount }}</flux:heading>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:text>{{ __('Matched') }}</flux:text>
                <flux:heading size="xl" class="mt-1">0</flux:heading>
            </div>
        </div>

        {{-- @if / @else / @endif — Blade's control structures mirror PHP's. --}}
        @if ($extractionRunCount === 0)
            <flux:callout variant="warning">
                {{ __('No extraction runs found. Run `php artisan db:seed`.') }}
            </flux:callout>
        @else
            <flux:callout>
                {{ __('Invoice upload and the review queue arrive in Phase 5.') }}
            </flux:callout>
        @endif

    </div>
</x-layouts::app>
