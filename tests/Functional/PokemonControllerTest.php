<?php

namespace App\Tests\Functional;

use App\Entity\Team;
use App\Tests\Mock\PokeApiMock;

class PokemonControllerTest extends FunctionalTestCase
{
    public function testDrawReturnsAPokemonWithAToken(): void
    {
        PokeApiMock::$pokemon = array_fill(1, 1025, PokeApiMock::payload(
            'charizard', 240, 'https://example.com/6.png', ['blaze', 'solar-power'], ['fire', 'flying']
        ));

        $pokemon = $this->drawPokemon();

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $pokemon['token']);
        $this->assertSame('charizard', $pokemon['name']);
        $this->assertSame(240, $pokemon['baseExperience']);
        $this->assertSame('https://example.com/6.png', $pokemon['sprite']);
        $this->assertSame(['blaze', 'solar-power'], $pokemon['abilities']);
        $this->assertSame(['fire', 'flying'], $pokemon['types']);
    }

    public function testDrawCallsThePokeApi(): void
    {
        $pokemon = $this->drawPokemon();

        $this->assertCount(1, PokeApiMock::$requests);
        $this->assertSame('https://pokeapi.co/api/v2/pokemon/'.$pokemon['apiId'].'/', PokeApiMock::$requests[0]);
    }

    public function testDrawRequiresPost(): void
    {
        $this->client->request('GET', '/pokemon/new');

        $this->assertResponseStatusCodeSame(405);
        $this->assertCount(0, PokeApiMock::$requests);
    }

    public function testATrainerCannotDrawMoreThanSixPokemon(): void
    {
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->drawPokemon();
            $this->assertResponseIsSuccessful();
        }

        $response = $this->drawPokemon();

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('more than 6', $response['error']);
        $this->assertCount(Team::MAX_POKEMON, PokeApiMock::$requests, 'The PokeAPI should not be called');
    }

    public function testOpeningTheCreatePageStartsANewDraft(): void
    {
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->drawPokemon();
        }

        $this->client->request('GET', '/team/create');
        $this->drawPokemon();

        $this->assertResponseIsSuccessful();
    }

    public function testPokeApiErrorReturns502(): void
    {
        PokeApiMock::$failWithStatus = 500;

        $response = $this->drawPokemon();

        $this->assertResponseStatusCodeSame(502);
        $this->assertStringContainsString('not available', $response['error']);
    }

    public function testPokeApiNetworkErrorReturns502(): void
    {
        PokeApiMock::$failWithNetworkError = true;

        $this->drawPokemon();

        $this->assertResponseStatusCodeSame(502);
    }

    public function testAFailedDrawDoesNotUseASlot(): void
    {
        PokeApiMock::$failWithStatus = 500;
        $this->drawPokemon();
        PokeApiMock::$failWithStatus = null;

        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->drawPokemon();
            $this->assertResponseIsSuccessful();
        }
    }
}
