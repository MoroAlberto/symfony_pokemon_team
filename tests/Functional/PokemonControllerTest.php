<?php

namespace App\Tests\Functional;

use App\Entity\Team;
use App\Exception\PokeApiException;
use App\Tests\Mock\PokeApiMock;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;

class PokemonControllerTest extends FunctionalTestCase
{
    public function testDrawReturnsAPokemonWithAToken(): void
    {
        PokeApiMock::$pokemon = array_fill(1, 1025, PokeApiMock::payload(
            'charizard', 240, 'https://example.com/6.png', ['blaze', 'solar-power'], ['fire', 'flying']
        ));

        $pokemon = $this->drawPokemon($this->openCreatePage());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertMatchesRegularExpression('/^t[0-9a-f]{16}$/', $pokemon['token']);
        $this->assertSame('charizard', $pokemon['name']);
        $this->assertSame(240, $pokemon['baseExperience']);
        $this->assertSame('https://example.com/6.png', $pokemon['sprite']);
        $this->assertSame(['blaze', 'solar-power'], $pokemon['abilities']);
        $this->assertSame(['fire', 'flying'], $pokemon['types']);
    }

    public function testDrawCallsThePokeApi(): void
    {
        $pokemon = $this->drawPokemon($this->openCreatePage());

        $this->assertCount(1, PokeApiMock::$requests);
        $this->assertSame('https://pokeapi.co/api/v2/pokemon/'.$pokemon['apiId'].'/', PokeApiMock::$requests[0]);
    }

    public function testDrawRequiresPost(): void
    {
        $this->client->request('GET', '/pokemon/new');

        $this->assertResponseStatusCodeSame(405);
        $this->assertCount(0, PokeApiMock::$requests);
    }

    public function testDrawRequiresAKnownDraft(): void
    {
        $this->openCreatePage();

        $response = $this->drawPokemon('0123456789abcdef');

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('create it again', $response['error']);
        $this->assertCount(0, PokeApiMock::$requests);
    }

    public function testDrawWithAnInvalidBodyReturns400(): void
    {
        $this->openCreatePage();

        $this->client->request('POST', '/pokemon/new', server: ['CONTENT_TYPE' => 'application/json'], content: 'not json');

        $this->assertResponseStatusCodeSame(400);
        $this->assertCount(0, PokeApiMock::$requests);
    }

    public function testATrainerCannotDrawMoreThanSixPokemon(): void
    {
        $draftId = $this->openCreatePage();
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->drawPokemon($draftId);
            $this->assertResponseIsSuccessful();
        }

        $response = $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('more than 6', $response['error']);
        $this->assertCount(Team::MAX_POKEMON, PokeApiMock::$requests, 'The PokeAPI should not be called');
    }

    public function testALostResponseDoesNotBlockTheTeam(): void
    {
        // Review point 2: the server stored a Pokémon whose response never reached the page
        $draftId = $this->openCreatePage();
        $shown = [];
        for ($i = 0; $i < Team::MAX_POKEMON - 1; ++$i) {
            $shown[] = $this->drawPokemon($draftId, $shown)['token'];
        }
        $this->drawPokemon($draftId, $shown);

        // The page still shows a free slot, so the next catch must work
        $last = $this->drawPokemon($draftId, $shown);

        $this->assertResponseIsSuccessful();
        $this->submitTokens('Lucky team', [...$shown, $last['token']], $draftId);
        $this->assertResponseRedirects('/team/list');
        $this->assertCount(Team::MAX_POKEMON, $this->findTeam('Lucky team')->getPokemon());
    }

    public function testEachCreatePageHasItsOwnSixSlots(): void
    {
        // Review point 2: two tabs share the session but must not share the slots
        $firstTab = $this->openCreatePage();
        $secondTab = $this->openCreatePage();
        for ($i = 0; $i < 3; ++$i) {
            $this->drawPokemon($firstTab);
            $this->drawPokemon($secondTab);
        }

        for ($i = 0; $i < 3; ++$i) {
            $this->drawPokemon($firstTab);
            $this->assertResponseIsSuccessful();
        }
    }

    public function testPokeApiErrorReturns502(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 500;

        $response = $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
        $this->assertStringContainsString('not available', $response['error']);
    }

    public function testPokeApiErrorIsLogged(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 500;

        $this->drawPokemon($draftId);

        $errors = $this->loggedErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Error with pokemon API 500', $errors[0]->message);
        $this->assertInstanceOf(PokeApiException::class, $errors[0]->context['exception']);
    }

    public function testInvalidDraftIsNotLoggedAsAnError(): void
    {
        $this->openCreatePage();

        $this->drawPokemon('0123456789abcdef');

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame([], $this->loggedErrors());
    }

    public function testPokeApiIsCalledWithTimeouts(): void
    {
        $this->drawPokemon($this->openCreatePage());

        // The user waits for the response: a slow PokeAPI must not block the page for a minute (PHP's default)
        $this->assertSame(3.0, PokeApiMock::$lastOptions['timeout']);
        $this->assertSame(5.0, PokeApiMock::$lastOptions['max_duration']);
    }

    public function testATemporaryPokeApiErrorIsRetried(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 502;
        PokeApiMock::$failures = 1;

        $pokemon = $this->drawPokemon($draftId);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $pokemon);
        $this->assertCount(2, PokeApiMock::$requests);
    }

    public function testATemporaryNetworkErrorIsRetried(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithNetworkError = true;
        PokeApiMock::$failures = 1;

        $this->drawPokemon($draftId);

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, PokeApiMock::$requests);
    }

    public function testPokeApiIsRetriedOnlyOnce(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 500;

        $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
        $this->assertCount(2, PokeApiMock::$requests);
    }

    public function testAnOverloadedPokeApiIsNotRetried(): void
    {
        // A 503 usually asks to come back later (Retry-After): waiting for it would block the page
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 503;

        $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
        $this->assertCount(1, PokeApiMock::$requests);
    }

    public function testAMissingPokemonIsNotRetried(): void
    {
        // A 404 will not change by asking again
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 404;

        $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
        $this->assertCount(1, PokeApiMock::$requests);
    }

    public function testPokeApiNetworkErrorReturns502(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithNetworkError = true;

        $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
    }

    public function testUnexpectedPokeApiDataReturns502(): void
    {
        // Review point 6: a 200 response with an unexpected body used to crash with a 500
        $draftId = $this->openCreatePage();
        PokeApiMock::$pokemon = array_fill(1, 1025, ['name' => 'missingno']);

        $response = $this->drawPokemon($draftId);

        $this->assertResponseStatusCodeSame(502);
        $this->assertStringContainsString('not available', $response['error']);
    }

    public function testAFailedDrawDoesNotUseASlot(): void
    {
        $draftId = $this->openCreatePage();
        PokeApiMock::$failWithStatus = 500;
        $this->drawPokemon($draftId);
        PokeApiMock::$failWithStatus = null;

        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->drawPokemon($draftId);
            $this->assertResponseIsSuccessful();
        }
    }

    /**
     * Records of level error or above logged during the last request.
     *
     * @return list<LogRecord>
     */
    private function loggedErrors(): array
    {
        /** @var TestHandler $handler */
        $handler = static::getContainer()->get('monolog.handler.test');

        return array_values(array_filter($handler->getRecords(), fn (LogRecord $record) => $record->level->isHigherThan(Level::Warning)));
    }
}
