<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('ticket types for an event can be listed publicly without pagination', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();

    $regular = TicketType::factory()->for($event)->create(['name' => 'Regular']);
    $vip = TicketType::factory()->for($event)->create(['name' => 'VIP']);
    TicketType::factory()->for($otherEvent)->create(['name' => 'Other Event Ticket']);

    $this->getJson("/api/events/{$event->id}/ticket-types")
        ->assertOk()
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonCount(2, 'ticket_types')
        ->assertJsonFragment(['id' => $regular->id])
        ->assertJsonFragment(['id' => $vip->id])
        ->assertJsonMissing(['name' => 'Other Event Ticket'])
        ->assertJsonMissingPath('pagination');
});

test('a ticket type can be displayed publicly by id', function () {
    $ticketType = TicketType::factory()->create();

    $this->getJson("/api/ticket-types/{$ticketType->id}")
        ->assertOk()
        ->assertJsonPath('ticket_type.id', $ticketType->id)
        ->assertJsonPath('ticket_type.event.id', $ticketType->event_id);
});

test('ticket type management requires authentication and an administrator role', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $payload = validTicketTypePayload($event);

    $this->postJson('/api/ticket-types', $payload)->assertUnauthorized();
    $this->patchJson("/api/ticket-types/{$ticketType->id}", ['name' => 'Changed'])->assertUnauthorized();
    $this->deleteJson("/api/ticket-types/{$ticketType->id}")->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_USER]));

    $this->postJson('/api/ticket-types', $payload)->assertForbidden();
    $this->patchJson("/api/ticket-types/{$ticketType->id}", ['name' => 'Changed'])->assertForbidden();
    $this->deleteJson("/api/ticket-types/{$ticketType->id}")->assertForbidden();
});

test('administrators can create update and delete ticket types before an event starts', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $event = Event::factory()->create(['starts_at' => now()->addMonth()]);

    $ticketTypeId = $this->postJson('/api/ticket-types', validTicketTypePayload($event))
        ->assertCreated()
        ->assertJsonPath('ticket_type.name', 'Early Bird')
        ->assertJsonPath('ticket_type.event.id', $event->id)
        ->json('ticket_type.id');

    $this->patchJson("/api/ticket-types/{$ticketTypeId}", [
        'name' => 'Standard',
        'price' => 3200,
        'quantity_available' => 120,
    ])
        ->assertOk()
        ->assertJsonPath('ticket_type.name', 'Standard')
        ->assertJsonPath('ticket_type.price', '3200.00')
        ->assertJsonPath('ticket_type.quantity_available', 120);

    $this->deleteJson("/api/ticket-types/{$ticketTypeId}")
        ->assertOk()
        ->assertJsonPath('message', 'Ticket type deleted successfully.');

    $this->assertDatabaseMissing('ticket_types', ['id' => $ticketTypeId]);
});

test('ticket types cannot be managed after their event has started', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $event = Event::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $ticketType = TicketType::factory()->for($event)->create();

    $this->postJson('/api/ticket-types', validTicketTypePayload($event))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->patchJson("/api/ticket-types/{$ticketType->id}", ['name' => 'Too Late'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->deleteJson("/api/ticket-types/{$ticketType->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');
});

test('ticket types with orders cannot be updated or deleted', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $event = Event::factory()->create(['starts_at' => now()->addMonth()]);
    $ticketType = TicketType::factory()->for($event)->create();

    Order::factory()->create([
        'ticket_type_id' => $ticketType->id,
        'status' => Order::STATUS_CANCELLED,
    ]);

    $this->patchJson("/api/ticket-types/{$ticketType->id}", ['name' => 'Cannot Change'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ticket_type');

    $this->deleteJson("/api/ticket-types/{$ticketType->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ticket_type');

    $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id]);
});

test('ticket type quantities name and event assignment are validated', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => 'Regular']);

    $this->postJson('/api/ticket-types', validTicketTypePayload($event, [
        'name' => 'Regular',
        'quantity_total' => 10,
        'quantity_available' => 11,
        'max_per_order' => 12,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->postJson('/api/ticket-types', validTicketTypePayload($otherEvent, [
        'name' => 'Regular',
    ]))->assertCreated();

    $this->patchJson("/api/ticket-types/{$ticketType->id}", [
        'event_id' => $otherEvent->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event_id');

    $this->patchJson("/api/ticket-types/{$ticketType->id}", [
        'quantity_total' => 5,
        'quantity_available' => 6,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity_available');

    $this->patchJson("/api/ticket-types/{$ticketType->id}", [
        'quantity_total' => 5,
        'quantity_available' => 5,
        'max_per_order' => 6,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_per_order');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validTicketTypePayload(Event $event, array $overrides = []): array
{
    return array_merge([
        'event_id' => $event->id,
        'name' => 'Early Bird',
        'description' => 'Discounted ticket available before regular admission.',
        'price' => 2500,
        'quantity_total' => 150,
        'quantity_available' => 150,
        'max_per_order' => 6,
    ], $overrides);
}
