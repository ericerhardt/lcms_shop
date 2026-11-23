<?php

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->admin = User::factory()->admin()->create();
});

test('admins see each submission with its details, newest first', function () {
    Inquiry::factory()->create(['company' => 'Older Co', 'created_at' => now()->subDay()]);
    Inquiry::factory()->create([
        'company' => 'Newer Co',
        'contact_name' => 'Pat Buyer',
        'label_type' => '3x2 Labels',
        'monthly_volume' => '40 cases',
        'delivery_zip' => '75201',
        'packaging_needs' => 'Needs cartons too',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.inquiries')
        ->assertSeeInOrder(['Newer Co', 'Older Co'])
        ->assertSee('Pat Buyer')
        ->assertSee('40 cases')
        ->assertSee('75201')
        ->assertSee('Needs cartons too');
});

test('submissions can be searched and filtered by email status', function () {
    Inquiry::factory()->create(['company' => 'Sent Co', 'notified_at' => now()]);
    Inquiry::factory()->create(['company' => 'Unsent Co', 'notified_at' => null]);
    Inquiry::factory()->create(['company' => 'Other Co', 'contact_name' => 'Zed Zebra']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.inquiries')
        ->set('search', 'ZEBRA')
        ->assertSee('Other Co')
        ->assertDontSee('Sent Co')
        ->set('search', '')
        ->set('delivery', 'unsent')
        ->assertSee('Unsent Co')
        ->assertDontSee('Sent Co')
        ->set('delivery', 'sent')
        ->assertSee('Sent Co')
        ->assertDontSee('Unsent Co');
});

test('an unsent submission can be emailed from the list', function () {
    config([
        'services.resend.key' => 're_test',
        'mail.from.address' => 'orders@lcm.test',
        'services.volume_pricing.to' => 'sales@lcm.test',
    ]);
    Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'e1'])]);
    $inquiry = Inquiry::factory()->create(['notified_at' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.inquiries')
        ->call('resend', $inquiry->id);

    expect($inquiry->fresh()->notified_at)->not->toBeNull();
    Http::assertSentCount(1);
});

test('a failed resend leaves the submission marked as not emailed', function () {
    config([
        'services.resend.key' => 're_test',
        'mail.from.address' => 'orders@lcm.test',
        'services.volume_pricing.to' => 'sales@lcm.test',
    ]);
    Http::fake(['https://api.resend.com/emails' => Http::response(['message' => 'Invalid API key'], 401)]);
    $inquiry = Inquiry::factory()->create(['notified_at' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.inquiries')
        ->call('resend', $inquiry->id);

    expect($inquiry->fresh()->notified_at)->toBeNull();
});
