<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function add(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Team $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * All the teams, oldest first, with their Pokémon and the types of these Pokémon.
     *
     * The joins load everything in a single query: without them, Doctrine would run one more query
     * for the Pokémon of each team, and one for the types of each Pokémon (the "N+1" problem).
     *
     * @return list<Team>
     */
    public function findAllWithPokemonAndTypes(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.pokemon', 'p')
            ->addSelect('p')
            ->leftJoin('p.types', 'ty')
            ->addSelect('ty')
            ->orderBy('t.created_at', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
