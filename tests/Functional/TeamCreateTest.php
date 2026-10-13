<?php

namespace App\Tests\Functional;

use App\Entity\Pokemon;
use App\Entity\Team;
use App\Entity\Type;
use App\Tests\Mock\PokeApiMock;
use PHPUnit\Framework\Attributes\DataProvider;

class TeamCreateTest extends FunctionalTestCase
{
    public function testCreatePageShowsTheFormAndSixEmptySlots(): void
    {
        $this->client->request('GET', '/team/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Create team!');
        $this->assertSelectorExists('input[name="team[name]"]');
        $this->assertSelectorExists('input[type="hidden"][name="team[pokemonTokens]"]');
        $this->assertSelectorCount(Team::MAX_POKEMON, '#PokemonTeam .card.free');
        $this->assertSelectorExists('#PokemonTeam[data-new-pokemon-url="/pokemon/new"]');
    }

    public function testCreateATeam(): void
    {
        PokeApiMock::$pokemon = array_fill(1, 1025, PokeApiMock::payload(
            'charizard', 240, 'https://example.com/6.png', ['blaze'], ['fire', 'flying']
        ));

        $this->createTeamThroughTheForm('Team Rocket', 2);

        $this->assertResponseRedirects('/team/list');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Your changes were saved!');

        $team = $this->findTeam('Team Rocket');
        $this->assertNotNull($team);
        $this->assertCount(2, $team->getPokemon());
        foreach ($team->getPokemon() as $pokemon) {
            $this->assertSame('charizard', $pokemon->getName());
            $this->assertSame(240, $pokemon->getBaseExperience());
            $this->assertSame('https://example.com/6.png', $pokemon->getSprite());
            $this->assertSame(['fire', 'flying'], $pokemon->getTypes()->map(fn (Type $t) => $t->getName())->toArray());
        }
        $this->assertEqualsWithDelta(new \DateTime(), $team->getCreatedAt(), 10);
        $this->assertEqualsWithDelta(new \DateTime(), $team->getUpdatedAt(), 10);
    }

    public function testCreateATeamOfSix(): void
    {
        $this->createTeamThroughTheForm('Full team', Team::MAX_POKEMON);

        $this->assertResponseRedirects('/team/list');
        $this->assertCount(Team::MAX_POKEMON, $this->findTeam('Full team')->getPokemon());
    }

    public function testOnlyTheSelectedDrawnPokemonAreSaved(): void
    {
        $this->client->request('GET', '/team/create');
        $first = $this->drawPokemon();
        $this->drawPokemon();
        $third = $this->drawPokemon();

        $this->submitTokens('Picky trainer', [$first['token'], $third['token']]);

        $this->assertResponseRedirects('/team/list');
        $names = $this->findTeam('Picky trainer')->getPokemon()->map(fn (Pokemon $p) => $p->getName())->toArray();
        $this->assertSame([$first['name'], $third['name']], $names);
    }

