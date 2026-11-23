<?php

use App\Models\Inquiry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

const RESEND_URL = 'https://api.resend.com/emails';

function fillInquiry($component, array $overrides = [])
{
    $values = [
        'company' => 'Acme Logistics',
        'contact_name' => 'Pat Buyer',
        'email' => 'pat@acme.test',
        'label_type' => '4 x 6 direct thermal',
        'monthly_volume' => '30 cases',
        'delivery_zip' => '76180',
        'packaging_needs' => 'Also need cartons',
        ...$overrides,
    ];

    foreach ($values as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

beforeEach(function () {
    Http::preventStrayRequests();
    RateLimiter::clear('volume-inquiry:127.0.0.1');
});

test('the hero button links to the volume pricing form', function () {
    $this->get(route('home'))->assertOk()->assertSee(route('volume-pricing'), false);
});

test('the volume pricing page shows separate fields for each detail', function () {
    $this->get(route('volume-pricing'))
        ->assertOk()
        ->assertSee('Need more than 10 cases?')
        ->assertSeeInOrder(['Company', 'Contact name', 'Email', 'Label type', 'Monthly volume', 'Delivery ZIP code', 'Other packaging needs'])
        ->assertSee('Request custom pricing');
});

test('a request is saved and emailed to the sales team through resend', function () {
    config([
        'services.resend.key' => 're_test_123',
        'mail.from.address' => 'orders@lcm.test',
        'services.volume_pricing.to' => 'robert@lcm.test',
        'services.volume_pricing.cc' => 'brian@lcm.test',
    ]);
    Http::fake([RESEND_URL => Http::response(['id' => 'email_1'])]);

    fillInquiry(Livewire::test('pages::shop.volume-pricing'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true)
        ->assertSee('within one business day');

    $inquiry = Inquiry::sole();

    expect($inquiry->company)->toBe('Acme Logistics')
        ->and($inquiry->delivery_zip)->toBe('76180')
        ->and($inquiry->packaging_needs)->toBe('Also need cartons')
        ->and($inquiry->notified_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === RESEND_URL
        && $request->hasHeader('Authorization', 'Bearer re_test_123')
        && $request['to'] === ['robert@lcm.test']
        && $request['cc'] === ['brian@lcm.test']
        && $request['reply_to'] === 'pat@acme.test'
        && str_contains($request['subject'], 'Acme Logistics')
        && str_contains($request['text'], '30 cases'));
});

test('a request is still saved when resend is not configured', function () {
    config(['services.resend.key' => null]);

    fillInquiry(Livewire::test('pages::shop.volume-pricing'))
        ->call('submit')
        ->assertSet('submitted', true);

    expect(Inquiry::sole()->notified_at)->toBeNull();
    Http::assertNothingSent();
});

test('a request is still saved when resend fails but it is not marked as notified', function () {
    config([
        'services.resend.key' => 're_test_123',
        'mail.from.address' => 'orders@lcm.test',
        'services.volume_pricing.to' => 'robert@lcm.test',
    ]);
    Http::fake([RESEND_URL => Http::response(['message' => 'Invalid API key'], 401)]);

    fillInquiry(Livewire::test('pages::shop.volume-pricing'))
        ->call('submit')
        ->assertSet('submitted', true);

    expect(Inquiry::sole()->notified_at)->toBeNull();
});

test('the form requires every field except other packaging needs', function () {
    Livewire::test('pages::shop.volume-pricing')
        ->call('submit')
        ->assertHasErrors(['company', 'contact_name', 'email', 'label_type', 'monthly_volume', 'delivery_zip'])
        ->assertHasNoErrors(['packaging_needs']);

    expect(Inquiry::count())->toBe(0);
});

test('the form rejects an invalid email and ZIP code', function () {
    fillInquiry(Livewire::test('pages::shop.volume-pricing'), ['email' => 'nope', 'delivery_zip' => '7618'])
        ->call('submit')
        ->assertHasErrors(['email', 'delivery_zip']);
});

test('a visitor is limited to five requests per hour', function () {
    config(['services.resend.key' => null]);

    foreach (range(1, 5) as $attempt) {
        fillInquiry(Livewire::test('pages::shop.volume-pricing'))->call('submit')->assertSet('submitted', true);
    }

    fillInquiry(Livewire::test('pages::shop.volume-pricing'))
        ->call('submit')
        ->assertHasErrors(['company'])
        ->assertSet('submitted', false);

    expect(Inquiry::count())->toBe(5);
});
