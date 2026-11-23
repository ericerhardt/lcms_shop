<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('the user list shows accounts and can be searched', function () {
    User::factory()->create(['name' => 'Casey Customer', 'email' => 'casey@shop.test']);
    User::factory()->create(['name' => 'Morgan Other', 'email' => 'morgan@elsewhere.test']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->assertSee('Casey Customer')
        ->assertSee('Morgan Other')
        ->set('search', 'SHOP.test')
        ->assertSee('Casey Customer')
        ->assertDontSee('Morgan Other');
});

test('an admin can create a verified user with a lowercased email', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->set('name', 'New Person')
        ->set('email', ' New.Person@Shop.TEST ')
        ->set('password', 'a-Strong-pass-123')
        ->set('is_admin', true)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'new.person@shop.test')->sole();

    expect($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-Strong-pass-123', $user->password))->toBeTrue();
});

test('creating a user requires a password and a unique email', function () {
    $existing = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->set('name', 'Dup')
        ->set('email', $existing->email)
        ->call('save')
        ->assertHasErrors(['email', 'password']);
});

test('editing keeps the password when left blank and can change the role', function () {
    $user = User::factory()->create(['password' => 'original-Pass-1']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('edit', $user->id)
        ->assertSet('email', $user->email)
        ->set('name', 'Renamed')
        ->set('is_admin', true)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and($user->is_admin)->toBeTrue()
        ->and(Hash::check('original-Pass-1', $user->password))->toBeTrue();
});

test('an admin can reset another users password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('edit', $user->id)
        ->set('password', 'brand-New-pass-9')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('brand-New-pass-9', $user->fresh()->password))->toBeTrue();
});

test('an admin cannot remove their own admin access', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('edit', $this->admin->id)
        ->set('is_admin', false)
        ->call('save')
        ->assertHasErrors(['is_admin']);

    expect($this->admin->fresh()->is_admin)->toBeTrue();
});

test('deleting a user keeps their orders, and admins cannot delete themselves', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->for($user)->create();

    $component = Livewire::actingAs($this->admin)->test('pages::admin.users');

    $component->call('delete', $user->id);
    $component->call('delete', $this->admin->id);

    expect(User::find($user->id))->toBeNull()
        ->and($order->fresh()->user_id)->toBeNull()
        ->and(User::find($this->admin->id))->not->toBeNull();
});
