<?php

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fake PokeAPI used as the HTTP client of the test environment (see framework.http_client.mock_response_factory).
 *
 * State is static because the kernel, and so this service, is rebuilt between requests of the test client.
 */
class PokeApiMock
{
    /** @var array<int, array> Pokémon returned for a given PokeAPI id, others are generated */
    public static array $pokemon = [];
    /** HTTP status to return instead of a Pokémon, or null for a successful response */
    public static ?int $failWithStatus = null;
    public static bool $failWithNetworkError = false;
    /** @var list<string> URLs requested so far */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$pokemon = [];
        self::$failWithStatus = null;
        self::$failWithNetworkError = false;
        self::$requests = [];
    }

    /**
     * Builds a PokeAPI-like payload.
     */
    public static function payload(
        string $name,
        ?int $baseExperience = 100,
        ?string $sprite = 'https://example.com/sprite.png',
        array $abilities = ['overgrow'],
        array $types = ['grass'],
    ): array {
        return [
            'name' => $name,
            'base_experience' => $baseExperience,
            'sprites' => ['front_default' => $sprite],
            'abilities' => array_map(fn (string $a) => ['ability' => ['name' => $a]], $abilities),
            'types' => array_map(fn (string $t) => ['type' => ['name' => $t]], $types),
        ];
    }

    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        self::$requests[] = $url;

        if (self::$failWithNetworkError) {
            return new MockResponse([new \RuntimeException('Network error')]);
        }
        if (null !== self::$failWithStatus) {
            return new MockResponse('Error', ['http_code' => self::$failWithStatus]);
        }

        preg_match('#/pokemon/(\d+)/$#', $url, $matches);
        $id = (int) $matches[1];
        $payload = self::$pokemon[$id] ?? self::payload('pokemon-'.$id, $id, 'https://example.com/'.$id.'.png');

        return new MockResponse(json_encode($payload), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
