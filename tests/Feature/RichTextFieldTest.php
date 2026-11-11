<?php

use Gmarineau\AdminPhp\Fields\RichText;

it('offers every tool by default', function () {
    expect(RichText::make('body')->toSchema())->toMatchArray([
        'type' => 'rich-text',
        'tools' => RichText::TOOLS,
    ]);
});

it('narrows the toolbar down, keeping the toolbar order', function () {
    $schema = RichText::make('body')->toolbar(['link', 'bold', 'italic'])->toSchema();

    expect($schema['tools'])->toBe(['bold', 'italic', 'link']);
});

it('refuses unknown tools', function () {
    RichText::make('body')->toolbar(['bold', 'marquee']);
})->throws(InvalidArgumentException::class, 'marquee');
