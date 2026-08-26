<?php

test('public registration is disabled for enterprise ERP security', function () {
    expect(Route::has('register'))->toBeFalse();
});