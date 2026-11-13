<?php

namespace Gmarineau\AdminPhp\Tests\Console;

use Gmarineau\AdminPhp\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Points the app, config, database and public paths to a temporary skeleton, set before the
 * providers boot so the publishable paths of the package resolve inside it.
 */
abstract class InstallCommandTestCase extends TestCase
{
    protected string $skeletonPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->skeletonPath = sys_get_temp_dir().'/adminphp-install-'.Str::random(8);

        foreach (['app', 'config', 'database/migrations', 'public'] as $directory) {
            (new Filesystem)->ensureDirectoryExists("{$this->skeletonPath}/{$directory}");
        }

        $app->useAppPath("{$this->skeletonPath}/app");
        $app->useConfigPath("{$this->skeletonPath}/config");
        $app->useDatabasePath("{$this->skeletonPath}/database");
        $app->usePublicPath("{$this->skeletonPath}/public");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->skeletonPath);

        parent::tearDown();
    }
}
