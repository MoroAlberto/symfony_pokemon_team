<?php

namespace App\Tests\Integration\Service;

use App\Entity\Team;
use App\Entity\Type;
use App\Service\TeamService;
use App\Service\TypeService;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TeamServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
    }

    public function testTeamsAreSortedByCreationDate(): void
    {
        $this->createTeam('Newest', [], '2024-03-01');
        $this->createTeam('Oldest', [], '2024-01-01');
        $this->createTeam('Middle', [], '2024-02-01');

        $names = array_map(fn (Team $t) => $t->getName(), $this->getTeamService()->getTeamsCache());

        $this->assertSame(['Oldest', 'Middle', 'Newest'], $names);
    }

    public function testTeamsComeWithTheirTypeList(): void
    {
        $this->createTeam('Fire team', ['charizard' => [240, ['fire', 'flying']], 'vulpix' => [60, ['fire']]]);

        [$team] = $this->getTeamService()->getTeamsCache();

        $this->assertSame(['fire', 'flying'], array_values($team->typeList));
    }

    public function testTeamsAreCachedUntilTheCacheIsInvalidated(): void
    {
        $this->createTeam('First');
        $this->assertCount(1, $this->getTeamService()->getTeamsCache());

        $this->createTeam('Second');
        $this->assertCount(1, $this->getTeamService()->getTeamsCache(), 'The list should come from the cache');

        $this->getTeamService()->invalidateCache();
        $this->assertCount(2, $this->getTeamService()->getTeamsCache());
    }

    public function testSaveUpdatesTheTeamAndInvalidatesTheCache(): void
    {
        $team = $this->createTeam('Old name', [], '2024-01-01');
        $this->getTeamService()->getTeamsCache();

        $team->setName('New name');
        $this->getTeamService()->save($team);

        $this->assertSame('New name', $this->getTeamService()->getTeamsCache()[0]->getName());
        $this->assertEqualsWithDelta(new \DateTime(), $team->getUpdatedAt(), 5);
        $this->assertEquals(new \DateTime('2024-01-01'), $team->getCreatedAt());
    }

    public function testTypesAreSortedByNameAndCachedUntilInvalidated(): void
    {
        $this->createTeam('Team', ['squirtle' => [63, ['water']], 'charmander' => [62, ['fire']]]);
        $this->assertSame(['fire', 'water'], $this->getTypeNames());

        $this->createTeam('Other team', ['pikachu' => [112, ['electric']]]);
        $this->assertSame(['fire', 'water'], $this->getTypeNames(), 'The types should come from the cache');

        static::getContainer()->get(TypeService::class)->invalidateCache();
        $this->assertSame(['electric', 'fire', 'water'], $this->getTypeNames());
    }

    public function testEachServiceInvalidatesOnlyItsOwnCache(): void
    {
        // Review 2, point 5: the team and type caches are invalidated separately
        $this->createTeam('Team', ['pikachu' => [112, ['electric']]]);
        $this->getTeamService()->getTeamsCache();
        $this->assertSame(['electric'], $this->getTypeNames());
        $this->createTeam('Other team', ['squirtle' => [63, ['water']]]);

        $this->getTeamService()->invalidateCache();

        $this->assertCount(2, $this->getTeamService()->getTeamsCache());
        $this->assertSame(['electric'], $this->getTypeNames(), 'The types should still come from the cache');
    }

    public function testSaveKeepsTheTypeCache(): void
    {
        // Review point 8: renaming a team does not change the types, so their cache stays valid
        $team = $this->createTeam('Team', ['pikachu' => [112, ['electric']]]);
        $this->assertSame(['electric'], $this->getTypeNames());
        $this->createTeam('Other team', ['squirtle' => [63, ['water']]]);

        $team->setName('Renamed');
        $this->getTeamService()->save($team);

        $this->assertSame(['electric'], $this->getTypeNames(), 'The types should still come from the cache');
    }

    private function getTeamService(): TeamService
    {
        return static::getContainer()->get(TeamService::class);
    }

    /**
     * @return list<string>
     */
    private function getTypeNames(): array
    {
        $typeService = static::getContainer()->get(TypeService::class);

        return array_map(fn (Type $t) => $t->getName(), $typeService->getTypeCached());
    }
}
