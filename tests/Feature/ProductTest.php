<?php

use App\Enums\BillingCycle;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('the products page lists active products first', function () {
    $user = User::factory()->create();
    Product::factory()->inactive()->create(['name' => 'Alpha Old']);
    Product::factory()->create(['name' => 'Zeta Current']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('products/index')
            ->where('products.0.name', 'Zeta Current')
            ->where('products.1.name', 'Alpha Old'));
});

test('a product is created with its price stored in paise', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'School ERP',
            'code' => 'serp',
            'sac_code' => '998314',
            'tax_rate' => '18',
            'list_price' => '45,000.50',
            'default_billing_cycle' => 'yearly',
            'unit_label' => 'per student',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('products.index'));

    $product = Product::firstWhere('code', 'SERP');

    expect($product->name)->toBe('School ERP')
        ->and($product->list_price)->toBe(4500050)
        ->and($product->default_billing_cycle)->toBe(BillingCycle::Yearly);
});

test('a product can be created without a price because prices are negotiated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Pharmacy Management',
            'code' => 'PHM',
            'sac_code' => '998314',
            'tax_rate' => '18',
            'default_billing_cycle' => 'yearly',
        ])
        ->assertSessionHasNoErrors();

    expect(Product::firstWhere('code', 'PHM')->list_price)->toBeNull();
});

test('product codes must be unique', function () {
    $user = User::factory()->create();
    Product::factory()->create(['code' => 'SERP']);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Another',
            'code' => 'SERP',
            'sac_code' => '998314',
            'tax_rate' => '18',
            'default_billing_cycle' => 'yearly',
        ])
        ->assertSessionHasErrors('code');
});

test('a product keeps its own code when updated', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['code' => 'SERP']);

    $this->actingAs($user)
        ->patch(route('products.update', $product), [
            'name' => 'School ERP Plus',
            'code' => 'SERP',
            'sac_code' => '998314',
            'tax_rate' => '18',
            'default_billing_cycle' => 'yearly',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($product->refresh()->name)->toBe('School ERP Plus')
        ->and($product->is_active)->toBeFalse();
});

test('invalid prices and GST rates are rejected with clear messages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'School ERP',
            'code' => 'SERP',
            'sac_code' => '998314',
            'tax_rate' => '15',
            'list_price' => '45000.555',
            'default_billing_cycle' => 'yearly',
        ])
        ->assertSessionHasErrors([
            'tax_rate' => 'Choose a GST rate: 0, 5, 12, 18 or 28%.',
            'list_price' => 'Enter the price in rupees, e.g. 45000 or 45000.50.',
        ]);
});

test('a product can be deleted', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertSoftDeleted($product);
});

test('each product shows how many clients use it and the range of deals', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    Subscription::factory()->for($product)->create(['rate' => 3_000_000]);
    Subscription::factory()->for($product)->create(['rate' => 4_800_000]);
    Subscription::factory()->for($product)->create(['rate' => 9_900_000, 'status' => 'cancelled']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.usage.clients', 2)
            ->where('products.0.usage.annual_value', 7_800_000)
            ->where('products.0.usage.min_annual', 3_000_000)
            ->where('products.0.usage.max_annual', 4_800_000));
});
