<?php

use AdminPhp\AdminPhp;
use App\AdminPhp\Blocks\PhotoGalleryBlock;
use App\AdminPhp\Modules\MenuEntryModule;
use App\AdminPhp\Modules\ProjectModule;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Loads the generated model and module, and runs the generated migration.
 */
function loadGeneratedModule(string $model, string $table): void
{
    require_once app_path("Models/{$model}.php");
    require_once app_path("AdminPhp/Modules/{$model}Module.php");

    (require File::glob(database_path("migrations/*_create_{$table}_table.php"))[0])->up();
}

it('creates a working module with its model and migration', function () {
    $this->artisan('adminphp:make:module', ['name' => 'Project', '--publishable' => true, '--trashable' => true, '--blocks' => true])
        ->expectsOutputToContain('Module [ProjectModule] created.')
        ->assertSuccessful();

    loadGeneratedModule('Project', 'projects');
    app(AdminPhp::class)->registerModules([ProjectModule::class]);
    actingAsAdmin();

    $id = $this->postJson('/admin/api/modules/project/items', ['title' => ['fr' => 'Grange']])
        ->assertCreated()
        ->assertJsonPath('data.values.slug', 'grange')
        ->json('data.id');
    $this->postJson("/admin/api/modules/project/items/{$id}/publish")->assertOk();
    $this->deleteJson("/admin/api/modules/project/items/{$id}")->assertNoContent();

    expect(Project::onlyTrashed()->find($id)?->isPublished())->toBeTrue()
        ->and(File::get(app_path('AdminPhp/Modules/ProjectModule.php')))->toContain("Blocks::make('content')");
});

it('creates a nested module ordered by hand', function () {
    $this->artisan('adminphp:make:module', ['name' => 'MenuEntry', '--nested' => true])->assertSuccessful();

    loadGeneratedModule('MenuEntry', 'menu_entries');
    $module = new MenuEntryModule;

    expect($module->nestingDepth())->toBe(1)
        ->and($module->isSortable())->toBeTrue()
        ->and($module->isPublishable())->toBeFalse()
        ->and(Schema::hasColumns('menu_entries', ['position', 'parent_id']))->toBeTrue();
});

it('asks what the module does when no option is given', function () {
    $this->artisan('adminphp:make:module', ['name' => 'Faq'])
        ->expectsChoice('Que doit savoir faire ce module ?', ['trashable'], [
            'publishable' => 'Publication (brouillon, en ligne, programmation) et adresse',
            'trashable' => 'Corbeille',
            'sortable' => 'Ordre manuel (glisser-déposer)',
            'nested' => 'Imbrication (sous-éléments)',
            'blocks' => 'Contenu en blocs',
        ])
        ->assertSuccessful();

    expect(File::get(app_path('Models/Faq.php')))->toContain('use HasTranslations, HasUlids, SoftDeletes;');
});

it('keeps an existing module unless forced', function () {
    File::ensureDirectoryExists(app_path('Models'));
    File::put(app_path('Models/Tool.php'), '<?php // mine');

    $this->artisan('adminphp:make:module', ['name' => 'Tool', '--no-interaction' => true])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(File::get(app_path('Models/Tool.php')))->toBe('<?php // mine')
        ->and(File::exists(app_path('AdminPhp/Modules/ToolModule.php')))->toBeFalse()
        ->and(File::glob(database_path('migrations/*_create_tools_table.php')))->toBe([]);

    $this->artisan('adminphp:make:module', ['name' => 'Tool', '--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect(File::get(app_path('Models/Tool.php')))->toContain('class Tool extends Model');
});

it('creates a block', function () {
    $this->artisan('adminphp:make:block', ['name' => 'PhotoGallery'])
        ->expectsOutputToContain('stored with the type "photo-gallery"')
        ->assertSuccessful();

    require_once app_path('AdminPhp/Blocks/PhotoGalleryBlock.php');
    $block = new PhotoGalleryBlock;

    expect($block::type())->toBe('photo-gallery')
        ->and($block->title())->toBe('Photo Gallery')
        ->and(array_keys($block->fields()))->toBe(['text'])
        ->and($block->fields()['text']->isTranslatable())->toBeTrue();
});
