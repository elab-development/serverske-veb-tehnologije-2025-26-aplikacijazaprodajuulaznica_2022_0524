<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('order routes require authentication', function () {
    $order = Order::factory()->create();

    $this->getJson('/api/orders')->assertUnauthorized();
    $this->postJson('/api/orders', [])->assertUnauthorized();
    $this->getJson("/api/orders/{$order->id}")->assertUnauthorized();
    $this->patchJson("/api/orders/{$order->id}", [])->assertUnauthorized();
    $this->getJson("/api/users/{$order->user_id}/orders")->assertUnauthorized();
    $this->getJson("/api/events/{$order->ticketType->event_id}/orders")->assertUnauthorized();
});

test('only regular users can create queued orders with server calculated fields', function () {
    $event = Event::factory()->create(['starts_at' => now()->addMonth()]);
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 1500,
        'quantity_total' => 10,
        'quantity_available' => 5,
        'max_per_order' => 3,
    ]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['role' => User::ROLE_USER]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/orders', validOrderPayload($ticketType))
        ->assertForbidden();

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', validOrderPayload($ticketType))
        ->assertCreated()
        ->assertJsonPath('order.user_id', $user->id)
        ->assertJsonPath('order.ticket_type_id', $ticketType->id)
        ->assertJsonPath('order.quantity', 2)
        ->assertJsonPath('order.unit_price', '1500.00')
        ->assertJsonPath('order.total_price', '3000.00')
        ->assertJsonPath('order.status', Order::STATUS_QUEUED)
        ->assertJsonPath('order.queue_number', 1);

    $this->postJson('/api/orders', validOrderPayload($ticketType, ['quantity' => 1]))
        ->assertCreated()
        ->assertJsonPath('order.queue_number', 2);

    expect($ticketType->fresh()->quantity_available)->toBe(5);
});

test('order creation rejects protected fields invalid quantities and started events', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    Sanctum::actingAs($user);

    $futureEvent = Event::factory()->create(['starts_at' => now()->addMonth()]);
    $ticketType = TicketType::factory()->for($futureEvent)->create([
        'quantity_total' => 10,
        'quantity_available' => 2,
        'max_per_order' => 2,
    ]);

    $this->postJson('/api/orders', validOrderPayload($ticketType, [
        'status' => Order::STATUS_PAID,
        'user_id' => User::factory()->create()->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'user_id']);

    $this->postJson('/api/orders', validOrderPayload($ticketType, ['quantity' => 3]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    $ticketType->update([
        'quantity_available' => 1,
        'max_per_order' => 5,
    ]);

    $this->postJson('/api/orders', validOrderPayload($ticketType, ['quantity' => 2]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    $startedEvent = Event::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $startedTicketType = TicketType::factory()->for($startedEvent)->create();

    $this->postJson('/api/orders', validOrderPayload($startedTicketType))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');
});

test('users list only their orders while administrators list all orders', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    Order::factory()->count(2)->create(['user_id' => $user->id]);
    Order::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(2, 'orders')
        ->assertJsonPath('orders.0.user_id', $user->id)
        ->assertJsonPath('orders.1.user_id', $user->id)
        ->assertJsonMissingPath('pagination');

    Sanctum::actingAs($admin);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(3, 'orders');
});

test('only administrators can list orders through user and event nested routes', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $otherTicketType = TicketType::factory()->for($otherEvent)->create();
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $userEventOrder = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
    ]);
    $userOtherEventOrder = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $otherTicketType->id,
    ]);
    $otherUserEventOrder = Order::factory()->create([
        'user_id' => $otherUser->id,
        'ticket_type_id' => $ticketType->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/users/{$user->id}/orders")->assertForbidden();
    $this->getJson("/api/events/{$event->id}/orders")->assertForbidden();

    Sanctum::actingAs($admin);

    $userOrdersResponse = $this->getJson("/api/users/{$user->id}/orders")
        ->assertOk()
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonCount(2, 'orders')
        ->assertJsonMissingPath('pagination');

    expect(collect($userOrdersResponse->json('orders'))->pluck('id')->all())
        ->toEqualCanonicalizing([$userEventOrder->id, $userOtherEventOrder->id]);

    $eventOrdersResponse = $this->getJson("/api/events/{$event->id}/orders")
        ->assertOk()
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonCount(2, 'orders')
        ->assertJsonMissingPath('pagination');

    expect(collect($eventOrdersResponse->json('orders'))->pluck('id')->all())
        ->toEqualCanonicalizing([$userEventOrder->id, $otherUserEventOrder->id]);
});

test('an order can be displayed only by its owner or an administrator', function () {
    $owner = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = Order::factory()->create(['user_id' => $owner->id]);

    Sanctum::actingAs($owner);
    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('order.id', $order->id);

    Sanctum::actingAs($otherUser);
    $this->getJson("/api/orders/{$order->id}")->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('order.id', $order->id);
});

test('an administrator advances an order and its owner pays the reserved order', function () {
    $event = Event::factory()->create(['starts_at' => now()->addMonth()]);
    $ticketType = TicketType::factory()->for($event)->create([
        'quantity_total' => 10,
        'quantity_available' => 5,
    ]);
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'status' => Order::STATUS_QUEUED,
        'purchased_at' => null,
    ]);

    Sanctum::actingAs($user);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    Sanctum::actingAs($admin);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_PROCESSING])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_PROCESSING);

    expect($ticketType->fresh()->quantity_available)->toBe(5);

    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_PENDING])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_PENDING);

    expect($ticketType->fresh()->quantity_available)->toBe(3);

    Sanctum::actingAs($user);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_PAID])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_PAID);

    expect($order->fresh()->purchased_at)->not->toBeNull()
        ->and($ticketType->fresh()->quantity_available)->toBe(3);

    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_CANCELLED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('cancelling a pending order restores its reserved tickets', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $ticketType = TicketType::factory()->create([
        'quantity_total' => 10,
        'quantity_available' => 7,
    ]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 3,
        'status' => Order::STATUS_PENDING,
        'purchased_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_CANCELLED])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_CANCELLED);

    expect($ticketType->fresh()->quantity_available)->toBe(10);
});

test('order updates reject other fields inaccessible orders and invalid final transitions', function () {
    $owner = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'status' => Order::STATUS_QUEUED,
    ]);

    Sanctum::actingAs($otherUser);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_CANCELLED])
        ->assertForbidden();

    Sanctum::actingAs($owner);
    $this->patchJson("/api/orders/{$order->id}", [
        'status' => Order::STATUS_CANCELLED,
        'quantity' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_CANCELLED])
        ->assertOk();

    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_QUEUED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('orders cannot advance after an event starts but can still be cancelled', function () {
    $event = Event::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $ticketType = TicketType::factory()->for($event)->create();
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'status' => Order::STATUS_QUEUED,
    ]);

    Sanctum::actingAs($admin);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    Sanctum::actingAs($user);
    $this->patchJson("/api/orders/{$order->id}", ['status' => Order::STATUS_CANCELLED])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_CANCELLED);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validOrderPayload(TicketType $ticketType, array $overrides = []): array
{
    return array_merge([
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
    ], $overrides);
}
