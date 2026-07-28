<?php

namespace App\Services;

use App\Models\Outlet;

class SingleOutletService
{
    public function getDefaultOutlet(): Outlet
    {
        return Outlet::firstOrCreate([
            'name' => Outlet::DEFAULT_NAME,
        ], [
            'address' => null,
            'phone' => null,
        ]);
    }

    public function getDefaultOutletId(): int
    {
        return (int) $this->getDefaultOutlet()->id;
    }

    public function getDefaultOutletName(): string
    {
        return $this->getDefaultOutlet()->name;
    }
}
