<?php

namespace App\Tests\Integration;

use App\Tests\DatabaseTestTrait;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The other tests build the schema from the entities: this one checks that the migrations build the same schema,
 * so that an entity change without its migration is caught.
 */
class MigrationsTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function tearDown(): void
    {
        // Leave the usual schema behind, as the other tests expect
        $this->resetDatabase();
        parent::tearDown();
    }

    public function testMigrationsCreateTheSchemaOfTheEntities(): void
    {
        $this->emptyDatabase();

        $this->migrate('latest');

        $this->assertSame([], $this->schemaChangesNeeded(), 'The migrations do not match the entities, run doctrine:migrations:diff');
    }

    public function testMigrationsCanBeReverted(): void
    {
        $this->emptyDatabase();
        $this->migrate('latest');

        $this->migrate('first');

        $tables = $this->getEntityManager()->getConnection()->createSchemaManager()->listTableNames();
        $this->assertSame(['doctrine_migration_versions'], $tables);
    }

    private function emptyDatabase(): void
    {
        self::bootKernel();
        (new SchemaTool($this->getEntityManager()))->dropDatabase();
    }

    private function migrate(string $version): void
    {
        // A migration can run only once per kernel, a new kernel is needed to run it again
        self::ensureKernelShutdown();
        self::bootKernel();
        $command = (new Application(self::$kernel))->find('doctrine:migrations:migrate');
        $tester = new CommandTester($command);
        $tester->execute(['version' => $version], ['interactive' => false]);

        $tester->assertCommandIsSuccessful($tester->getDisplay());
    }

    /**
     * @return list<string> SQL needed to bring the database in line with the entities
     */
    private function schemaChangesNeeded(): array
    {
        $entityManager = $this->getEntityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $sql = (new SchemaTool($entityManager))->getUpdateSchemaSql($metadata);

        // The table where the migrations record what was run is not an entity
        return array_values(array_filter($sql, fn (string $query) => !str_contains($query, 'doctrine_migration_versions')));
    }
}
