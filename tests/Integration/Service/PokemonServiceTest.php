<?php

namespace App\Tests\Integration\Service;

use App\Entity\Ability;
use App\Entity\Pokemon;
use App\Entity\Team;
use App\Entity\Type;
use App\Service\PokemonService;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PokemonServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resetDatabase();
    }

    public function testAddPokemonToTeamPersistsTheTeamAndItsPokemon(): void
    {
        $team = $this->newTeam();

        $this->getService()->addPokemonToTeam($team, [
            $this->pokemonData('charizard', 240, ['blaze'], ['fire', 'flying']),
            $this->pokemonData('pikachu', 112, ['static'], ['electric']),
        ]);
        $this->getEntityManager()->clear();

        $savedTeam = $this->getEntityManager()->getRepository(Team::class)->find($team->getId());
        $this->assertSame('Team Rocket', $savedTeam->getName());
        $pokemon = $savedTeam->getPokemon()->toArray();
        $this->assertSame(['charizard', 'pikachu'], array_map(fn (Pokemon $p) => $p->getName(), $pokemon));
        $this->assertSame(240, $pokemon[0]->getBaseExperience());
        $this->assertSame('https://example.com/charizard.png', $pokemon[0]->getSprite());
        $this->assertSame(['fire', 'flying'], array_map(fn (Type $t) => $t->getName(), $pokemon[0]->getTypes()->toArray()));
        $this->assertSame(['blaze'], array_map(fn (Ability $a) => $a->getName(), $pokemon[0]->getAbilities()->toArray()));
    }

    public function testExistingTypesAndAbilitiesAreReused(): void
    {
        $this->getService()->addPokemonToTeam($this->newTeam(), [
            $this->pokemonData('charmander', 62, ['blaze'], ['fire']),
        ]);

        $this->getService()->addPokemonToTeam($this->newTeam(), [
            $this->pokemonData('torchic', 62, ['blaze'], ['fire']),
        ]);

        $this->assertSame(1, $this->getEntityManager()->getRepository(Type::class)->count([]));
        $this->assertSame(1, $this->getEntityManager()->getRepository(Ability::class)->count([]));
    }

    public function testNewTypesSharedInTheSameTeamAreCreatedOnce(): void
    {
        $this->getService()->addPokemonToTeam($this->newTeam(), [
            $this->pokemonData('charmander', 62, ['blaze'], ['fire']),
            $this->pokemonData('vulpix', 60, ['flash-fire'], ['fire']),
            $this->pokemonData('torchic', 62, ['blaze'], ['fire']),
        ]);

        $types = $this->getEntityManager()->getRepository(Type::class)->findAll();
        $this->assertSame(['fire'], array_map(fn (Type $t) => $t->getName(), $types));
        $this->assertSame(2, $this->getEntityManager()->getRepository(Ability::class)->count([]));
    }

    public function testTheSamePokemonCanBeInATeamTwice(): void
    {
        $team = $this->newTeam();

        $this->getService()->addPokemonToTeam($team, [
            $this->pokemonData('magikarp', 40, ['swift-swim'], ['water']),
            $this->pokemonData('magikarp', 40, ['swift-swim'], ['water']),
        ]);

        $this->assertCount(2, $team->getPokemon());
        $this->assertSame(2, $this->getEntityManager()->getRepository(Pokemon::class)->count([]));
    }

    private function getService(): PokemonService
    {
        return static::getContainer()->get(PokemonService::class);
    }

    private function newTeam(): Team
    {
        return (new Team())->setName('Team Rocket')->setCreatedAt(new \DateTime())->setUpdatedAt(new \DateTime());
    }

    private function pokemonData(string $name, int $baseExperience, array $abilities, array $types): array
    {
        return [
            'apiId' => 1,
            'name' => $name,
            'baseExperience' => $baseExperience,
            'sprite' => 'https://example.com/'.$name.'.png',
            'abilities' => $abilities,
            'types' => $types,
        ];
    }
}
