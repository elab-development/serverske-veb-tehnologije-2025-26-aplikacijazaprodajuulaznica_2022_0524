<?php

use Illuminate\Support\Facades\Http;

test('external events can be fetched from wikidata without an api key', function () {
    Http::fake([
        'https://query.wikidata.org/sparql*' => Http::response([
            'head' => [
                'vars' => ['event', 'eventLabel', 'startDate'],
            ],
            'results' => [
                'bindings' => [
                    [
                        'event' => ['type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q123456'],
                        'eventLabel' => ['type' => 'literal', 'value' => 'Belgrade Music Festival'],
                        'eventDescription' => ['type' => 'literal', 'value' => 'Annual music festival'],
                        'startDate' => ['type' => 'literal', 'value' => '2026-09-20T18:00:00Z'],
                        'endDate' => ['type' => 'literal', 'value' => '2026-09-22T22:00:00Z'],
                        'locationLabel' => ['type' => 'literal', 'value' => 'Belgrade'],
                        'countryLabel' => ['type' => 'literal', 'value' => 'Serbia'],
                        'coordinates' => ['type' => 'literal', 'value' => 'Point(20.46 44.82)'],
                        'image' => ['type' => 'uri', 'value' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Festival.jpg'],
                    ],
                ],
            ],
        ]),
    ]);

    $this->getJson('/api/external/events?limit=10&offset=0&language=sr')
        ->assertOk()
        ->assertJsonPath('source', 'Wikidata Query Service')
        ->assertJsonPath('events.0.external_id', 'Q123456')
        ->assertJsonPath('events.0.title', 'Belgrade Music Festival')
        ->assertJsonPath('events.0.starts_at', '2026-09-20T18:00:00Z')
        ->assertJsonPath('events.0.location.name', 'Belgrade')
        ->assertJsonPath('events.0.location.country', 'Serbia')
        ->assertJsonPath('events.0.location.latitude', 44.82)
        ->assertJsonPath('events.0.location.longitude', 20.46)
        ->assertJsonPath('pagination.returned', 1)
        ->assertJsonPath('language', 'sr');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://query.wikidata.org/sparql')
            && $request['format'] === 'json'
            && str_contains($request['query'], 'FILTER(?startDate >= NOW())')
            && str_contains($request['query'], 'LIMIT 10')
            && str_contains($request['query'], 'OFFSET 0')
            && str_contains($request['query'], 'wikibase:language "sr,en"')
            && ! isset($request['apikey'])
            && ! isset($request['api_key'])
            && $request->hasHeader('User-Agent')
    );
});

test('external event query parameters are validated', function () {
    Http::fake();

    $this->getJson('/api/external/events?limit=100&offset=501&language=de')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'limit',
            'offset',
            'language',
        ]);

    Http::assertNothingSent();
});

test('external event endpoint handles an empty wikidata result', function () {
    Http::fake([
        'https://query.wikidata.org/sparql*' => Http::response([
            'results' => ['bindings' => []],
        ]),
    ]);

    $this->getJson('/api/external/events')
        ->assertOk()
        ->assertJsonCount(0, 'events')
        ->assertJsonPath('pagination.returned', 0)
        ->assertJsonPath('pagination.has_more', false);
});

test('external event endpoint handles wikidata errors', function () {
    Http::fake([
        'https://query.wikidata.org/sparql*' => Http::response('Query timeout', 500),
    ]);

    $this->getJson('/api/external/events')
        ->assertStatus(502)
        ->assertJsonPath('message', 'External event service returned an error.')
        ->assertJsonPath('status', 500);
});
