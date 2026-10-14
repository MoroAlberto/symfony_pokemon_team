<?php

namespace App\Service;

use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TeamService
{
    public const CACHE_KEY = 'team_key';

    public function __construct(
        private TeamRepository $teamRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @return Team[]
     */
    public function getTeamsCache(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $teams = $this->teamRepository->findBy([], ['created_at' => 'ASC']);
            foreach ($teams as $team) {
                $team->typeList = $team->getAllPokemonType();
            }

            return $teams;
        });
    }

    public function save(Team $team): void
    {
        $team->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($team);
        $this->entityManager->flush();
        // Editing a team does not change the types, so their cache stays valid
        $this->invalidateCache();
    }

    /**
     * Must be called whenever teams or their Pokémon change.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
