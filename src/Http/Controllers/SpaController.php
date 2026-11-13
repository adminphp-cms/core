<?php

namespace AdminPhp\Http\Controllers;

use AdminPhp\AdminPhp;
use Illuminate\Contracts\View\View;

/**
 * Serves the single Blade page that boots the Vue admin.
 */
class SpaController
{
    public function __invoke(AdminPhp $adminPhp): View
    {
        $basePath = '/'.trim(config('adminphp.path'), '/');

        return view('adminphp::app', [
            'config' => [
                'basePath' => $basePath,
                'apiUrl' => rtrim($basePath, '/').'/api',
                'locales' => $adminPhp->locales(),
                'defaultLocale' => $adminPhp->defaultLocale(),
                'siteUrl' => config('adminphp.site_url') ?: url('/'),
                'hasSettings' => $adminPhp->settingsForm() !== null,
            ],
        ]);
    }
}
