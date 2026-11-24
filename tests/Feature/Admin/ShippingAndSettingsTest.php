<?php

use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\StoreSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('an admin can add a shipping method with carrier, timeframe and cost', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.shipping')
        ->set('carrier', 'UPS Ground')
        ->set('timeframe', '3-5 business days')
        ->set('price', '18.50')
        ->call('save')
        ->assertHasNoErrors();

    expect(ShippingMethod::sole())
        ->carrier->toBe('UPS Ground')
        ->timeframe->toBe('3-5 business days')
        ->price_cents->toBe(1850)
        ->is_active->toBeTrue();
});

test('a shipping method needs a carrier, timeframe and a non-negative cost', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.shipping')
        ->set('price', '-1')
        ->call('save')
        ->assertHasErrors(['carrier', 'timeframe', 'price']);

    expect(ShippingMethod::count())->toBe(0);
});

test('a shipping method can be edited, switched off and deleted', function () {
    $method = ShippingMethod::factory()->create(['carrier' => 'USPS', 'price_cents' => 900]);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.shipping');

    $component->call('edit', $method->id)
        ->assertSet('price', '9.00')
        ->set('carrier', 'USPS Priority')
        ->set('price', '12.25')
        ->call('save')
        ->assertHasNoErrors();

    expect($method->fresh())->carrier->toBe('USPS Priority')->price_cents->toBe(1225);

    $component->call('toggle', $method->id);
    expect($method->fresh()->is_active)->toBeFalse();

    $component->call('delete', $method->id);
    expect(ShippingMethod::count())->toBe(0);
});

test('the shipping page warns when no method is active', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.shipping')
        ->assertSee('No active shipping methods');

    ShippingMethod::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.shipping')
        ->assertDontSee('No active shipping methods');
});

test('store settings load their defaults and can be saved', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings')
        ->assertSet('tax_enabled', false)
        ->assertSet('tax_label', 'Sales tax')
        ->set('store_name', 'LCM Solutions')
        ->set('contact_email', 'help@lcm.test')
        ->set('order_copy_emails', 'Orders@LCM.test; owner@lcm.test')
        ->set('tax_enabled', true)
        ->set('tax_rate', '8.25')
        ->set('tax_label', 'Texas sales tax')
        ->set('tax_on_shipping', true)
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(StoreSettings::class);

    expect($settings->storeName())->toBe('LCM Solutions')
        ->and($settings->contactEmail())->toBe('help@lcm.test')
        ->and($settings->copyRecipients())->toBe(['orders@lcm.test', 'owner@lcm.test'])
        ->and($settings->taxEnabled())->toBeTrue()
        ->and($settings->taxRate())->toBe(8.25)
        ->and($settings->taxLabel())->toBe('Texas sales tax')
        ->and($settings->taxOnShipping())->toBeTrue();
});

test('saved settings are shown again when the page is reopened', function () {
    app(StoreSettings::class)->save(['order_copy_emails' => 'orders@lcm.test', 'email_footer' => 'See you soon']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings')
        ->assertSet('order_copy_emails', 'orders@lcm.test')
        ->assertSet('email_footer', 'See you soon');
});

test('settings reject bad email addresses and out of range tax rates', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings')
        ->set('order_copy_emails', 'good@lcm.test, not-an-email')
        ->set('contact_email', 'nope')
        ->set('tax_rate', '45')
        ->call('save')
        ->assertHasErrors(['order_copy_emails', 'contact_email', 'tax_rate']);
});

test('the email subject and message above the order are required', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.settings')
        ->set('email_subject', '')
        ->set('email_intro', '')
        ->call('save')
        ->assertHasErrors(['email_subject', 'email_intro']);
});
