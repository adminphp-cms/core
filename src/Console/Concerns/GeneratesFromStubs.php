<?php

namespace Gmarineau\AdminPhp\Console\Concerns;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Helpers of the commands writing classes into the application from the package stubs.
 */
trait GeneratesFromStubs
{
    /**
     * Root namespace of the application ("App\"), read from its composer.json.
     */
    protected function appNamespace(): string
    {
        try {
            return $this->laravel->getNamespace();
        } catch (RuntimeException) {
            return 'App\\';
        }
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function fromStub(string $stub, array $replacements): string
    {
        $contents = (string) file_get_contents(__DIR__."/../../../stubs/{$stub}.stub");

        foreach ($replacements as $key => $value) {
            $contents = str_replace("{{ {$key} }}", $value, $contents);
        }

        return $contents;
    }

    protected function relativePath(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}
