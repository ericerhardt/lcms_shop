<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('the test email command sends to the volume pricing recipients through resend', function () {
    config([
        'services.resend.key' => 're_test',
        'mail.from.address' => 'noreply@resend.lcmsol.com',
        'services.volume_pricing.to' => 'sales@lcm.test',
        'services.volume_pricing.cc' => 'boss@lcm.test',
    ]);
    Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'abc'])]);

    $this->artisan('shop:test-email')->assertSuccessful();

    Http::assertSent(fn ($request) => $request['to'] === ['sales@lcm.test']
        && $request['cc'] === ['boss@lcm.test']
        && str_contains($request['from'], 'noreply@resend.lcmsol.com'));
});

test('the test email command fails clearly when email is not configured', function () {
    config(['services.resend.key' => null, 'services.volume_pricing.to' => null]);

    $this->artisan('shop:test-email')->assertFailed();

    Http::assertNothingSent();
});

test('the test email command reports the error resend returns', function () {
    config([
        'services.resend.key' => 're_test',
        'mail.from.address' => 'noreply@gmail.com',
        'services.volume_pricing.to' => 'sales@lcm.test',
    ]);
    Http::fake(['https://api.resend.com/emails' => Http::response(['message' => 'The gmail.com domain is not verified.'], 403)]);

    $this->artisan('shop:test-email')
        ->expectsOutputToContain('The gmail.com domain is not verified.')
        ->assertFailed();
});
