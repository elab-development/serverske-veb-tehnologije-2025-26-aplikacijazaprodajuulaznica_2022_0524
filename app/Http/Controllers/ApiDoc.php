<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Billetterie API",
 *     version="1.0.0",
 *     description="REST API za prodaju ulaznica. API koristi JSON odgovore, Sanctum Bearer tokene za zasticene rute, red cekanja za porudzbine i javni Wikidata servis za preuzimanje eksternih dogadjaja."
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API base path"
 * )
 *
 * @OA\Tag(name="Auth", description="Registracija, prijava i odjava korisnika")
 * @OA\Tag(name="Users", description="Podaci o korisnicima i njihove porudzbine")
 * @OA\Tag(name="Events", description="Javni pregled i administratorsko upravljanje dogadjajima")
 * @OA\Tag(name="Ticket Types", description="Tipovi karata dostupni za dogadjaje")
 * @OA\Tag(name="Orders", description="Red cekanja i porudzbine karata")
 * @OA\Tag(name="External", description="Javno dostupni dogadjaji sa Wikidata servisa bez API kljuca")
 * @OA\Tag(name="Exports", description="CSV eksport podataka")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum token",
 *     description="Uneti token dobijen kroz /register ili /login. Authorization: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="MessageResponse",
 *     type="object",
 *     required={"message"},
 *
 *     @OA\Property(property="message", type="string", example="Operation completed successfully.")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     required={"message","errors"},
 *
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         additionalProperties=@OA\AdditionalProperties(type="array", @OA\Items(type="string"))
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id","name","email","role"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Marko Petrovic"),
 *     @OA\Property(property="email", type="string", format="email", example="marko.petrovic@example.com"),
 *     @OA\Property(property="role", type="string", enum={"admin","user"}, example="user"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Event",
 *     type="object",
 *     required={"id","title","location","starts_at"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Belgrade Music Festival"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Open-air music event."),
 *     @OA\Property(property="location", type="string", example="Kalemegdan Fortress, Belgrade"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-12-20T19:00:00.000000Z"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2026-12-20T23:00:00.000000Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="ticket_types", type="array", @OA\Items(ref="#/components/schemas/TicketType"))
 * )
 *
 * @OA\Schema(
 *     schema="TicketType",
 *     type="object",
 *     required={"id","event_id","name","price","quantity_total","quantity_available","max_per_order"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Regular"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Regular admission ticket."),
 *     @OA\Property(property="price", type="string", example="35.00"),
 *     @OA\Property(property="quantity_total", type="integer", example=800),
 *     @OA\Property(property="quantity_available", type="integer", example=620),
 *     @OA\Property(property="max_per_order", type="integer", example=6),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     required={"id","user_id","ticket_type_id","quantity","unit_price","total_price","status","queue_number"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="ticket_type_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="unit_price", type="string", example="35.00"),
 *     @OA\Property(property="total_price", type="string", example="70.00"),
 *     @OA\Property(property="status", type="string", enum={"queued","processing","pending","paid","cancelled","failed"}, example="queued"),
 *     @OA\Property(property="queue_number", type="integer", example=15),
 *     @OA\Property(property="purchased_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="user", ref="#/components/schemas/User", nullable=true),
 *     @OA\Property(property="ticket_type", ref="#/components/schemas/TicketType", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ExternalEventLocation",
 *     type="object",
 *
 *     @OA\Property(property="name", type="string", nullable=true, example="Belgrade"),
 *     @OA\Property(property="country", type="string", nullable=true, example="Serbia"),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true, example=44.82),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true, example=20.46)
 * )
 *
 * @OA\Schema(
 *     schema="ExternalEvent",
 *     type="object",
 *
 *     @OA\Property(property="external_id", type="string", nullable=true, example="Q123456"),
 *     @OA\Property(property="title", type="string", nullable=true, example="Belgrade Music Festival"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Annual music festival"),
 *     @OA\Property(property="url", type="string", format="uri", nullable=true, example="http://www.wikidata.org/entity/Q123456"),
 *     @OA\Property(property="image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="starts_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="location", ref="#/components/schemas/ExternalEventLocation")
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *     type="object",
 *     required={"data","access_token","token_type"},
 *
 *     @OA\Property(property="data", ref="#/components/schemas/User"),
 *     @OA\Property(property="access_token", type="string", example="1|plain-text-token"),
 *     @OA\Property(property="token_type", type="string", example="Bearer")
 * )
 *
 * @OA\Schema(
 *     schema="EventCreateRequest",
 *     type="object",
 *     required={"title","location","starts_at"},
 *
 *     @OA\Property(property="title", type="string", maxLength=255, example="Belgrade Music Festival"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Open-air music event."),
 *     @OA\Property(property="location", type="string", maxLength=255, example="Kalemegdan Fortress, Belgrade"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-12-20T19:00:00Z"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2026-12-20T23:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="EventUpdateRequest",
 *     type="object",
 *
 *     @OA\Property(property="title", type="string", maxLength=255, example="Updated Music Festival"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Updated description."),
 *     @OA\Property(property="location", type="string", maxLength=255, example="Belgrade Arena"),
 *     @OA\Property(property="starts_at", type="string", format="date-time", example="2026-12-21T19:00:00Z"),
 *     @OA\Property(property="ends_at", type="string", format="date-time", nullable=true, example="2026-12-21T23:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="TicketTypeCreateRequest",
 *     type="object",
 *     required={"event_id","name","price","quantity_total","quantity_available","max_per_order"},
 *
 *     @OA\Property(property="event_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", maxLength=255, example="Regular"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Regular admission ticket."),
 *     @OA\Property(property="price", type="number", format="float", minimum=0, example=35),
 *     @OA\Property(property="quantity_total", type="integer", minimum=1, example=800),
 *     @OA\Property(property="quantity_available", type="integer", minimum=0, example=800),
 *     @OA\Property(property="max_per_order", type="integer", minimum=1, example=6)
 * )
 *
 * @OA\Schema(
 *     schema="TicketTypeUpdateRequest",
 *     type="object",
 *     description="event_id nije dozvoljeno menjati.",
 *
 *     @OA\Property(property="name", type="string", maxLength=255, example="Regular Plus"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Updated ticket description."),
 *     @OA\Property(property="price", type="number", format="float", minimum=0, example=40),
 *     @OA\Property(property="quantity_total", type="integer", minimum=1, example=850),
 *     @OA\Property(property="quantity_available", type="integer", minimum=0, example=700),
 *     @OA\Property(property="max_per_order", type="integer", minimum=1, example=5)
 * )
 *
 * @OA\Schema(
 *     schema="OrderCreateRequest",
 *     type="object",
 *     required={"ticket_type_id","quantity"},
 *     description="Cene, status, broj u redu i korisnik odredjuju se na serveru.",
 *
 *     @OA\Property(property="ticket_type_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", minimum=1, example=2)
 * )
 *
 * @OA\Schema(
 *     schema="OrderStatusUpdateRequest",
 *     type="object",
 *     required={"status"},
 *
 *     @OA\Property(property="status", type="string", enum={"queued","processing","pending","paid","cancelled","failed"}, example="cancelled")
 * )
 *
 * @OA\Post(
 *     path="/register",
 *     tags={"Auth"},
 *     summary="Registracija korisnika",
 *     description="Kreira nalog sa user ulogom i vraca Sanctum Bearer token. Polje role nije dozvoljeno u zahtevu.",
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"name","email","password"},
 *
 *         @OA\Property(property="name", type="string", maxLength=255, example="Petar Petrovic"),
 *         @OA\Property(property="email", type="string", format="email", example="petar@example.com"),
 *         @OA\Property(property="password", type="string", format="password", minLength=8, example="password123")
 *     )),
 *
 *     @OA\Response(response=201, description="User registered", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/login",
 *     tags={"Auth"},
 *     summary="Prijava korisnika",
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"email","password"},
 *
 *         @OA\Property(property="email", type="string", format="email", example="petar@example.com"),
 *         @OA\Property(property="password", type="string", format="password", example="password123")
 *     )),
 *
 *     @OA\Response(response=200, description="User logged in", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
 *     @OA\Response(response=401, description="Wrong credentials", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/logout",
 *     tags={"Auth"},
 *     summary="Odjava korisnika",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Token revoked", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Get(
 *     path="/user",
 *     tags={"Users"},
 *     summary="Trenutno ulogovani korisnik",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Authenticated user", @OA\JsonContent(ref="#/components/schemas/User")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Get(
 *     path="/external/events",
 *     tags={"External"},
 *     summary="Buduci dogadjaji sa Wikidata servisa",
 *     description="Javna ruta koja poziva Wikidata Query Service. Ne koristi API kljuc, token niti autentikaciju.",
 *
 *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=25, default=10)),
 *     @OA\Parameter(name="offset", in="query", required=false, @OA\Schema(type="integer", minimum=0, maximum=500, default=0)),
 *     @OA\Parameter(name="language", in="query", required=false, @OA\Schema(type="string", enum={"en","sr"}, default="en")),
 *
 *     @OA\Response(response=200, description="External events", @OA\JsonContent(
 *
 *         @OA\Property(property="source", type="string", example="Wikidata Query Service"),
 *         @OA\Property(property="events", type="array", @OA\Items(ref="#/components/schemas/ExternalEvent")),
 *         @OA\Property(property="pagination", type="object",
 *             @OA\Property(property="offset", type="integer", example=0),
 *             @OA\Property(property="limit", type="integer", example=10),
 *             @OA\Property(property="returned", type="integer", example=10),
 *             @OA\Property(property="has_more", type="boolean", example=true)
 *         ),
 *         @OA\Property(property="language", type="string", enum={"en","sr"}, example="en")
 *     )),
 *
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
 *     @OA\Response(response=502, description="External service error", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Get(
 *     path="/events/export",
 *     tags={"Events","Exports"},
 *     summary="CSV eksport dogadjaja",
 *     description="Javna ruta. Eksportuje sve dogadjaje, broj tipova karata i zbirne kolicine dostupnih i ukupnih karata.",
 *
 *     @OA\Response(
 *         response=200,
 *         description="CSV file",
 *
 *         @OA\MediaType(
 *             mediaType="text/csv",
 *
 *             @OA\Schema(type="string", example="id,title,description,location,starts_at,ends_at,ticket_types_count,tickets_total,tickets_available,created_at,updated_at")
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/events",
 *     tags={"Events"},
 *     summary="Lista dogadjaja",
 *     description="Javna ruta sa pretragom, filterima datuma, sortiranjem i paginacijom.",
 *
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=255, example="music")),
 *     @OA\Parameter(name="location", in="query", required=false, @OA\Schema(type="string", maxLength=255, example="Belgrade")),
 *     @OA\Parameter(name="starts_from", in="query", required=false, @OA\Schema(type="string", format="date-time")),
 *     @OA\Parameter(name="starts_until", in="query", required=false, @OA\Schema(type="string", format="date-time")),
 *     @OA\Parameter(name="sort_by", in="query", required=false, @OA\Schema(type="string", enum={"title","location","starts_at","ends_at","created_at","updated_at"}, default="starts_at")),
 *     @OA\Parameter(name="sort_direction", in="query", required=false, @OA\Schema(type="string", enum={"asc","desc"}, default="asc")),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=50, default=10)),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1, default=1)),
 *
 *     @OA\Response(response=200, description="Paginated events", @OA\JsonContent(
 *
 *         @OA\Property(property="events", type="array", @OA\Items(ref="#/components/schemas/Event")),
 *         @OA\Property(property="pagination", type="object",
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="last_page", type="integer", example=3),
 *             @OA\Property(property="per_page", type="integer", example=10),
 *             @OA\Property(property="total", type="integer", example=25)
 *         ),
 *         @OA\Property(property="sort", type="object",
 *             @OA\Property(property="by", type="string", example="starts_at"),
 *             @OA\Property(property="direction", type="string", example="asc")
 *         )
 *     )),
 *
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/events",
 *     tags={"Events"},
 *     summary="Kreiranje dogadjaja",
 *     description="Samo administrator. Datum pocetka mora biti u buducnosti, a kraj nakon pocetka.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/EventCreateRequest")),
 *
 *     @OA\Response(response=201, description="Event created", @OA\JsonContent(
 *
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="event", ref="#/components/schemas/Event")
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage events", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Pregled jednog dogadjaja",
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Event details", @OA\JsonContent(@OA\Property(property="event", ref="#/components/schemas/Event"))),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Put(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Azuriranje dogadjaja",
 *     description="Samo administrator. Dogadjaj ne sme biti poceo i ne sme imati porudzbine.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/EventUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Event updated", @OA\JsonContent(
 *
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="event", ref="#/components/schemas/Event")
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage events", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Event cannot be changed or validation failed", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Delimicno azuriranje dogadjaja",
 *     description="Ista pravila kao za PUT: samo administrator, dogadjaj nije poceo i nema porudzbine.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/EventUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Event updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage events", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Event cannot be changed or validation failed", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Delete(
 *     path="/events/{event}",
 *     tags={"Events"},
 *     summary="Brisanje dogadjaja",
 *     description="Samo administrator. Dogadjaj ne sme biti poceo i ne sme imati porudzbine.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Event deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage events", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Event cannot be deleted", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/ticket-types",
 *     tags={"Ticket Types"},
 *     summary="Tipovi karata za dogadjaj",
 *     description="Javna nested ruta bez filtera i paginacije.",
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Ticket types", @OA\JsonContent(
 *
 *         @OA\Property(property="event_id", type="integer", example=1),
 *         @OA\Property(property="ticket_types", type="array", @OA\Items(ref="#/components/schemas/TicketType"))
 *     )),
 *
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Get(
 *     path="/ticket-types/{ticketType}",
 *     tags={"Ticket Types"},
 *     summary="Pregled jednog tipa karte",
 *     description="Javna ruta.",
 *
 *     @OA\Parameter(name="ticketType", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Ticket type details", @OA\JsonContent(@OA\Property(property="ticket_type", ref="#/components/schemas/TicketType"))),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Post(
 *     path="/ticket-types",
 *     tags={"Ticket Types"},
 *     summary="Kreiranje tipa karte",
 *     description="Samo administrator i samo pre pocetka dogadjaja.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/TicketTypeCreateRequest")),
 *
 *     @OA\Response(response=201, description="Ticket type created", @OA\JsonContent(
 *
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="ticket_type", ref="#/components/schemas/TicketType")
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage ticket types", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Validation error or event already started", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Put(
 *     path="/ticket-types/{ticketType}",
 *     tags={"Ticket Types"},
 *     summary="Azuriranje tipa karte",
 *     description="Samo administrator. Dogadjaj nije poceo, tip karte nema porudzbine i event_id se ne moze menjati.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="ticketType", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/TicketTypeUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Ticket type updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage ticket types", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Ticket type cannot be changed or validation failed", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/ticket-types/{ticketType}",
 *     tags={"Ticket Types"},
 *     summary="Delimicno azuriranje tipa karte",
 *     description="Ista pravila kao za PUT.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="ticketType", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/TicketTypeUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Ticket type updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage ticket types", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Ticket type cannot be changed or validation failed", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Delete(
 *     path="/ticket-types/{ticketType}",
 *     tags={"Ticket Types"},
 *     summary="Brisanje tipa karte",
 *     description="Samo administrator. Dogadjaj nije poceo i tip karte nema porudzbine.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="ticketType", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Ticket type deleted", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can manage ticket types", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Ticket type cannot be deleted", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/orders",
 *     tags={"Orders"},
 *     summary="Pregled porudzbina",
 *     description="Ulogovani administrator vidi sve porudzbine, a user samo svoje. Nema filtera ni paginacije.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Orders list", @OA\JsonContent(
 *
 *         @OA\Property(property="orders", type="array", @OA\Items(ref="#/components/schemas/Order"))
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Post(
 *     path="/orders",
 *     tags={"Orders"},
 *     summary="Kreiranje porudzbine u redu cekanja",
 *     description="Samo user moze kreirati porudzbinu. Cena, korisnik, status queued i queue_number odredjuju se na serveru.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderCreateRequest")),
 *
 *     @OA\Response(response=201, description="Order queued", @OA\JsonContent(
 *
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="order", ref="#/components/schemas/Order")
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only regular users can create orders", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Ticket type not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Validation, availability or event date error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Pregled jedne porudzbine",
 *     description="Administrator moze videti svaku porudzbinu, a user samo svoju.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Order details", @OA\JsonContent(@OA\Property(property="order", ref="#/components/schemas/Order"))),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Order belongs to another user", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Put(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Promena statusa porudzbine",
 *     description="Dozvoljeno je poslati samo status. User: queued->cancelled; pending->paid/cancelled. Admin: queued->processing/cancelled/failed; processing->pending/cancelled/failed; pending->paid/cancelled/failed.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderStatusUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Order status updated", @OA\JsonContent(
 *
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="order", ref="#/components/schemas/Order")
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Order belongs to another user", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Invalid field or status transition", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/orders/{order}",
 *     tags={"Orders"},
 *     summary="Delimicna promena statusa porudzbine",
 *     description="Ista pravila tranzicije kao za PUT. Nijedno drugo polje nije dozvoljeno.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OrderStatusUpdateRequest")),
 *
 *     @OA\Response(response=200, description="Order status updated"),
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Order belongs to another user", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Order not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=422, description="Invalid field or status transition", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Get(
 *     path="/users/{user}/orders",
 *     tags={"Users","Orders"},
 *     summary="Porudzbine odredjenog korisnika",
 *     description="Nested ruta dostupna samo administratoru.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer", example=2)),
 *
 *     @OA\Response(response=200, description="User orders", @OA\JsonContent(
 *
 *         @OA\Property(property="user_id", type="integer", example=2),
 *         @OA\Property(property="orders", type="array", @OA\Items(ref="#/components/schemas/Order"))
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can access this listing", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="User not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 *
 * @OA\Get(
 *     path="/events/{event}/orders",
 *     tags={"Events","Orders"},
 *     summary="Porudzbine za odredjeni dogadjaj",
 *     description="Nested ruta dostupna samo administratoru.",
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *
 *     @OA\Response(response=200, description="Event orders", @OA\JsonContent(
 *
 *         @OA\Property(property="event_id", type="integer", example=1),
 *         @OA\Property(property="orders", type="array", @OA\Items(ref="#/components/schemas/Order"))
 *     )),
 *
 *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=403, description="Only administrators can access this listing", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
 *     @OA\Response(response=404, description="Event not found", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
 * )
 */
class ApiDoc {}
