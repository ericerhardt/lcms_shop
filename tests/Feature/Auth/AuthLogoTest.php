<?php

test('the account pages show the LCM Solutions logo', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('images/lcm-long.png', false);
})->with(['login', 'register', 'password.request']);
