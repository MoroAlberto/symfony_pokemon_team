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
        $this->assertSelectorExists('input[type="hidden"][name="team[draft]"][data-team-draft]');
        $this->assertSelectorCount(Team::MAX_POKEMON, '#PokemonTeam .card.free');
        $this->assertSelectorExists('#PokemonTeam[data-new-pokemon-url="/pokemon/new"]');
    }

    public function testTheBrowserDoesNotRestoreTheHiddenFields(): void
    {
        // Review 3, point 1: browsers restored an old draft id on Back or reload
        $this->client->request('GET', '/team/create');

        $this->assertSelectorExists('input[name="team[draft]"][autocomplete="off"]');
        $this->assertSelectorExists('input[name="team[pokemonTokens]"][autocomplete="off"]');
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
        $draftId = $this->openCreatePage();
        $first = $this->drawPokemon($draftId);
        $this->drawPokemon($draftId);
        $third = $this->drawPokemon($draftId);

        $this->submitTokens('Picky trainer', [$first['token'], $third['token']], $draftId);

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
        $draftId = $this->openCreatePage();
        $tokens = [];
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $tokens[] = $this->drawPokemon($draftId)['token'];
        }
        $tokens[] = $tokens[0];

        $this->submitTokens('Too many', $tokens, $draftId);

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'more than 6 Pokémon');
        $this->assertSame(0, $this->countTeams());
    }

    public function testTheSamePokemonCannotBeSubmittedTwice(): void
    {
        $draftId = $this->openCreatePage();
        $token = $this->drawPokemon($draftId)['token'];

        $this->submitTokens('Clones', [$token, $token], $draftId);

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
        $otherDraftId = $this->openCreatePage();
        $otherToken = $this->drawPokemon($otherDraftId)['token'];

        // ...and a different trainer (without the session cookie) tries to use it
        $this->client->getCookieJar()->clear();
        $this->createTeamThroughTheForm('Thief', 0, [$otherToken]);
        $this->assertResponseStatusCodeSame(422);
        $this->submitTokens('Thief', [$otherToken], $otherDraftId);
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
        $draftId = $this->client->getCrawler()->filter('[data-team-draft]')->attr('value');
        $this->submitTokens('Fixed name', $tokens, $draftId);

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
        $draftId = $this->openCreatePage();
        $token = $this->drawPokemon($draftId)['token'];
        $this->submitTokens('First', [$token], $draftId);
        $this->assertResponseRedirects('/team/list');

        $this->submitTokens('Second', [$token], $draftId);

        $this->assertSame(1, $this->countTeams());
        $this->assertNull($this->findTeam('Second'));
    }

    public function testSubmittingTheSameTeamTwiceShowsItWasSaved(): void
    {
        // Review 2, point 3: a double click on "Save team" used to show an "expired" error for a saved team
        $draftId = $this->openCreatePage();
        $token = $this->drawPokemon($draftId)['token'];
        $this->submitTokens('Double click', [$token], $draftId);
        $this->assertResponseRedirects('/team/list');

        $this->submitTokens('Double click', [$token], $draftId);

        $this->assertResponseRedirects('/team/list');
        $this->client->followRedirect();
        // Review 3, point 5: the second submission is ignored, so it must not look like a success
        $this->assertSelectorTextContains('.alert-warning', 'already saved');
        $this->assertSelectorTextContains('.alert-warning', 'start a new team');
        $this->assertSame(1, $this->countTeams());
    }

    public function testCatchingOnASavedTeamSaysItWasSaved(): void
    {
        // Review 3, point 2: catching after saving (e.g. after Back) said "expired"
        $draftId = $this->openCreatePage();
        $token = $this->drawPokemon($draftId)['token'];
        $this->submitTokens('Saved team', [$token], $draftId);

        $response = $this->drawPokemon($draftId, [$token]);

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('already saved', $response['error']);
    }

    public function testADraftWithPokemonSurvivesOtherPages(): void
    {
        // Review 2, point 1: opening "Create team" 5 more times used to drop the draft in use
        $draftId = $this->openCreatePage();
        $tokens = [$this->drawPokemon($draftId)['token']];
        for ($i = 0; $i < 10; ++$i) {
            $this->openCreatePage();
        }

        $this->submitTokens('Survivor', $tokens, $draftId);

        $this->assertResponseRedirects('/team/list');
    }

    public function testOpeningTheCreatePageInAnotherTabKeepsTheFirstDraft(): void
    {
        // Review point 1: opening "Create new team" in a second tab used to wipe the first tab's Pokémon
        $firstTab = $this->openCreatePage();
        $tokens = [$this->drawPokemon($firstTab)['token'], $this->drawPokemon($firstTab)['token']];

        $this->openCreatePage();
        $this->submitTokens('First tab team', $tokens, $firstTab);

        $this->assertResponseRedirects('/team/list');
        $this->assertCount(2, $this->findTeam('First tab team')->getPokemon());
    }

    public function testAnExpiredDraftIsReplacedByANewOne(): void
    {
        $this->openCreatePage();

        $this->submitTokens('Late team', ['0123456789abcdef'], '0123456789abcdef');

        $this->assertResponseStatusCodeSame(422);
        $this->assertAnySelectorTextContains('div, li, span', 'create it again');
        $newDraftId = $this->client->getCrawler()->filter('[data-team-draft]')->attr('value');
        $this->assertNotSame('0123456789abcdef', $newDraftId);
        $this->drawPokemon($newDraftId);
        $this->assertResponseIsSuccessful();
    }

    public function testTheNameIsSavedWithoutSurroundingSpaces(): void
    {
        $this->createTeamThroughTheForm('   Pikachu Squad   ', 1);

        $this->assertResponseRedirects('/team/list');
        $this->assertNotNull($this->findTeam('Pikachu Squad'));
    }

    public function testCsrfProtection(): void
    {
        $draftId = $this->openCreatePage();
        $token = $this->drawPokemon($draftId)['token'];

        $this->client->request('POST', '/team/create', ['team' => [
            'name' => 'Forged request',
            'draft' => $draftId,
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
}
