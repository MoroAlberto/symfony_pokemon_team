<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TeamRepositoryTest extends KernelTestCase
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

        $names = array_map(fn (Team $t) => $t->getName(), $this->findTeams());

        $this->assertSame(['Oldest', 'Middle', 'Newest'], $names);
    }

    public function testTeamsWithoutPokemonAreIncluded(): void
    {
        $this->createTeam('Empty team');

        [$team] = $this->findTeams();

        $this->assertSame('Empty team', $team->getName());
        $this->assertCount(0, $team->getPokemon());
    }

    public function testPokemonAndTypesAreLoadedWithTheTeams(): void
    {
        $this->createTeam('Fire team', ['charizard' => [240, ['fire', 'flying']], 'vulpix' => [60, ['fire']]]);

        [$team] = $this->findTeams();

        // Initialized collections were filled by the query itself, so reading them runs no other query
        $pokemon = $team->getPokemon();
        $this->assertInstanceOf(PersistentCollection::class, $pokemon);
        $this->assertTrue($pokemon->isInitialized(), 'The Pokémon should be loaded with the team');
        foreach ($pokemon as $p) {
            $this->assertTrue($p->getTypes()->isInitialized(), 'The types should be loaded with the Pokémon');
        }
        $this->assertSame(['fire', 'flying'], array_values($team->getAllPokemonType()));
    }

    /**
     * @return list<Team>
     */
    private function findTeams(): array
    {
        // Forget the entities created by the test, so that they are really loaded from the database
        $this->getEntityManager()->clear();

        return static::getContainer()->get(TeamRepository::class)->findAllWithPokemonAndTypes();
    }
}