    public function testTheSavedDataComesFromTheServerNotFromTheBrowser(): void
    {
        // The old version accepted the Pokémon data as JSON from the browser, so it could be forged
        $forged = json_encode(['name' => 'mewtwo', 'baseExperience' => 999999, 'sprite' => 'x', 'abilities' => [], 'types' => []]);

        $this->createTeamThroughTheForm('Cheater', 1, rawTokens: '['.$forged.']');

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'invalid Pokémon');
        $this->assertSame(0, $this->countTeams());
        $this->assertSame(0, $this->getEntityManager()->getRepository(Pokemon::class)->count([]));
    }

    public function testATeamNeedsAtLeastOnePokemon(): void
    {
        $this->createTeamThroughTheForm('Empty team', 0);

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'at least one Pokémon');
        $this->assertSame(0, $this->countTeams());
    }

    public function testATeamCannotHaveMoreThanSixPokemon(): void
    {
        $this->client->request('GET', '/team/create');
        $tokens = [];
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $tokens[] = $this->drawPokemon()['token'];
        }
        $tokens[] = $tokens[0];

        $this->submitTokens('Too many', $tokens);

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'more than 6 Pokémon');
        $this->assertSame(0, $this->countTeams());
    }

    public function testTheSamePokemonCannotBeSubmittedTwice(): void
    {
        $this->client->request('GET', '/team/create');
        $token = $this->drawPokemon()['token'];

        $this->submitTokens('Clones', [$token, $token]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'more than once');
        $this->assertSame(0, $this->countTeams());
    }

    public function testUnknownTokensAreRejected(): void
    {
        $this->createTeamThroughTheForm('Guesser', 0, ['0123456789abcdef']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'invalid Pokémon');
        $this->assertSame(0, $this->countTeams());
    }

    public function testTokensOfAnotherSessionAreRejected(): void
    {
        // Another trainer draws a Pokémon in their own session...
        $this->client->request('GET', '/team/create');
        $otherToken = $this->drawPokemon()['token'];

        // ...and a different trainer (without the session cookie) tries to use it
        $this->client->getCookieJar()->clear();
        $this->createTeamThroughTheForm('Thief', 0, [$otherToken]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countTeams());
    }

    #[DataProvider('malformedTokens')]
    public function testMalformedTokensDoNotCrash(string $raw): void
    {
        $this->createTeamThroughTheForm('Hacker', 1, rawTokens: $raw);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countTeams());
    }

    public static function malformedTokens(): iterable
    {
        yield 'not JSON' => ['not json'];
        yield 'empty string' => [''];
        yield 'JSON object' => ['{"a": "b"}'];
        yield 'JSON string' => ['"abc"'];
        yield 'list of numbers' => ['[1, 2]'];
        yield 'nested lists' => ['[["abc"]]'];
    }

    public function testTheNameIsRequired(): void
    {
        $this->createTeamThroughTheForm('   ', 2);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('#team_name.is-invalid');
        $this->assertSame(0, $this->countTeams());
    }

    public function testTheNameCannotBeTooLong(): void
    {
        $this->createTeamThroughTheForm(str_repeat('a', 256), 1);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countTeams());
    }

    public function testDrawnPokemonAreKeptWhenTheFormHasErrors(): void
    {
        $this->createTeamThroughTheForm('', 2);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorCount(2, '#PokemonTeam .card[data-token]');
        $this->assertSelectorCount(Team::MAX_POKEMON - 2, '#PokemonTeam .card.free');

        // The trainer fixes the name and saves without drawing again
        $tokens = $this->client->getCrawler()->filter('#PokemonTeam .card[data-token]')->extract(['data-token']);
        $this->submitTokens('Fixed name', $tokens);

        $this->assertResponseRedirects('/team/list');
        $this->assertCount(2, $this->findTeam('Fixed name')->getPokemon());
    }

    public function testFullDraftShowsNoEmptySlot(): void
    {
        $this->createTeamThroughTheForm('', Team::MAX_POKEMON);

        $this->assertSelectorCount(Team::MAX_POKEMON, '#PokemonTeam .card[data-token]');
        $this->assertSelectorNotExists('#PokemonTeam .card.free');
    }

    public function testTokensCannotBeReusedForASecondTeam(): void
    {
        $this->client->request('GET', '/team/create');
        $token = $this->drawPokemon()['token'];
        $this->submitTokens('First', [$token]);
        $this->assertResponseRedirects('/team/list');

        $this->submitTokens('Second', [$token]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(1, $this->countTeams());
    }

    public function testCsrfProtection(): void
    {
        $this->client->request('GET', '/team/create');
        $token = $this->drawPokemon()['token'];

        $this->client->request('POST', '/team/create', ['team' => [
            'name' => 'Forged request',
            'pokemonTokens' => json_encode([$token]),
            '_token' => 'csrf-token',
        ]], server: ['HTTP_ORIGIN' => 'https://evil.example.com', 'HTTP_REFERER' => 'https://evil.example.com/']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countTeams());
    }

    public function testCreateRejectsOtherHttpMethods(): void
    {
        $this->client->request('DELETE', '/team/create');

        $this->assertResponseStatusCodeSame(405);
    }

    /**
     * Submits the create form with the given tokens, keeping the current draft.
     *
     * @param list<string> $tokens
     */
    private function submitTokens(string $name, array $tokens): void
    {
        // Opening the create page would start a new draft, so post directly (forms use a stateless CSRF token)
        $this->client->request('POST', '/team/create', ['team' => [
            'name' => $name,
            'pokemonTokens' => json_encode($tokens),
            '_token' => 'csrf-token',
        ]], server: ['HTTP_ORIGIN' => 'http://localhost']);
    }
}
