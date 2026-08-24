<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('events can be listed searched and displayed publicly', function () {
    $event = Event::factory()->create([
        'title' => 'Belgrade Music Festival',
        'location' => 'Kalemegdan, Belgrade',
        'starts_at' => now()->addMonth(),
    ]);

    Event::factory()->create([
        'title' => 'Novi Sad Theatre Night',
        'location' => 'Novi Sad',
        'starts_at' => now()->addWeeks(2),
    ]);

    $this->getJson('/api/events?search=Music&location=Belgrade')
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->assertJsonPath('events.0.id', $event->id)
        ->assertJsonPath('events.0.title', 'Belgrade Music Festival')
        ->assertJsonMissingPath('events.0.status');

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('event.id', $event->id);
});

test('events can be sorted by an allowed field and direction', function () {
    Event::factory()->create(['title' => 'Alpha Event']);
    Event::factory()->create(['title' => 'Charlie Event']);
    Event::factory()->create(['title' => 'Bravo Event']);

    $this->getJson('/api/events?sort_by=title&sort_direction=desc')
        ->assertOk()
        ->assertJsonPath('sort.by', 'title')
        ->assertJsonPath('sort.direction', 'desc')
        ->assertJsonPath('events.0.title', 'Charlie Event')
        ->assertJsonPath('events.1.title', 'Bravo Event')
        ->assertJsonPath('events.2.title', 'Alpha Event');
});

test('event sorting rejects unsupported fields and directions', function () {
    $this->getJson('/api/events?sort_by=description&sort_direction=sideways')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_by', 'sort_direction']);
});

test('event management requires authentication', function () {
    $event = Event::factory()->create();

    $this->postJson('/api/events', [])->assertUnauthorized();
    $this->patchJson("/api/events/{$event->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/events/{$event->id}")->assertUnauthorized();
});

test('regular users cannot manage events', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $event = Event::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/events', validEventPayload())->assertForbidden();
    $this->patchJson("/api/events/{$event->id}", ['title' => 'Changed'])->assertForbidden();
    $this->deleteJson("/api/events/{$event->id}")->assertForbidden();
});

test('administrators can create update and delete events that have not started', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $eventId = $this->postJson('/api/events', validEventPayload())
        ->assertCreated()
        ->assertJsonPath('event.title', 'Summer Music Festival')
        ->assertJsonMissingPath('event.status')
        ->json('event.id');

    $this->patchJson("/api/events/{$eventId}", ['title' => 'Updated Festival'])
        ->assertOk()
        ->assertJsonPath('event.title', 'Updated Festival');

    $this->deleteJson("/api/events/{$eventId}")
        ->assertOk()
        ->assertJsonPath('message', 'Event deleted successfully.');

    $this->assertDatabaseMissing('events', ['id' => $eventId]);
});

test('administrators cannot update or delete events that have started', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $event = Event::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $this->patchJson("/api/events/{$event->id}", ['title' => 'Too Late'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->deleteJson("/api/events/{$event->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => $event->title,
    ]);
});

test('administrators cannot update or delete events that have orders', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $event = Event::factory()->create([
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHours(3),
    ]);
    $ticketType = TicketType::factory()->for($event)->create();

    Order::factory()->create([
        'ticket_type_id' => $ticketType->id,
        'status' => Order::STATUS_CANCELLED,
    ]);

    $this->patchJson("/api/events/{$event->id}", ['title' => 'Cannot Be Changed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->deleteJson("/api/events/{$event->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'title' => $event->title,
    ]);
});

test('event dates must describe a future valid interval', function () {
    Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    $this->postJson('/api/events', validEventPayload([
        'starts_at' => now()->subMinute()->toISOString(),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('starts_at');

    $this->postJson('/api/events', validEventPayload([
        'starts_at' => now()->addDays(2)->toISOString(),
        'ends_at' => now()->addDay()->toISOString(),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validEventPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Summer Music Festival',
        'description' => 'Open-air music event.',
        'location' => 'Belgrade Fortress',
        'starts_at' => now()->addMonth()->toISOString(),
        'ends_at' => now()->addMonth()->addHours(3)->toISOString(),
    ], $overrides);
}
