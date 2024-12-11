<?php

namespace App\Services;

use App\Enums\Accounting\AccountType;
use App\Enums\Banking\BankAccountType;
use App\Models\Accounting\AccountSubtype;
use App\Models\Banking\BankAccount;
use App\Models\Company;
use App\Utilities\Currency\CurrencyAccessor;
use Exception;

class ChartOfAccountsService
{
    public function createChartOfAccounts(Company $company): void
    {
        $chartOfAccounts = config('chart-of-accounts.default');

        foreach ($chartOfAccounts as $type => $subtypes) {
            foreach ($subtypes as $subtypeName => $subtypeConfig) {
                $subtype = $company->accountSubtypes()
                    ->createQuietly([
                        'multi_currency' => $subtypeConfig['multi_currency'] ?? false,
                        'inverse_cash_flow' => $subtypeConfig['inverse_cash_flow'] ?? false,
                        'category' => AccountType::from($type)->getCategory()->value,
                        'type' => $type,
                        'name' => $subtypeName,
                        'description' => $subtypeConfig['description'] ?? 'No description available.',
                    ]);

                try {
                    $this->createDefaultAccounts($company, $subtype, $subtypeConfig);
                } catch (Exception $e) {
                    // Log the error
                    logger()->alert('Failed to create a company with its defaults, blocking critical business functionality.', [
                        'error' => $e->getMessage(),
                        'userId' => $company->owner->id,
                        'companyId' => $company->id,
                    ]);

                    throw $e;
                }
            }
        }
    }

    private function createDefaultAccounts(Company $company, AccountSubtype $subtype, array $subtypeConfig): void
    {
        if (isset($subtypeConfig['accounts']) && is_array($subtypeConfig['accounts'])) {
            $baseCode = $subtypeConfig['base_code'];

            $defaultCurrencyCode = CurrencyAccessor::getDefaultCurrency();

            if (empty($defaultCurrencyCode)) {
                throw new Exception('No default currency available for creating accounts.');
            }

            foreach ($subtypeConfig['accounts'] as $accountName => $accountDetails) {
                $bankAccount = null;

                if ($subtypeConfig['multi_currency'] && isset($subtypeConfig['bank_account_type'])) {
                    $bankAccount = $this->createBankAccountForMultiCurrency($company, $subtypeConfig['bank_account_type']);
                }

                $company->accounts()->createQuietly([
                    'bank_account_id' => $bankAccount?->id,
                    'subtype_id' => $subtype->id,
                    'category' => $subtype->type->getCategory()->value,
                    'type' => $subtype->type->value,
                    'code' => $baseCode++,
                    'name' => $accountName,
                    'currency_code' => $defaultCurrencyCode,
                    'description' => $accountDetails['description'] ?? 'No description available.',
                    'default' => true,
                    'created_by' => $company->owner->id,
                    'updated_by' => $company->owner->id,
                ]);
            }
        }
    }

    private function createBankAccountForMultiCurrency(Company $company, string $bankAccountType): BankAccount
    {
        $noDefaultBankAccount = $company->bankAccounts()->where('enabled', true)->doesntExist();

        return $company->bankAccounts()->createQuietly([
            'type' => BankAccountType::from($bankAccountType) ?? BankAccountType::Other,
            'enabled' => $noDefaultBankAccount,
            'created_by' => $company->owner->id,
            'updated_by' => $company->owner->id,
        ]);
    }
}
