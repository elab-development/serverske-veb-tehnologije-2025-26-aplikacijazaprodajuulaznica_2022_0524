<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const USER_TRANSITIONS = [
        Order::STATUS_QUEUED => [
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PENDING => [
            Order::STATUS_PAID,
            Order::STATUS_CANCELLED,
        ],
    ];

    private const ADMIN_TRANSITIONS = [
        Order::STATUS_QUEUED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_PENDING,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
        Order::STATUS_PENDING => [
            Order::STATUS_PAID,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAILED,
        ],
    ];

    private const FORWARD_STATUSES = [
        Order::STATUS_PROCESSING,
        Order::STATUS_PENDING,
        Order::STATUS_PAID,
    ];

    /**
     * Display orders visible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $query = Order::query()->with(['user', 'ticketType.event']);

        if ($user->role !== User::ROLE_ADMIN) {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'orders' => OrderResource::collection($query->latest()->get()),
        ]);
    }

    /**
     * Display all orders placed by the specified user.
     */
    public function forUser(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($this->authenticatedUser($request));

        $orders = Order::query()
            ->with(['user', 'ticketType.event'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'user_id' => $user->id,
            'orders' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Display all orders for the specified event.
     */
    public function forEvent(Request $request, Event $event): JsonResponse
    {
        $this->ensureAdmin($this->authenticatedUser($request));

        $orders = Order::query()
            ->with(['user', 'ticketType.event'])
            ->whereHas('ticketType', function ($query) use ($event): void {
                $query->where('event_id', $event->id);
            })
            ->latest()
            ->get();

        return response()->json([
            'event_id' => $event->id,
            'orders' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Place a new order in the waiting queue.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        abort_unless($user->role === User::ROLE_USER, 403, 'Only regular users can create orders.');

        $validated = $request->validate([
            'ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'total_price' => ['prohibited'],
            'status' => ['prohibited'],
            'queue_number' => ['prohibited'],
            'purchased_at' => ['prohibited'],
        ]);

        $order = DB::transaction(function () use ($user, $validated): Order {
            $ticketType = TicketType::query()
                ->with('event')
                ->whereKey($validated['ticket_type_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureEventHasNotStarted($ticketType);
            $this->ensureQuantityCanBeOrdered($ticketType, (int) $validated['quantity']);

            $unitPrice = (float) $ticketType->price;
            $queueNumber = ((int) Order::query()->max('queue_number')) + 1;

            return Order::query()->create([
                'user_id' => $user->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => round($unitPrice * $validated['quantity'], 2),
                'status' => Order::STATUS_QUEUED,
                'queue_number' => $queueNumber,
                'purchased_at' => null,
            ]);
        });

        return response()->json([
            'message' => 'Order created and added to the waiting queue.',
            'order' => new OrderResource($order->load(['user', 'ticketType.event'])),
        ], 201);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->ensureCanAccess($this->authenticatedUser($request), $order);

        return response()->json([
            'order' => new OrderResource($order->load(['user', 'ticketType.event'])),
        ]);
    }

    /**
     * Transition the specified order to another status.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $this->ensureCanAccess($user, $order);
        $this->ensureOnlyStatusIsSubmitted($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $order = DB::transaction(function () use ($order, $user, $validated): Order {
            $lockedOrder = Order::query()
                ->with(['user', 'ticketType.event'])
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureCanAccess($user, $lockedOrder);
            $this->ensureTransitionIsAllowed($user, $lockedOrder, $validated['status']);

            if (in_array($validated['status'], self::FORWARD_STATUSES, true)) {
                $this->ensureEventHasNotStarted($lockedOrder->ticketType);
            }

            if ($validated['status'] === Order::STATUS_PENDING) {
                $ticketType = $this->lockedTicketType($lockedOrder);
                $this->ensureEnoughTicketsAreAvailable($ticketType, $lockedOrder->quantity);
                $ticketType->decrement('quantity_available', $lockedOrder->quantity);
            }

            if ($lockedOrder->status === Order::STATUS_PENDING && in_array($validated['status'], [
                Order::STATUS_CANCELLED,
                Order::STATUS_FAILED,
            ], true)) {
                $this->lockedTicketType($lockedOrder)
                    ->increment('quantity_available', $lockedOrder->quantity);
            }

            $lockedOrder->update([
                'status' => $validated['status'],
                'purchased_at' => $validated['status'] === Order::STATUS_PAID ? now() : null,
            ]);

            return $lockedOrder->refresh()->load(['user', 'ticketType.event']);
        });

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => new OrderResource($order),
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function ensureCanAccess(User $user, Order $order): void
    {
        abort_unless(
            $user->role === User::ROLE_ADMIN || $order->user_id === $user->id,
            403,
            'You cannot access this order.'
        );
    }

    private function ensureAdmin(User $user): void
    {
        abort_unless($user->role === User::ROLE_ADMIN, 403, 'Only administrators can access this order listing.');
    }

    /**
     * @throws ValidationException
     */
    private function ensureTransitionIsAllowed(User $user, Order $order, string $newStatus): void
    {
        $transitions = $user->role === User::ROLE_ADMIN
            ? self::ADMIN_TRANSITIONS
            : self::USER_TRANSITIONS;

        if (! in_array($newStatus, $transitions[$order->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["The transition from {$order->status} to {$newStatus} is not allowed."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureEventHasNotStarted(TicketType $ticketType): void
    {
        if ($ticketType->event->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'event' => ['An order cannot be created or advanced after the event has started.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureQuantityCanBeOrdered(TicketType $ticketType, int $quantity): void
    {
        if ($quantity > $ticketType->max_per_order) {
            throw ValidationException::withMessages([
                'quantity' => ['The requested quantity exceeds the maximum allowed per order.'],
            ]);
        }

        $this->ensureEnoughTicketsAreAvailable($ticketType, $quantity);
    }

    /**
     * @throws ValidationException
     */
    private function ensureEnoughTicketsAreAvailable(TicketType $ticketType, int $quantity): void
    {
        if ($quantity > $ticketType->quantity_available) {
            throw ValidationException::withMessages([
                'quantity' => ['There are not enough tickets available for this ticket type.'],
            ]);
        }
    }

    private function lockedTicketType(Order $order): TicketType
    {
        return TicketType::query()
            ->whereKey($order->ticket_type_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @throws ValidationException
     */
    private function ensureOnlyStatusIsSubmitted(Request $request): void
    {
        $invalidFields = array_diff(array_keys($request->all()), ['status']);

        if ($invalidFields === []) {
            return;
        }

        throw ValidationException::withMessages(
            collect($invalidFields)
                ->mapWithKeys(fn (string $field): array => [
                    $field => ['Only status can be updated on an order.'],
                ])
                ->all()
        );
    }
}
