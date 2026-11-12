<?php

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Modules\Module;
use Gmarineau\AdminPhp\Tests\Fixtures\MenuLink;
use Gmarineau\AdminPhp\Tests\Fixtures\MenuLinkModule;
use Gmarineau\AdminPhp\Tests\Fixtures\Tag;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    app(AdminPhp::class)->registerModules([MenuLinkModule::class]);

    actingAsAdmin();
});

function createMenuLink(string $title): string
{
    return test()->postJson('/admin/api/modules/menu-link/items', ['title' => ['fr' => $title]])->assertCreated()->json('data.id');
}

/**
 * @param  array<int, array{id: string, parentId?: string|null}>  $items
 */
function reorderMenuLinks(array $items): TestResponse
{
    return test()->putJson('/admin/api/modules/menu-link/items/order', ['items' => $items]);
}

class NestedTagModule extends Module
{
    protected string $model = Tag::class;

    protected int $nestingDepth = 1;

    public function form(): array
    {
        return [];
    }
}

it('tells the admin front that a module is sortable and how deep it nests', function () {
    $this->getJson('/admin/api/modules/menu-link')
        ->assertJsonPath('data.sortable', true)
        ->assertJsonPath('data.nestingDepth', 1);

    $this->getJson('/admin/api/modules/article')
        ->assertJsonPath('data.sortable', false)
        ->assertJsonPath('data.nestingDepth', 0);
});

it('refuses to nest the items of a model without position and nesting', function () {
    (new NestedTagModule)->nestingDepth();
})->throws(LogicException::class, 'Module [nested-tag] nests its items: add HasPosition and HasNesting');

it('adds new items last and lists all items in their order with their parent', function () {
    $home = createMenuLink('Accueil');
    $blog = createMenuLink('Blog');

    expect(MenuLink::query()->ordered()->pluck('id')->all())->toBe([$home, $blog]);

    $this->getJson('/admin/api/modules/menu-link/items')
        ->assertOk()
        ->assertJsonPath('data.0.id', $home)
        ->assertJsonPath('data.0.parentId', null)
        ->assertJsonPath('data.1.id', $blog)
        ->assertJsonPath('meta.total', 2);
});

it('saves the order and nesting chosen by drag and drop without touching the items', function () {
    Carbon::setTestNow('2026-11-01 10:00:00');
    $home = createMenuLink('Accueil');
    $blog = createMenuLink('Blog');
    $news = createMenuLink('Actualités');
    Carbon::setTestNow('2026-11-02 10:00:00');

    reorderMenuLinks([
        ['id' => $blog, 'parentId' => null],
        ['id' => $news, 'parentId' => $blog],
        ['id' => $home, 'parentId' => null],
    ])->assertNoContent();

    $roots = MenuLink::query()->roots()->ordered()->with('children')->get();

    expect($roots->modelKeys())->toBe([$blog, $home])
        ->and($roots->first()->children->modelKeys())->toBe([$news])
        ->and(MenuLink::query()->findOrFail($news)->updated_at->toDateString())->toBe('2026-11-01');
});

it('refuses to nest deeper than the module allows', function () {
    [$first, $second, $third] = [createMenuLink('Un'), createMenuLink('Deux'), createMenuLink('Trois')];

    reorderMenuLinks([
        ['id' => $first],
        ['id' => $second, 'parentId' => $first],
        ['id' => $third, 'parentId' => $second],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items' => 'Les éléments ne peuvent pas être imbriqués sur plus de 2 niveaux.']);
});

it('refuses a child listed before its parent', function () {
    [$first, $second] = [createMenuLink('Un'), createMenuLink('Deux')];

    reorderMenuLinks([
        ['id' => $second, 'parentId' => $first],
        ['id' => $first],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items' => 'Un élément doit suivre son parent.']);
});

it('refuses an item that no longer exists', function () {
    reorderMenuLinks([['id' => createMenuLink('Un')], ['id' => '01JAAAAAAAAAAAAAAAAAAAAAAA']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items' => 'Un des éléments n’existe plus : rechargez la page.']);
});

it('answers 404 when ordering the items of a module that is not sortable', function () {
    $this->putJson('/admin/api/modules/article/items/order', ['items' => [['id' => createArticleItem()]]])->assertNotFound();
});
