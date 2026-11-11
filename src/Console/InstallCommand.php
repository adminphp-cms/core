<?php

namespace Gmarineau\AdminPhp\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:install')]
class InstallCommand extends Command
{
    protected $signature = 'adminphp:install
        {--force : Overwrite the already published config}
        {--migrate : Run the migrations without asking}';

    protected $description = 'Install AdminPhp: publish its config, migrations and admin assets';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Installing AdminPhp.');

        $this->call('vendor:publish', [
            '--tag' => 'adminphp-config',
            '--force' => $this->option('force'),
        ]);

        $this->publishMigrations($files);

        $this->call('vendor:publish', ['--tag' => 'adminphp-assets', '--force' => true]);

        if ($this->option('migrate') || $this->confirm('Run the database migrations now?', true)) {
            $this->call('migrate');
        }

        $this->components->info('AdminPhp is installed.');

        $this->components->bulletList([
            'Register your modules, e.g. in AppServiceProvider::boot(): app(\Gmarineau\AdminPhp\AdminPhp::class)->registerModules([ArticleModule::class]);',
            'Create an admin user: php artisan adminphp:user',
            sprintf('Open %s', url(config('adminphp.path'))),
        ]);

        return self::SUCCESS;
    }

    /**
     * Copies the package migrations with a timestamp prefix, skipping the ones already
     * published so the command can be run again safely (e.g. after a package update).
     */
    protected function publishMigrations(Filesystem $files): void
    {
        $targetDirectory = database_path('migrations');
        $files->ensureDirectoryExists($targetDirectory);

        $timestamp = Carbon::now();

        $migrations = collect($files->files(__DIR__.'/../../database/migrations'))
            ->sortBy(fn (SplFileInfo $file): string => $file->getFilename());

        foreach ($migrations as $migration) {
            $name = $migration->getFilename();

            $isAlreadyPublished = $files->exists("{$targetDirectory}/{$name}")
                || $files->glob("{$targetDirectory}/*_{$name}") !== [];

            if ($isAlreadyPublished) {
                $this->components->twoColumnDetail("Migration [{$name}]", '<fg=yellow;options=bold>ALREADY PUBLISHED</>');

                continue;
            }

            $targetName = $timestamp->format('Y_m_d_His').'_'.$name;
            $files->copy($migration->getPathname(), "{$targetDirectory}/{$targetName}");
            $timestamp = $timestamp->addSecond();

            $this->components->twoColumnDetail("Migration [{$targetName}]", '<fg=green;options=bold>PUBLISHED</>');
        }
    }
}
