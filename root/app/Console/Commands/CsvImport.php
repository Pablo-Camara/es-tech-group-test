<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Price;
use App\Models\User;
use Illuminate\Console\Command;
use League\Csv\Reader;

class CsvImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:csv';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import provided csv files with refs to products, accounts and users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = storage_path('app/import.csv');
        $csvReader = Reader::createFromPath($filePath);
        $csvReader->setHeaderOffset(0);
        $records = $csvReader->getRecords();

        foreach ($records as $record) {
            foreach($record as $key => $value) {
                if (empty($value)) {
                    $record[$key] = null;
                }
            }

            // user to insert
            if (!empty($record['user_ref'])) {
                User::updateOrInsert(
                    ['ref' => $record['user_ref']]
                );
            }
            //---


            // account to insert
            if (!empty($record['account_ref'])) {
                Account::updateOrInsert(
                    ['ref' => $record['account_ref']]
                );
            }
            //---


            // add users to the accounts they belong
            if (
                !empty($record['account_ref'])
                &&
                !empty($record['user_ref'])
            ) {
                AccountUser::updateOrInsert(
                    [
                        'account_ref' => $record['account_ref'],
                        'user_ref' => $record['user_ref']
                    ]
                );
            }

            // price to insert
            Price::updateOrInsert(
                [
                    'sku' => $record['sku'],
                    'account_ref' => $record['account_ref'],
                    'user_ref' => $record['user_ref'],
                ],
                $record
            );
            //---
        }
    }
}
