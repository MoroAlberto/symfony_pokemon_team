<?php

namespace App\Service;

use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;

class TeamService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Team $team): void
    {
        $team->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }
}
