<?php

use AdminPhp\AdminPhp;
use AdminPhp\Tests\Fixtures\Article;
use AdminPhp\Tests\Fixtures\ArticleModule;
use Illuminate\Database\Eloquent\Relations\Relation;

it('registers the module under its name and morph alias', function () {
    expect(app(AdminPhp::class)->module('article'))->toBeInstanceOf(ArticleModule::class)
        ->and(Relation::getMorphedModel('article'))->toBe(Article::class);
});

it('describes the module form, including its blocks, for the admin front', function () {
    $schema = (new ArticleModule)->toSchema();

    expect($schema['name'])->toBe('article')
        ->and($schema['fields'][0])->toMatchArray([
            'name' => 'title',
            'type' => 'text',
            'label' => 'Titre',
            'translatable' => true,
            'required' => true,
            'multiline' => false,
        ])
        ->and($schema['fields'][2]['type'])->toBe('blocks')
        ->and($schema['fields'][2]['blocks'])->toHaveCount(4)
        ->and($schema['fields'][2]['blocks'][1])->toMatchArray([
            'type' => 'quote',
            'title' => 'Citation',
            'version' => 2,
        ])
        ->and(array_column($schema['fields'][2]['blocks'][1]['fields'], 'name'))->toBe(['quote', 'author']);
});
