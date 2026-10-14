<?php

namespace App\Tests;

use App\Entity\Pokemon;
use App\Entity\Team;
use App\Entity\Type;
use App\Tests\Mock\PokeApiMock;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Gives every test an empty database and a fresh fake PokeAPI.
 * Must be used in a KernelTestCase (or WebTestCase, after creating the client).
 */
trait DatabaseTestTrait
{
    protected function resetDatabase(): void
    {
        $entityManager = $this->getEntityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);

        PokeApiMock::reset();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Creates a team directly in the database.
     *
     * @param array<string, array{int, list<string>}> $pokemon Base experience and types, indexed by name
     */
    protected function createTeam(string $name, array $pokemon = [], string $createdAt = 'now'): Team
    {
        $entityManager = $this->getEntityManager();
        $team = (new Team())
            ->setName($name)
            ->setCreatedAt(new \DateTime($createdAt))
            ->setUpdatedAt(new \DateTime($createdAt));

        $types = [];
        foreach ($pokemon as $pokemonName => [$baseExperience, $typeNames]) {
            $entity = (new Pokemon())
                ->setName($pokemonName)
                ->setBaseExperience($baseExperience)
                ->setSprite('https://example.com/'.$pokemonName.'.png');
            foreach ($typeNames as $typeName) {
                $types[$typeName] ??= $entityManager->getRepository(Type::class)->findOneBy(['name' => $typeName])
                    ?? (new Type())->setName($typeName);
                $entity->addType($types[$typeName]);
                $entityManager->persist($types[$typeName]);
            }
            $team->addPokemon($entity);
            $entityManager->persist($entity);
        }

        $entityManager->persist($team);
        $entityManager->flush();

        return $team;
    }
}
