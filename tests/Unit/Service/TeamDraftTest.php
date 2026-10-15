<?php

namespace App\Tests\Unit\Service;

use App\Entity\Team;
use App\Exception\InvalidTeamDraftException;
use App\Service\PokemonService;
use App\Service\TeamDraft;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @phpstan-import-type PokemonData from PokemonService
 */
class TeamDraftTest extends TestCase
{
    private RequestStack $requestStack;
    private TeamDraft $teamDraft;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->teamDraft = new TeamDraft($this->requestStack);
    }

    public function testStartCreatesAnEmptyDraft(): void
    {
        $draftId = $this->teamDraft->start();

        $this->assertMatchesRegularExpression('/^d[0-9a-f]{16}$/', $draftId);
        $this->assertTrue($this->teamDraft->exists($draftId));
        $this->assertSame([], $this->teamDraft->all($draftId));
    }

    public function testUnknownDraft(): void
    {
        $this->assertFalse($this->teamDraft->exists('0123456789abcdef'));
        $this->assertSame([], $this->teamDraft->all('0123456789abcdef'));
    }

    public function testAddStoresTheDrawnPokemonUnderANewToken(): void
    {
        $draftId = $this->teamDraft->start();

        $pokemon = $this->teamDraft->add($draftId, fn () => self::pokemon('pikachu'));

        $this->assertMatchesRegularExpression('/^t[0-9a-f]{16}$/', $pokemon['token']);
        $this->assertSame('pikachu', $pokemon['name']);
        $this->assertSame([$pokemon['token'] => self::pokemon('pikachu')], $this->teamDraft->all($draftId));
    }

    public function testTokensAreUnique(): void
    {
        $draftId = $this->teamDraft->start();

        $tokens = $this->fill($draftId, Team::MAX_POKEMON);

        $this->assertCount(Team::MAX_POKEMON, array_unique($tokens));
    }

    public function testAddToAFullDraftFailsWithoutDrawing(): void
    {
        $draftId = $this->teamDraft->start();
        $this->fill($draftId, Team::MAX_POKEMON);

        try {
            $this->teamDraft->add($draftId, fn () => $this->fail('No Pokémon should be drawn for a full team'));
            $this->fail('A full draft should be rejected');
        } catch (InvalidTeamDraftException $e) {
            $this->assertStringContainsString('more than 6 Pokémon', $e->getMessage());
        }
    }

    public function testAddToAnUnknownDraftFails(): void
    {
        $this->expectException(InvalidTeamDraftException::class);

        $this->teamDraft->add('0123456789abcdef', fn () => self::pokemon('pikachu'));
    }

    public function testDraftsAreIndependent(): void
    {
        // Two create pages open at the same time, e.g. in two tabs
        $first = $this->teamDraft->start();
        $second = $this->teamDraft->start();
        $this->fill($first, Team::MAX_POKEMON);

        $this->fill($second, Team::MAX_POKEMON);

        $this->assertCount(Team::MAX_POKEMON, $this->teamDraft->all($first));
        $this->assertCount(Team::MAX_POKEMON, $this->teamDraft->all($second));
    }

    public function testOnlyALimitedNumberOfDraftsIsKept(): void
    {
        $oldest = $this->teamDraft->start();
        for ($i = 0; $i < TeamDraft::MAX_DRAFTS; ++$i) {
            $newest = $this->teamDraft->start();
        }

        $this->assertFalse($this->teamDraft->exists($oldest));
        $this->assertTrue($this->teamDraft->exists($newest));
    }

    public function testADraftWithPokemonSurvivesManyNewPages(): void
    {
        // Review 2, point 1: pressing Reset or opening "Create team" 5 times used to drop the draft in use
        $inUse = $this->teamDraft->start();
        $this->fill($inUse, 4);

        for ($i = 0; $i < 2 * TeamDraft::MAX_DRAFTS; ++$i) {
            $this->teamDraft->start();
        }

        $this->assertTrue($this->teamDraft->exists($inUse));
        $this->assertCount(4, $this->teamDraft->all($inUse));
    }

    public function testANewPageSurvivesAbandonedDrafts(): void
    {
        // Review 3, point 3: drafts left half-done (Reset, refresh) used to evict the empty draft of a new tab
        for ($i = 0; $i < 4; ++$i) {
            $this->fill($this->teamDraft->start(), 2);
        }

        $tabA = $this->teamDraft->start();
        $this->teamDraft->start();

        $this->assertTrue($this->teamDraft->exists($tabA));
    }

    public function testTheLeastRecentlyUsedDraftIsDroppedFirst(): void
    {
        $drafts = [];
        for ($i = 0; $i < TeamDraft::MAX_DRAFTS; ++$i) {
            $drafts[$i] = $this->teamDraft->start();
            $this->fill($drafts[$i], 1);
        }
        // The oldest draft is used again, so the second oldest becomes the least recently used
        $this->fill($drafts[0], 1);

        $this->teamDraft->start();

        $this->assertTrue($this->teamDraft->exists($drafts[0]));
        $this->assertFalse($this->teamDraft->exists($drafts[1]));
    }

    public function testNumericLookingIdsStayStrings(): void
    {
        // Review 2, point 7: PHP turns array keys like "1234567890123456" into integers
        $teamDraft = new class($this->requestStack) extends TeamDraft {
            private int $next = 1000000000000000;

            protected function randomId(): string
            {
                return (string) $this->next++;
            }
        };
        $draftId = $teamDraft->start();
        $token = $teamDraft->add($draftId, fn () => self::pokemon('pikachu'))['token'];

        $this->assertSame('d1000000000000000', $draftId);
        $this->assertSame([$token], array_keys($teamDraft->all($draftId)));
        $teamDraft->retain($draftId, [$token]);
        $this->assertSame([self::pokemon('pikachu')], $teamDraft->pick($draftId, [$token]));
    }

    public function testRetainDropsThePokemonThePageDoesNotShow(): void
    {
        $draftId = $this->teamDraft->start();
        [$shown, $lost] = $this->fill($draftId, 2);

        $this->teamDraft->retain($draftId, [$shown]);

        $this->assertSame([$shown], array_keys($this->teamDraft->all($draftId)));
    }

    public function testRetainIgnoresUnknownTokens(): void
    {
        $draftId = $this->teamDraft->start();
        [$token] = $this->fill($draftId, 1);

        $this->teamDraft->retain($draftId, [$token, 'unknown', 42]);

        $this->assertSame([$token], array_keys($this->teamDraft->all($draftId)));
    }

    public function testPickReturnsThePokemonInTheRequestedOrder(): void
    {
        $draftId = $this->teamDraft->start();
        $first = $this->teamDraft->add($draftId, fn () => self::pokemon('bulbasaur'))['token'];
        $second = $this->teamDraft->add($draftId, fn () => self::pokemon('charmander'))['token'];

        $this->assertSame(
            [self::pokemon('charmander'), self::pokemon('bulbasaur')],
            $this->teamDraft->pick($draftId, [$second, $first])
        );
    }

    #[DataProvider('invalidPicks')]
    public function testPickRejectsInvalidTeams(mixed $tokens, string $expectedMessage): void
    {
        $draftId = $this->teamDraft->start();
        $drawn = $this->fill($draftId, Team::MAX_POKEMON);
        $tokens = \is_callable($tokens) ? $tokens($drawn) : $tokens;

        $this->expectException(InvalidTeamDraftException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->teamDraft->pick($draftId, $tokens);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidPicks(): iterable
    {
        yield 'no Pokémon' => [[], 'at least one Pokémon'];
        yield 'not a list' => [null, 'at least one Pokémon'];
        yield 'associative array' => [['a' => 'b'], 'at least one Pokémon'];
        yield 'too many' => [fn (array $drawn) => [...$drawn, $drawn[0]], 'more than 6 Pokémon'];
        yield 'unknown token' => [['0123456789abcdef'], 'invalid Pokémon'];
        yield 'not a string' => [[42], 'invalid Pokémon'];
        yield 'duplicate' => [fn (array $drawn) => [$drawn[0], $drawn[0]], 'more than once'];
    }

    public function testPickFromAnUnknownDraftFails(): void
    {
        $this->expectException(InvalidTeamDraftException::class);
        $this->expectExceptionMessage('create it again');

        $this->teamDraft->pick('0123456789abcdef', ['0123456789abcdef']);
    }

    public function testCompleteForgetsTheDraftButRemembersItWasSaved(): void
    {
        $draftId = $this->teamDraft->start();
        $this->fill($draftId, 1);
        $this->assertFalse($this->teamDraft->isCompleted($draftId));

        $this->teamDraft->complete($draftId);

        $this->assertFalse($this->teamDraft->exists($draftId));
        $this->assertTrue($this->teamDraft->isCompleted($draftId));
        $this->assertFalse($this->teamDraft->isCompleted($this->teamDraft->start()));
    }

    public function testACompletedDraftCannotBeChanged(): void
    {
        $draftId = $this->teamDraft->start();
        $this->teamDraft->complete($draftId);

        $this->expectException(InvalidTeamDraftException::class);
        $this->expectExceptionMessage('already saved');

        $this->teamDraft->add($draftId, fn () => self::pokemon('pikachu'));
    }

    public function testOnlyTheLastCompletedDraftsAreRemembered(): void
    {
        $first = $this->teamDraft->start();
        $this->teamDraft->complete($first);
        for ($i = 0; $i < TeamDraft::MAX_DRAFTS; ++$i) {
            $this->teamDraft->complete($this->teamDraft->start());
        }

        $this->assertFalse($this->teamDraft->isCompleted($first));
    }

    /**
     * @return list<string> The tokens of the drawn Pokémon
     */
    private function fill(string $draftId, int $count): array
    {
        $tokens = [];
        for ($i = 0; $i < $count; ++$i) {
            $tokens[] = $this->teamDraft->add($draftId, fn () => self::pokemon('pokemon-'.$i))['token'];
        }

        return $tokens;
    }

    /**
     * @return PokemonData
     */
    private static function pokemon(string $name): array
    {
        return ['apiId' => 1, 'name' => $name, 'baseExperience' => 100, 'sprite' => '', 'abilities' => [], 'types' => []];
    }
}
