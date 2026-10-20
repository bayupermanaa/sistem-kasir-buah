<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InitialDataSeeder::class);
        $this->call(CatalogSeeder::class);
        $this->call(StockSeeder::class);
        $this->call(AccountingAccountSeeder::class);
    }
}
