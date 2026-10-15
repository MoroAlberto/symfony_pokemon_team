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
    /** @var array<int, array<string, mixed>> Pokémon returned for a given PokeAPI id, others are generated */
    public static array $pokemon = [];
    /** HTTP status to return instead of a Pokémon, or null for a successful response */
    public static ?int $failWithStatus = null;
    public static bool $failWithNetworkError = false;
    /** How many of the next requests fail as set above before PokeAPI works again, null for all of them */
    public static ?int $failures = null;
    /** @var list<string> URLs requested so far */
    public static array $requests = [];
    /** @var array<string, mixed> Options of the last request (timeout, max_duration...) */
    public static array $lastOptions = [];

    public static function reset(): void
    {
        self::$pokemon = [];
        self::$failWithStatus = null;
        self::$failWithNetworkError = false;
        self::$failures = null;
        self::$requests = [];
        self::$lastOptions = [];
    }

    /**
     * Builds a PokeAPI-like payload.
     *
     * @param list<string> $abilities
     * @param list<string> $types
     *
     * @return array<string, mixed>
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

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        self::$requests[] = $url;
        self::$lastOptions = $options;

        $fails = (self::$failWithNetworkError || null !== self::$failWithStatus)
            && (null === self::$failures || self::$failures-- > 0);
        if ($fails && self::$failWithNetworkError) {
            // Fails before any response, like a refused connection or a timeout
            return new MockResponse('', ['error' => 'Network error']);
        }
        if ($fails && null !== self::$failWithStatus) {
            return new MockResponse('Error', ['http_code' => self::$failWithStatus]);
        }

        preg_match('#/pokemon/(\d+)/$#', $url, $matches);
        $id = (int) $matches[1];
        $payload = self::$pokemon[$id] ?? self::payload('pokemon-'.$id, $id, 'https://example.com/'.$id.'.png');

        return new MockResponse(json_encode($payload), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
