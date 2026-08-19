<?php

namespace App\Providers;

use App\Services\Extraction\FakeInvoiceExtractor;
use App\Services\Extraction\InvoiceExtractor;
use App\Services\Extraction\JsonResponseParser;
use App\Services\Extraction\LlmInvoiceExtractor;
use App\Services\Extraction\PdfTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InvoiceExtractor::class, function ($app): InvoiceExtractor {
            return match (config('extraction.driver')) {
                'llm' => new LlmInvoiceExtractor(
                    $app->make(PdfTextExtractor::class),
                    $app->make(JsonResponseParser::class),
                    (string) config('extraction.base_url'),
                    (string) config('extraction.model'),
                    (int) config('extraction.timeout'),
                    (float) config('extraction.temperature'),
                    (int) config('extraction.max_tokens'),
                    (int) config('extraction.max_payload_chars')
                ),
                'fake' => new FakeInvoiceExtractor,
                default => throw new InvalidArgumentException(
                    'Unknown extraction driver ['.var_export(config('extraction.driver'), true).'].'
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
