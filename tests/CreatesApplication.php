<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        $compiledViewPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sistem-kasir-buah-views';

        if (! is_dir($compiledViewPath)) {
            mkdir($compiledViewPath, 0777, true);
        }

        $app['config']->set('view.compiled', $compiledViewPath);

        return $app;
    }
}
