<?php

namespace App\Tests\Unit\Service;

use App\Exception\PokeApiException;
use App\Repository\AbilityRepository;
use App\Repository\TypeRepository;
use App\Service\PokemonService;
use App\Service\TeamService;
use App\Service\TypeService;
use App\Tests\Mock\PokeApiMock;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PokemonServiceTest extends TestCase
{
    public function testFetchRandomPokemonNormalizesThePokeApiResponse(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(PokeApiMock::payload(
            'charizard', 240, 'https://example.com/6.png', ['blaze', 'solar-power'], ['fire', 'flying']
        ))));

        $pokemon = $this->createService($client)->fetchRandomPokemon();

        $this->assertSame('charizard', $pokemon['name']);
        $this->assertSame(240, $pokemon['baseExperience']);
        $this->assertSame('https://example.com/6.png', $pokemon['sprite']);
        $this->assertSame(['blaze', 'solar-power'], $pokemon['abilities']);
        $this->assertSame(['fire', 'flying'], $pokemon['types']);
    }

    public function testFetchRandomPokemonRequestsAnExistingPokemon(): void
    {
        $urls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$urls) {
            $urls[] = $url;

            return new MockResponse(json_encode(PokeApiMock::payload('bulbasaur')));
        });
        $service = $this->createService($client);

        for ($i = 0; $i < 50; ++$i) {
            $pokemon = $service->fetchRandomPokemon();

            $this->assertGreaterThanOrEqual(1, $pokemon['apiId']);
            $this->assertLessThanOrEqual(PokemonService::MAX_POKEMON_ID, $pokemon['apiId']);
            $this->assertSame(PokemonService::API_URL.$pokemon['apiId'].'/', $urls[$i]);
        }
    }

    public function testFetchRandomPokemonHandlesMissingBaseExperienceAndSprite(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(PokeApiMock::payload('new-pokemon', null, null))));

        $pokemon = $this->createService($client)->fetchRandomPokemon();

        $this->assertSame(0, $pokemon['baseExperience']);
        $this->assertSame('', $pokemon['sprite']);
    }

    #[DataProvider('failingResponses')]
    public function testFetchRandomPokemonThrowsWhenThePokeApiFails(MockResponse $response): void
    {
        $this->expectException(PokeApiException::class);

        $this->createService(new MockHttpClient($response))->fetchRandomPokemon();
    }

    public static function failingResponses(): iterable
    {
        yield 'not found' => [new MockResponse('Not Found', ['http_code' => 404])];
        yield 'server error' => [new MockResponse('Error', ['http_code' => 500])];
        yield 'redirect' => [new MockResponse('', ['http_code' => 301])];
        yield 'network error' => [new MockResponse([new \RuntimeException('Network error')])];
        yield 'invalid JSON' => [new MockResponse('not json')];
        // Review point 6: a successful response with an unexpected body
        yield 'empty object' => [new MockResponse('{}')];
        yield 'JSON list' => [new MockResponse('[1, 2]')];
        yield 'missing types' => [new MockResponse('{"name": "missingno", "abilities": []}')];
        yield 'name not a string' => [new MockResponse(json_encode(['name' => 42] + PokeApiMock::payload('x')))];
        yield 'null ability' => [new MockResponse(json_encode(['abilities' => [null]] + PokeApiMock::payload('x')))];
        yield 'type without name' => [new MockResponse(json_encode(['types' => [['type' => []]]] + PokeApiMock::payload('x')))];
        // Review 2, point 8: lists sent as JSON objects
        yield 'abilities as an object' => [new MockResponse(json_encode(['abilities' => ['a' => ['ability' => ['name' => 'static']]]] + PokeApiMock::payload('x')))];
        yield 'types as an object' => [new MockResponse(json_encode(['types' => ['a' => ['type' => ['name' => 'electric']]]] + PokeApiMock::payload('x')))];
        yield 'base experience not a number' => [new MockResponse(json_encode(['base_experience' => 'a lot'] + PokeApiMock::payload('x')))];
    }

    private function createService(MockHttpClient $client): PokemonService
    {
        return new PokemonService(
            $client,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(TypeRepository::class),
            $this->createStub(AbilityRepository::class),
            $this->createStub(TeamService::class),
            $this->createStub(TypeService::class),
        );
    }
}
