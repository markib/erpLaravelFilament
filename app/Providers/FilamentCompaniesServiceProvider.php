<?php

namespace App\Providers;

use App\Actions\FilamentCompanies\AddCompanyEmployee;
use App\Actions\FilamentCompanies\CreateConnectedAccount;
use App\Actions\FilamentCompanies\CreateNewUser;
use App\Actions\FilamentCompanies\CreateUserFromProvider;
use App\Actions\FilamentCompanies\DeleteCompany;
use App\Actions\FilamentCompanies\DeleteUser;
use App\Actions\FilamentCompanies\HandleInvalidState;
use App\Actions\FilamentCompanies\InviteCompanyEmployee;
use App\Actions\FilamentCompanies\RemoveCompanyEmployee;
use App\Actions\FilamentCompanies\ResolveSocialiteUser;
use App\Actions\FilamentCompanies\SetUserPassword;
use App\Actions\FilamentCompanies\UpdateCompanyName;
use App\Actions\FilamentCompanies\UpdateConnectedAccount;
use App\Actions\FilamentCompanies\UpdateUserPassword;
use App\Actions\FilamentCompanies\UpdateUserProfileInformation;
use App\Filament\Company\Clusters\Settings;
use App\Filament\Company\Pages\Accounting\AccountChart;
use App\Filament\Company\Pages\Accounting\Transactions;
use App\Filament\Company\Pages\CreateCompany;
use App\Filament\Company\Pages\ManageCompany;
use App\Filament\Company\Pages\Reports;
use App\Filament\Company\Pages\Service\ConnectedAccount;
use App\Filament\Company\Pages\Service\LiveCurrency;
use App\Filament\Company\Resources\AdjustmentResource;
use App\Filament\Company\Resources\Banking\AccountResource;
use App\Filament\Company\Resources\Common\OfferingResource;
use App\Filament\Company\Resources\Core\DepartmentResource;
use App\Filament\Company\Resources\CustomerResource;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Filament\Company\Resources\Purchases\OrderResource;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\SupplierResource;
use App\Filament\Company\Widgets\OrderChart;
use App\Filament\Company\Widgets\SalesChart;
use App\Filament\Components\PanelShiftDropdown;
use App\Filament\User\Clusters\Account;
use App\Filament\Widgets\EnhancedStatsOverviewWidget;
use App\Http\Middleware\ConfigureCurrentCompany;
use App\Livewire\UpdatePassword;
use App\Livewire\UpdateProfileInformation;
use App\Models\Company;
use App\Support\FilamentComponentConfigurator;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Exception;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Wallo\FilamentCompanies\Actions\GenerateRedirectForProvider;
use Wallo\FilamentCompanies\Enums\Feature;
use Wallo\FilamentCompanies\Enums\Provider;
use Wallo\FilamentCompanies\FilamentCompanies;
use Wallo\FilamentCompanies\Pages\Auth\Login;
use Wallo\FilamentCompanies\Pages\Auth\Register;

class FilamentCompaniesServiceProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {

