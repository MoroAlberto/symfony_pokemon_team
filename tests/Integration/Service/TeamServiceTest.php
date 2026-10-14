<?php

namespace App\Tests\Integration\Service;

use App\Entity\Team;
use App\Service\TeamService;
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

    public function testSaveUpdatesTheTeam(): void
    {
        $team = $this->createTeam('Old name', [], '2024-01-01');

        $team->setName('New name');
        static::getContainer()->get(TeamService::class)->save($team);

        $this->getEntityManager()->clear();
        $saved = $this->getEntityManager()->find(Team::class, $team->getId());
        $this->assertSame('New name', $saved->getName());
        $this->assertEqualsWithDelta(new \DateTime(), $saved->getUpdatedAt(), 5);
        $this->assertEquals(new \DateTime('2024-01-01'), $saved->getCreatedAt());
    }
}
