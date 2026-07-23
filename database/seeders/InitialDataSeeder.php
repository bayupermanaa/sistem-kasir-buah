<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outlet = Outlet::create([
            'name' => 'Toko Buah Teras Fatimah',
            'address' => null,
            'phone' => null,
        ]);

        User::create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Cashier',
            'username' => 'cashier',
            'email' => 'cashier@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }
}
