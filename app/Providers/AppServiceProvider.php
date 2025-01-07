<?php

namespace App\Providers;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Models\Product\Product;
use App\Services\DateRangeService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Livewire\Notifications;
use Filament\Support\Assets\Js;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DateRangeService::class);
        $this->app->singleton(LoginResponse::class, \App\Http\Responses\LoginResponse::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notifications::alignment(Alignment::Center);

        FilamentAsset::register([
            Js::make('TopNavigation', __DIR__ . '/../../resources/js/TopNavigation.js'),
        ]);

        Relation::morphMap([
            'invoice' => Invoice::class,
            'bill' => Bill::class,
            'bankAccount' => BankAccount::class,
            'journal_entry' => JournalEntry::class,
            'offering' => Offering::class,
            'product' => Product::class,
            'estimate' => Estimate::class,

        ]);
    }
}
