<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketTypeController extends Controller
{
    /**
     * Display all ticket types for the specified event.
     */
    public function index(Event $event): JsonResponse
    {
        return response()->json([
            'event_id' => $event->id,
            'ticket_types' => TicketTypeResource::collection($event->ticketTypes()->get()),
        ]);
    }

    /**
     * Store a newly created ticket type.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate($this->storeRules($request));
        $event = Event::query()->findOrFail($validated['event_id']);

        $this->ensureEventHasNotStarted($event);
        $this->ensureValidQuantities($validated);

        $ticketType = TicketType::query()
            ->create($validated)
            ->load('event');

        return response()->json([
            'message' => 'Ticket type created successfully.',
            'ticket_type' => new TicketTypeResource($ticketType),
        ], 201);
    }

    /**
     * Display the specified ticket type.
     */
    public function show(TicketType $ticketType): JsonResponse
    {
        return response()->json([
            'ticket_type' => new TicketTypeResource($ticketType->load('event')),
        ]);
    }

    /**
     * Update the specified ticket type.
     */
    public function update(Request $request, TicketType $ticketType): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->ensureEventHasNotStarted($ticketType->event);
        $this->ensureHasNoOrders($ticketType);

        $validated = $request->validate($this->updateRules($ticketType));
        $this->ensureValidQuantities($validated, $ticketType);

        $ticketType->update($validated);

        return response()->json([
            'message' => 'Ticket type updated successfully.',
            'ticket_type' => new TicketTypeResource($ticketType->refresh()->load('event')),
        ]);
    }

    /**
     * Remove the specified ticket type.
     */
    public function destroy(Request $request, TicketType $ticketType): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->ensureEventHasNotStarted($ticketType->event);
        $this->ensureHasNoOrders($ticketType);

        $ticketType->delete();

        return response()->json([
            'message' => 'Ticket type deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(Request $request): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ticket_types', 'name')
                    ->where('event_id', $request->input('event_id')),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity_total' => ['required', 'integer', 'min:1'],
            'quantity_available' => ['required', 'integer', 'min:0'],
            'max_per_order' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(TicketType $ticketType): array
    {
        return [
            'event_id' => ['prohibited'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('ticket_types', 'name')
                    ->where('event_id', $ticketType->event_id)
                    ->ignore($ticketType),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_total' => ['sometimes', 'required', 'integer', 'min:1'],
            'quantity_available' => ['sometimes', 'required', 'integer', 'min:0'],
            'max_per_order' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureValidQuantities(array $validated, ?TicketType $ticketType = null): void
    {
        $quantityTotal = (int) ($validated['quantity_total'] ?? $ticketType?->quantity_total);
        $quantityAvailable = (int) ($validated['quantity_available'] ?? $ticketType?->quantity_available);
        $maxPerOrder = (int) ($validated['max_per_order'] ?? $ticketType?->max_per_order);

        if ($quantityAvailable > $quantityTotal) {
            throw ValidationException::withMessages([
                'quantity_available' => ['The quantity available must be less than or equal to quantity total.'],
            ]);
        }

        if ($maxPerOrder > $quantityTotal) {
            throw ValidationException::withMessages([
                'max_per_order' => ['The maximum per order must be less than or equal to quantity total.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureEventHasNotStarted(Event $event): void
    {
        if ($event->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'event' => ['Ticket types cannot be changed after the event has started.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureHasNoOrders(TicketType $ticketType): void
    {
        if ($ticketType->orders()->exists()) {
            throw ValidationException::withMessages([
                'ticket_type' => ['A ticket type with existing orders cannot be changed.'],
            ]);
        }
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403, 'Only administrators can manage ticket types.');
    }
}
