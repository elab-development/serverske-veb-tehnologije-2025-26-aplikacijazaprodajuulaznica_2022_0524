<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExternalEventDataController extends Controller
{
    private const ENDPOINT = 'https://query.wikidata.org/sparql';

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:500'],
            'language' => ['sometimes', Rule::in(['en', 'sr'])],
        ]);

        $limit = (int) ($validated['limit'] ?? 10);
        $offset = (int) ($validated['offset'] ?? 0);
        $language = $validated['language'] ?? 'en';

        try {
            $response = Http::timeout(30)
                ->accept('application/sparql-results+json')
                ->withHeaders([
                    'User-Agent' => 'BilletterieApp/1.0 (educational Laravel project)',
                ])
                ->get(self::ENDPOINT, [
                    'query' => $this->sparqlQuery($limit, $offset, $language),
                    'format' => 'json',
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'External event service is currently unavailable.',
            ], 502);
        }

        if ($response->failed()) {
            return response()->json([
                'message' => 'External event service returned an error.',
                'status' => $response->status(),
            ], 502);
        }

        $bindings = $response->json('results.bindings', []);

        if (! is_array($bindings)) {
            $bindings = [];
        }

        $events = collect($bindings)
            ->filter(fn (mixed $binding): bool => is_array($binding))
            ->map(fn (array $binding): array => $this->normalizeEvent($binding))
            ->values();

        return response()->json([
            'source' => 'Wikidata Query Service',
            'events' => $events,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'returned' => $events->count(),
                'has_more' => $events->count() === $limit,
            ],
            'language' => $language,
        ]);
    }

    private function sparqlQuery(int $limit, int $offset, string $language): string
    {
        return <<<SPARQL
            SELECT DISTINCT
                ?event
                ?eventLabel
                ?eventDescription
                ?startDate
                ?endDate
                ?locationLabel
                ?countryLabel
                ?coordinates
                ?image
            WHERE {
                {
                    SELECT ?event ?startDate
                    WHERE {
                        ?event wdt:P580 ?startDate .
                        hint:Prior hint:rangeSafe true .
                        FILTER(?startDate >= NOW())
                    }
                    ORDER BY ?startDate
                    LIMIT {$limit}
                    OFFSET {$offset}
                }

                OPTIONAL { ?event wdt:P582 ?endDate . }
                OPTIONAL {
                    ?event wdt:P276 ?location .
                    OPTIONAL { ?location wdt:P17 ?country . }
                    OPTIONAL { ?location wdt:P625 ?coordinates . }
                }
                OPTIONAL { ?event wdt:P18 ?image . }

                SERVICE wikibase:label {
                    bd:serviceParam wikibase:language "{$language},en" .
                }
            }
            ORDER BY ?startDate
            SPARQL;
    }

    /**
     * @param  array<string, mixed>  $binding
     * @return array<string, mixed>
     */
    private function normalizeEvent(array $binding): array
    {
        $url = $this->bindingValue($binding, 'event');
        $coordinates = $this->coordinates($this->bindingValue($binding, 'coordinates'));

        return [
            'external_id' => $url === null ? null : Str::afterLast($url, '/'),
            'title' => $this->bindingValue($binding, 'eventLabel'),
            'description' => $this->bindingValue($binding, 'eventDescription'),
            'url' => $url,
            'image_url' => $this->bindingValue($binding, 'image'),
            'starts_at' => $this->bindingValue($binding, 'startDate'),
            'ends_at' => $this->bindingValue($binding, 'endDate'),
            'location' => [
                'name' => $this->bindingValue($binding, 'locationLabel'),
                'country' => $this->bindingValue($binding, 'countryLabel'),
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $binding
     */
    private function bindingValue(array $binding, string $key): ?string
    {
        $value = data_get($binding, "{$key}.value");

        return is_string($value) ? $value : null;
    }

    /**
     * @return array{latitude: ?float, longitude: ?float}
     */
    private function coordinates(?string $point): array
    {
        if ($point !== null && preg_match('/^Point\((-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)\)$/', $point, $matches) === 1) {
            return [
                'latitude' => (float) $matches[2],
                'longitude' => (float) $matches[1],
            ];
        }

        return [
            'latitude' => null,
            'longitude' => null,
        ];
    }
}
