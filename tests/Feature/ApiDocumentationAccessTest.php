<?php

it('hides the API documentation in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/api/documentation')->assertNotFound();
    $this->get('/docs')->assertNotFound();
});

it('shows the API documentation in production when made public', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['l5-swagger.public_in_production' => true]);

    $this->get('/api/documentation')->assertOk();
});

it('shows the API documentation outside production', function () {
    $this->get('/api/documentation')->assertOk();
});
