<?php

use App\Models\Inquiry;
use App\Models\Order;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admins see shop-wide sales, orders and volume forms for the current month', function () {
    Order::factory()->paid()->create(['subtotal_cents' => 10000]);
    Order::factory()->paid()->create(['subtotal_cents' => 25050]);
    Order::factory()->paid()->create(['subtotal_cents' => 90000, 'paid_at' => now()->subMonths(2)]);
    Order::factory()->create(['subtotal_cents' => 77700]);
    Inquiry::factory()->count(2)->create();
    Inquiry::factory()->create(['created_at' => now()->subMonths(3)]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Current Sales', '$350.50', '$1,250.50 all time'])
        ->assertSeeInOrder(['Current Orders', '2', '3 all time'])
        ->assertSeeInOrder(['Volume Forms Submitted', '2', '3 all time']);
});

test('customers only see their own figures and no volume forms', function () {
    $user = User::factory()->create();
    Order::factory()->paid()->for($user)->create(['subtotal_cents' => 12345]);
    Order::factory()->paid()->create(['subtotal_cents' => 999999]);
    Inquiry::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Spending')
        ->assertSee('$123.45')
        ->assertDontSee('$9,999.99')
        ->assertDontSee('Volume Forms Submitted')
        ->assertDontSee('Current Sales');
});