        return $panel
            ->default()
            ->id('company')
            ->path('company')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->tenantMenu(false)
            ->plugin(
                FilamentCompanies::make()
                    ->userPanel('user')
                    ->switchCurrentCompany()
                    ->updateProfileInformation(component: UpdateProfileInformation::class)
                    ->updatePasswords(component: UpdatePassword::class)
                    ->setPasswords()
                    ->connectedAccounts()
                    ->manageBrowserSessions()
                    ->accountDeletion()
                    ->profilePhotos()
                    ->api()
                    ->companies(invitations: true)
                    ->termsAndPrivacyPolicy()
                    ->notifications()
                    ->modals()
                    ->socialite(
                        providers: [Provider::Github],
                        features: [Feature::RememberSession, Feature::ProviderAvatars],
                    ),
            )
            ->plugin(
                PanelShiftDropdown::make()
                    ->logoutItem()
                    ->companySettings()
                    ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                        return $builder
                            ->items(Account::getNavigationItems());
                    }),
                FilamentShieldPlugin::make(),
            )
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Gray,
            ])
            // ->userMenuItems([
            //     'profile' => MenuItem::make()
            //         ->label('Profile')
            //         ->icon('heroicon-o-user-circle'),
            //         // ->url(static fn() => url(Profile::getUrl())),
            //     MenuItem::make()
            //         ->label('Company')
            //         ->icon('heroicon-o-building-office')
            //         ->url(static fn() => url(Pages\Dashboard::getUrl(panel: 'company', tenant: Auth::user()->personalCompany()))),
            // ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {

                return $builder
                    ->items([
                        ...Dashboard::getNavigationItems(),
                        ...Reports::getNavigationItems(),
                        ...Settings::getNavigationItems(),
                        ...OfferingResource::getNavigationItems(),
                    ])
                    ->groups([
                        NavigationGroup::make('Accounting')
                            ->localizeLabel()
                            ->icon('heroicon-o-clipboard-document-list')
                            ->extraSidebarAttributes(['class' => 'es-sidebar-group'])
                            ->items([
                                ...AccountChart::getNavigationItems(),
                                ...Transactions::getNavigationItems(),
                                ...AdjustmentResource::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Banking')
                            ->localizeLabel()
                            ->icon('heroicon-o-building-library')
                            ->items(AccountResource::getNavigationItems()),
                        NavigationGroup::make('HR')
                            ->icon('heroicon-o-user-group')
                            ->items(DepartmentResource::getNavigationItems()),
                        NavigationGroup::make('Sales')
                            ->label('Sales')
                            ->icon('heroicon-o-currency-dollar')
                            ->items([
                                ...InvoiceResource::getNavigationItems(),
                                ...EstimateResource::getNavigationItems(),

                            ]),
                        NavigationGroup::make('Purchases')
                            ->label('Purchases')
                            ->icon('heroicon-o-shopping-cart')
                            ->items([
                                ...BillResource::getNavigationItems(),
                                ...OrderResource::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Manage Products')

                            ->localizeLabel()

                            ->icon('heroicon-o-rectangle-stack')

                            ->items([

                                // Only define items that should be visible based on their authorization logic

                                NavigationItem::make('Categories')

                                    ->url(route(
                                        'filament.company.resources.categories.index',
                                        ['tenant' => auth()->user()->currentCompany->id]
                                    ))

                                    ->visible(fn () => auth()->user()->hasRole('admin')), // Visible to admin and product manager

                                NavigationItem::make('Products')
                                    ->url(route(
                                        'filament.company.resources.products.index',
                                        ['tenant' => auth()->user()->currentCompany->id]
                                    ))

                                    ->visible(fn () => auth()->user()->hasRole('admin')), // Same visibility

                            ]),

                        NavigationGroup::make('Services')
                            ->localizeLabel()
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->items([
                                ...ConnectedAccount::getNavigationItems(),
                                ...LiveCurrency::getNavigationItems(),
                            ]),

                        NavigationGroup::make('Roles & Permissions') // Parent Group Menu
                            ->icon('heroicon-o-lock-closed') // Add an icon for the group
                            ->items([ // Define the items within the group
                                NavigationItem::make('List Roles')
                                    ->url(route('filament.company.resources.shield.roles.index', [
                                        'tenant' => auth()->user()->currentCompany->id ?? 1,
                                    ])),
                                // ->icon('heroicon-o-list-bullet'),

                                NavigationItem::make('Create Role')
                                    ->url(route('filament.company.resources.shield.roles.create', [
                                        'tenant' => auth()->user()->currentCompany->id ?? 1,
                                    ])),
                                // ->icon('heroicon-o-plus'),
                            ]),
                        NavigationGroup::make('Parties')
                            ->localizeLabel()
                            ->icon('heroicon-o-clipboard-document-list')
                            ->extraSidebarAttributes(['class' => 'es-sidebar-group'])
                            ->items([
                                ...CustomerResource::getNavigationItems(),
                                ...SupplierResource::getNavigationItems(),
                            ]),
                    ]);
            })
            ->viteTheme('resources/css/filament/company/theme.css')
        // ->brandLogo(static fn () => view('components.icons.logo'))
            ->brandLogo(asset('storage/logos/company/1_alx1.png'))
            ->tenant(Company::class, ownershipRelationship: 'company')
            ->tenantProfile(ManageCompany::class)
            ->tenantRegistration(CreateCompany::class)
            ->discoverResources(in: app_path('Filament/Company/Resources'), for: 'App\\Filament\\Company\\Resources')
            ->discoverPages(in: app_path('Filament/Company/Pages'), for: 'App\\Filament\\Company\\Pages')
            ->discoverClusters(in: app_path('Filament/Company/Clusters'), for: 'App\\Filament\\Company\\Clusters')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->authGuard('web')
            ->discoverWidgets(in: app_path('Filament/Company/Widgets'), for: 'App\\Filament\\Company\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
                EnhancedStatsOverviewWidget::class,
                OrderChart::class,
                SalesChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->tenantMiddleware([
                ConfigureCurrentCompany::class,
                \BezhanSalleh\FilamentShield\Middleware\SyncShieldTenant::class,
            ], isPersistent: true)
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();
        $this->configureDefaults();

        FilamentCompanies::createUsersUsing(CreateNewUser::class);
        FilamentCompanies::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        FilamentCompanies::updateUserPasswordsUsing(UpdateUserPassword::class);

        FilamentCompanies::createCompaniesUsing(CreateCompany::class);
        FilamentCompanies::updateCompanyNamesUsing(UpdateCompanyName::class);
        FilamentCompanies::addCompanyEmployeesUsing(AddCompanyEmployee::class);
        FilamentCompanies::inviteCompanyEmployeesUsing(InviteCompanyEmployee::class);
        FilamentCompanies::removeCompanyEmployeesUsing(RemoveCompanyEmployee::class);
        FilamentCompanies::deleteCompaniesUsing(DeleteCompany::class);
        FilamentCompanies::deleteUsersUsing(DeleteUser::class);

        FilamentCompanies::resolvesSocialiteUsersUsing(ResolveSocialiteUser::class);
        FilamentCompanies::createUsersFromProviderUsing(CreateUserFromProvider::class);
        FilamentCompanies::createConnectedAccountsUsing(CreateConnectedAccount::class);
        FilamentCompanies::updateConnectedAccountsUsing(UpdateConnectedAccount::class);
        FilamentCompanies::setUserPasswordsUsing(SetUserPassword::class);
        FilamentCompanies::handlesInvalidStateUsing(HandleInvalidState::class);
        FilamentCompanies::generatesProvidersRedirectsUsing(GenerateRedirectForProvider::class);

        // app(Panel::class)
        //     ->id('company') // Add a unique ID for the panel
        //     ->path('company') // Define the path (URL segment) for the panel
        //     ->plugins([
        //         FilamentShieldPlugin::make(),
        //     ]);

    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        FilamentCompanies::defaultApiTokenPermissions(['read']);

        FilamentCompanies::role('admin', 'Administrator', [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Administrator users can perform any action.');

        FilamentCompanies::role('editor', 'Editor', [
            'read',
            'create',
            'update',
        ])->description('Editor users have the ability to read, create, and update.');

        FilamentCompanies::role('viewer', 'Viewer', [
            'read',
        ])->description('Viewer users can only read data.');
    }

    /**
     * Configure the default settings for Filament.
     */
    protected function configureDefaults(): void
    {
        $this->configureSelect();

        Actions\CreateAction::configureUsing(static fn (Actions\CreateAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Actions\EditAction::configureUsing(static fn (Actions\EditAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Tables\Actions\EditAction::configureUsing(static fn (Tables\Actions\EditAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Tables\Actions\CreateAction::configureUsing(static fn (Tables\Actions\CreateAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Forms\Components\DateTimePicker::configureUsing(static function (Forms\Components\DateTimePicker $component) {
            $component->native(false);
        });
    }

    /**
     * Configure the default settings for the Select component.
     */
    protected function configureSelect(): void
    {
        Select::configureUsing(function (Select $select): void {
            $isSelectable = fn (): bool => ! $this->hasRequiredRule($select);

            $select
                ->native(false)
                ->selectablePlaceholder($isSelectable);
        }, isImportant: true);
    }

    protected function hasRequiredRule(Select $component): bool
    {
        $rules = $component->getValidationRules();

        return in_array('required', $rules, true);
    }
}
