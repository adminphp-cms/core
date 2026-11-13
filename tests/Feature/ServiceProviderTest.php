<?php

use AdminPhp\AdminPhpServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(AdminPhpServiceProvider::class))->not->toBeEmpty();
});
