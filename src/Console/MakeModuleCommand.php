<?php

namespace Gmarineau\AdminPhp\Console;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Console\Concerns\GeneratesFromStubs;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'adminphp:make:module')]
class MakeModuleCommand extends Command
{
    use GeneratesFromStubs;

    protected $signature = 'adminphp:make:module
        {name : Name of the model, e.g. Project}
        {--publishable : Contents with their own page on the site: publication, slug and preview}
        {--trashable : Deleted items go to a trash first}
        {--sortable : Items are ordered by hand}
        {--nested : Items can be nested under others (implies --sortable)}
        {--blocks : The content is made of blocks}
        {--force : Overwrite the model and the module if they exist}';

    protected $description = 'Create a module of the admin with its model and migration';

    /**
     * Features asked when none is given as an option, in an interactive terminal.
     *
     * @var array<string, string>
     */
    protected const FEATURES = [
        'publishable' => 'Publication (brouillon, en ligne, programmation) et adresse',
        'trashable' => 'Corbeille',
        'sortable' => 'Ordre manuel (glisser-déposer)',
        'nested' => 'Imbrication (sous-éléments)',
        'blocks' => 'Contenu en blocs',
    ];

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(AdminPhp $adminPhp): int
    {
        $model = Str::studly(Str::replaceEnd('Module', '', (string) $this->argument('name')));

        if ($model === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            $this->components->error('The name must be a class name, e.g. Project.');

            return self::FAILURE;
        }

        $modelPath = app_path("Models/{$model}.php");
        $modulePath = app_path("AdminPhp/Modules/{$model}Module.php");

        if (! $this->option('force')) {
            foreach ([$modelPath, $modulePath] as $path) {
                if ($this->files->exists($path)) {
                    $this->components->error(sprintf('[%s] already exists: use --force to overwrite it.', $this->relativePath($path)));

                    return self::FAILURE;
                }
            }
        }

        $features = $this->features();
        $isTranslatable = count($adminPhp->locales()) > 1;
        $table = Str::snake(Str::pluralStudly($model));
        $appNamespace = $this->appNamespace();

        $this->write($modelPath, $this->model($appNamespace, $model, $features, $isTranslatable));
        $this->writeMigration($table, $this->migration($table, $features, $isTranslatable));
        $this->write($modulePath, $this->module($appNamespace, $model, $features, $isTranslatable));

        $this->components->info("Module [{$model}Module] created.");
        $this->components->bulletList([
            "Register it, e.g. in AppServiceProvider::boot(): app(\\Gmarineau\\AdminPhp\\AdminPhp::class)->registerModules([\\{$appNamespace}AdminPhp\\Modules\\{$model}Module::class]);",
            'Add your fields to the migration and to the form of the module, then run: php artisan migrate',
            ...(in_array('blocks', $features, true) ? ['Allow your blocks in the Blocks field of the form (php artisan adminphp:make:block)'] : []),
            ...(in_array('publishable', $features, true) ? ['Show the items on the site with the published() scope, and set $previewView to preview them'] : []),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function features(): array
    {
        $features = array_values(array_filter(array_keys(self::FEATURES), fn (string $feature): bool => (bool) $this->option($feature)));

        if ($features === [] && $this->input->isInteractive()) {
            $features = multiselect(label: 'Que doit savoir faire ce module ?', options: self::FEATURES, hint: 'Espace pour cocher, Entrée pour valider.');
        }

        if (in_array('nested', $features, true) && ! in_array('sortable', $features, true)) {
            $features[] = 'sortable';
        }

        return $features;
    }

    protected function write(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
        $this->components->twoColumnDetail($this->relativePath($path), '<fg=green;options=bold>CREATED</>');
    }

    /**
     * Writes the migration unless one already creates the table: it may have run already.
     */
    protected function writeMigration(string $table, string $contents): void
    {
        $name = "create_{$table}_table.php";
        $directory = database_path('migrations');

        if ($this->files->glob("{$directory}/*_{$name}") !== []) {
            $this->components->twoColumnDetail("Migration [{$name}]", '<fg=yellow;options=bold>ALREADY EXISTS</>');

            return;
        }

        $this->write("{$directory}/".Carbon::now()->format('Y_m_d_His')."_{$name}", $contents);
    }

    /**
     * @param  array<int, string>  $features
     */
    protected function model(string $appNamespace, string $model, array $features, bool $isTranslatable): string
    {
        $traits = [
            'blocks' => 'Gmarineau\AdminPhp\Concerns\HasBlocks',
            'nested' => 'Gmarineau\AdminPhp\Concerns\HasNesting',
            'sortable' => 'Gmarineau\AdminPhp\Concerns\HasPosition',
            'publishable' => 'Gmarineau\AdminPhp\Concerns\HasPublication',
            'translatable' => 'Spatie\Translatable\HasTranslations',
            'ulids' => 'Illuminate\Database\Eloquent\Concerns\HasUlids',
            'trashable' => 'Illuminate\Database\Eloquent\SoftDeletes',
        ];

        $enabled = [...$features, 'ulids', ...($isTranslatable ? ['translatable'] : [])];
        $traits = array_intersect_key($traits, array_flip($enabled));
        $imports = [...array_values($traits), 'Illuminate\Database\Eloquent\Model'];
        sort($imports);

        $traitNames = array_map('class_basename', array_values($traits));
        sort($traitNames);

        $body = $isTranslatable ? "\n    /**\n     * @var array<int, string>\n     */\n    public array \$translatable = ['title'];\n" : '';

        return $this->fromStub('model', [
            'namespace' => "{$appNamespace}Models",
            'imports' => implode("\n", array_map(fn (string $import): string => "use {$import};", $imports)),
            'class' => $model,
            'traits' => implode(', ', $traitNames),
            'body' => $body,
        ]);
    }

    /**
     * @param  array<int, string>  $features
     */
    protected function migration(string $table, array $features, bool $isTranslatable): string
    {
        $has = fn (string $feature): bool => in_array($feature, $features, true);

        $columns = array_filter([
            "\$table->ulid('id')->primary();",
            $isTranslatable ? "\$table->jsonb('title');" : "\$table->string('title');",
            $has('publishable') ? "\$table->string('slug')->unique();" : null,
            $has('publishable') ? '$table->publication();' : null,
            $has('sortable') ? '$table->position();' : null,
            $has('nested') ? '$table->nesting();' : null,
            '$table->timestamps();',
            $has('trashable') ? '$table->softDeletes();' : null,
        ]);

        return $this->fromStub('migration', [
            'table' => $table,
            'columns' => implode("\n", array_map(fn (string $column): string => "            {$column}", $columns)),
        ]);
    }

    /**
     * @param  array<int, string>  $features
     */
    protected function module(string $appNamespace, string $model, array $features, bool $isTranslatable): string
    {
        $has = fn (string $feature): bool => in_array($feature, $features, true);

        $imports = array_filter([
            "{$appNamespace}Models\\{$model}",
            $has('blocks') ? 'Gmarineau\AdminPhp\Fields\Blocks' : null,
            $has('publishable') ? 'Gmarineau\AdminPhp\Fields\Slug' : null,
            'Gmarineau\AdminPhp\Fields\Text',
            'Gmarineau\AdminPhp\Modules\Module',
        ]);
        sort($imports);

        $fields = array_filter([
            "Text::make('title')->label('Titre')".($isTranslatable ? '->translatable()' : '').'->required(),',
            $has('publishable') ? "Slug::make('slug')->label('Adresse')->from('title')->prefix('/".Str::kebab(Str::pluralStudly($model))."/'),"
                : null,
            $has('blocks') ? "Blocks::make('content')->label('Contenu')->allow([]),"
                : null,
        ]);

        return $this->fromStub('module', [
            'namespace' => "{$appNamespace}AdminPhp\\Modules",
            'imports' => implode("\n", array_map(fn (string $import): string => "use {$import};", $imports)),
            'class' => "{$model}Module",
            'model' => $model,
            'properties' => $has('nested') ? "\n    protected int \$nestingDepth = 1;\n" : '',
            'fields' => implode("\n", array_map(fn (string $field): string => "            {$field}", $fields)),
        ]);
    }
}
