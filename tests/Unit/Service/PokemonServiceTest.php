<?php

namespace App\Tests\Unit\Service;

use App\Exception\PokeApiException;
use App\Repository\AbilityRepository;
use App\Repository\TypeRepository;
use App\Service\PokemonService;
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
    }

    private function createService(MockHttpClient $client): PokemonService
    {
        return new PokemonService(
            $client,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(TypeRepository::class),
            $this->createStub(AbilityRepository::class),
        );
    }
}
