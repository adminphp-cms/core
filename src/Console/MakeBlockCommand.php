<?php

namespace AdminPhp\Console;

use AdminPhp\AdminPhp;
use AdminPhp\Console\Concerns\GeneratesFromStubs;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:make:block')]
class MakeBlockCommand extends Command
{
    use GeneratesFromStubs;

    protected $signature = 'adminphp:make:block
        {name : Name of the block, e.g. Gallery}
        {--force : Overwrite the block if it exists}';

    protected $description = 'Create a block for the Blocks fields of the admin';

    public function handle(Filesystem $files, AdminPhp $adminPhp): int
    {
        $name = Str::studly(Str::replaceEnd('Block', '', (string) $this->argument('name')));

        if ($name === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('The name must be a class name, e.g. Gallery.');

            return self::FAILURE;
        }

        $class = "{$name}Block";
        $path = app_path("AdminPhp/Blocks/{$class}.php");
        $relativePath = $this->relativePath($path);

        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("[{$relativePath}] already exists: use --force to overwrite it.");

            return self::FAILURE;
        }

        $replacements = [
            'namespace' => $this->appNamespace().'AdminPhp\Blocks',
            'class' => $class,
            'title' => Str::headline($name),
            'translatable' => count($adminPhp->locales()) > 1 ? '->translatable()' : '',
        ];

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->fromStub('block', $replacements));

        $type = Str::kebab($name);

        $this->components->twoColumnDetail($relativePath, '<fg=green;options=bold>CREATED</>');
        $this->components->info("Block [{$class}] created, stored with the type \"{$type}\".");
        $this->components->bulletList([
            "Allow it in a Blocks field: Blocks::make('content')->allow([{$class}::class])",
            "Render it on the site where \$block->type is \"{$type}\", reading its fields with \$block->translated('text')",
        ]);

        return self::SUCCESS;
    }
}
