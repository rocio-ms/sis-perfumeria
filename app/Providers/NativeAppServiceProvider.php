<?php

namespace App\Providers;

use Native\Desktop\Facades\App;
use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->url(config('app.url'))
            ->width(1200)
            ->height(800)
            ->webPreferences([
                'webSecurity' => false,
                'allowRunningInsecureContent' => true,
            ]);

        App::focus();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [];
    }
}
