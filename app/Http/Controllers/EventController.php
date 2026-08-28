<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private const SORTABLE_FIELDS = [
        'title',
        'location',
        'starts_at',
        'ends_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'starts_from' => ['sometimes', 'date'],
            'starts_until' => ['sometimes', 'date'],
            'sort_by' => ['sometimes', Rule::in(self::SORTABLE_FIELDS)],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $sortBy = $validated['sort_by'] ?? 'starts_at';
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        $query = Event::query();

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['location'])) {
            $query->where('location', 'like', "%{$validated['location']}%");
        }

        if (isset($validated['starts_from'])) {
            $query->where('starts_at', '>=', $validated['starts_from']);
        }

        if (isset($validated['starts_until'])) {
            $query->where('starts_at', '<=', $validated['starts_until']);
        }

        $events = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        return response()->json([
            'events' => EventResource::collection($events->getCollection()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
            'sort' => [
                'by' => $sortBy,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Export all events and ticket availability totals to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $filename = 'events-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id',
                'title',
                'description',
                'location',
                'starts_at',
                'ends_at',
                'ticket_types_count',
                'tickets_total',
                'tickets_available',
                'created_at',
                'updated_at',
            ]);

            Event::query()
                ->withCount('ticketTypes')
                ->withSum('ticketTypes as tickets_total', 'quantity_total')
                ->withSum('ticketTypes as tickets_available', 'quantity_available')
                ->orderBy('id')
                ->chunk(200, function ($events) use ($handle): void {
                    foreach ($events as $event) {
                        fputcsv($handle, [
                            $event->id,
                            $event->title,
                            $event->description,
                            $event->location,
                            $event->starts_at?->toDateTimeString(),
                            $event->ends_at?->toDateTimeString(),
                            $event->ticket_types_count,
                            $event->tickets_total ?? 0,
                            $event->tickets_available ?? 0,
                            $event->created_at?->toDateTimeString(),
                            $event->updated_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate($this->rules());
        $this->ensureValidDateRange($validated);

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => new EventResource($event),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->ensureHasNotStarted($event);
        $this->ensureHasNoOrders($event);

        $validated = $request->validate($this->rules(updating: true));
        $this->ensureValidDateRange($validated, $event);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => new EventResource($event->refresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->ensureAdmin($request);
        $this->ensureHasNotStarted($event);
        $this->ensureHasNoOrders($event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'location' => [$required, 'string', 'max:255'],
            'starts_at' => [$required, 'date', 'after:now'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function ensureValidDateRange(array $validated, ?Event $event = null): void
    {
        $startsAt = Carbon::parse($validated['starts_at'] ?? $event?->starts_at);
        $endsAt = array_key_exists('ends_at', $validated)
            ? $validated['ends_at']
            : $event?->ends_at;

        if ($endsAt !== null && Carbon::parse($endsAt)->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['The ends at field must be after starts at.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureHasNotStarted(Event $event): void
    {
        if ($event->starts_at->lte(now())) {
            throw ValidationException::withMessages([
                'event' => ['An event that has already started cannot be changed.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureHasNoOrders(Event $event): void
    {
        if ($event->ticketTypes()->whereHas('orders')->exists()) {
            throw ValidationException::withMessages([
                'event' => ['An event with existing orders cannot be changed.'],
            ]);
        }
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403, 'Only administrators can manage events.');
    }
}
