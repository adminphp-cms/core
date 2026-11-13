# AdminPhp

[![Tests](https://github.com/adminphp-cms/core/actions/workflows/tests.yml/badge.svg)](https://github.com/adminphp-cms/core/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-12%20%7C%2013-ff2d20)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE.md)

A content management admin for Laravel, inspired by [Twill](https://twillcms.com). You describe your
content as PHP classes (modules, fields and blocks); AdminPhp gives your editors a fast admin to write,
preview, schedule and publish it, while your site keeps rendering it with plain Eloquent and Blade.

The admin is a Vue 3 single page application shipped prebuilt with the package: there is nothing to
compile in your project.

## Features

- **Modules**: one PHP class per kind of content (articles, pages, menu links…), with its form and listing.
- **Fields**: text, rich text, slug, select, toggle, date, image, repeater, browser (links to other
  contents) and blocks. Text fields can be translated in every locale of the site.
- **Block editor**: build pages from your own blocks (text, image, quote…), reorder them by drag and drop.
- **Publication workflow**: drafts kept apart from the live version, publication windows, revisions you
  can restore, and a preview of unsaved changes rendered by your own Blade view.
- **Listings**: columns, filters, views, search and sort declared on the module; manual ordering and
  nested items (menus, page trees); trash, duplication and bulk actions.
- **Media library**: uploads with responsive WebP copies, an `<x-adminphp::image>` component for
  `srcset`, and usage tracking so an image in use cannot be deleted.
- **Settings**: a form for site-wide values (name, logo, social links…), read anywhere in your code.
- **Users**: admin and editor roles, email invitations, password reset, deactivation.
- **Edit locks**: while someone edits a content, others see it read-only and can take over.
- **Generators**: `adminphp:make:module` and `adminphp:make:block` scaffold your content in seconds.

## Requirements

- PHP 8.3 or later, with the `gd` (or `imagick`) and `exif` extensions
- Laravel 12 or 13
- SQLite, MySQL 8 or PostgreSQL

## Installation

AdminPhp is not on Packagist yet: add the repository to your `composer.json` first.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/adminphp-cms/core" }
]
```

Then require the package and run the installer, which publishes the config, the migrations and the admin
assets, and runs the migrations:

```bash
composer require adminphp-cms/core
php artisan adminphp:install
```

Create your first admin user and make uploaded images public:

```bash
php artisan adminphp:user
php artisan storage:link
```

The admin is now available at `/admin` (see `ADMINPHP_PATH` in `config/adminphp.php`).

## Quick start

Generate a module with its model and migration:

```bash
php artisan adminphp:make:module Project --publishable --trashable --blocks
```

Describe its fields in the module class:

```php
namespace App\AdminPhp\Modules;

use App\AdminPhp\Blocks\TextBlock;
use App\Models\Project;
use AdminPhp\Fields\Blocks;
use AdminPhp\Fields\Image;
use AdminPhp\Fields\Slug;
use AdminPhp\Fields\Text;
use AdminPhp\Modules\Module;

class ProjectModule extends Module
{
    protected string $model = Project::class;

    protected string $icon = 'briefcase';

    protected ?string $previewView = 'projects.show';

    public function form(): array
    {
        return [
            Text::make('title')->label('Title')->translatable()->required(),
            Slug::make('slug')->label('Address')->from('title')->prefix('/projects/'),
            Image::make('cover')->label('Cover'),
            Blocks::make('content')->label('Content')->allow([TextBlock::class]),
        ];
    }
}
```

Register it, for instance in `AppServiceProvider::boot()`, then run `php artisan migrate`:

```php
use App\AdminPhp\Modules\ProjectModule;
use AdminPhp\AdminPhp;

app(AdminPhp::class)->registerModules([ProjectModule::class]);
```

Every field takes a `note()` shown under it, a `placeholder()` shown while it is empty, and a
`default()` value for new contents (items, blocks, repeater rows, settings never saved). A text can be
of a given kind, with the matching keyboard on mobile and its validation:

```php
Text::make('email')->label('E-mail')->email()->note('Shown in the footer.'),
Text::make('website')->url()->placeholder('https://…'),
Text::make('phone')->tel(),
Text::make('rooms')->number(min: 1, max: 20, step: 1)->default(1), // Stored as a number.
```

## Displaying content on your site

Your models stay regular Eloquent models:

```php
use AdminPhp\Models\Media;

$project = Project::query()->published()->where('slug', $slug)->firstOrFail();
$cover = Media::query()->find($project->cover); // An Image field stores the id of its media.
```

```blade
<h1>{{ $project->title }}</h1>

<x-adminphp::image :media="$cover" sizes="100vw" />

@foreach ($project->blocks as $block)
    @if ($block->type === 'text')
        <p>{{ $block->translated('text') }}</p>
    @endif
@endforeach
```

A `RichText` field stores the JSON document of its editor. Render it as HTML, every text escaped and
only safe links kept, with `$block->richText('body')` or with the component, which takes any stored
document (a block field or a model attribute):

```blade
<x-adminphp::rich-text :content="$block->translated('body')" class="prose" />
```

Settings are read with `app(AdminPhp::class)->setting('site_name')`.

## Commands

| Command | Description |
| --- | --- |
| `adminphp:install` | Publish the config, migrations and admin assets, then migrate |
| `adminphp:user` | Create an admin user |
| `adminphp:make:module` | Create a module with its model and migration |
| `adminphp:make:block` | Create a block |
| `adminphp:media:regenerate` | Make the responsive copies of the images again |
| `adminphp:media:sync-usages` | Record again which contents use each image |

## Testing

```bash
composer install
vendor/bin/pest
```

The tests run on SQLite in memory. To run them on MySQL or PostgreSQL, set `DB_CONNECTION`, `DB_HOST`,
`DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.

The admin front lives in `resources/js`. After changing it, rebuild the committed assets with
`npm install && npm run build`.

## License

AdminPhp is open-source software licensed under the [MIT license](LICENSE.md).
