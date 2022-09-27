<?php

namespace App\Service;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use App\Entity\Team;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use App\Repository\TeamRepository;

class TeamService
{

    private FilesystemAdapter $cache;
    private TeamRepository $teamRepository;

    public function __construct(TeamRepository $teamRepository)
    {
        $this->cache = new FilesystemAdapter();
        $this->teamRepository = $teamRepository;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getTeamsCache()
    {
        $value = $this->cache->get('team_key', function (ItemInterface $item) {
            $item->expiresAfter(3600);
            $teams = $this->teamRepository->findBy(
                array(),
                array('created_at' => 'ASC')
            );
            foreach ($teams as $team) {
                foreach ($team->getPokemon() as $pokemon) {
                    foreach ($pokemon->getTypes() as $type) {
                        //if I don't do this shit I don't cache collection
                    }
                }
            }
            return $teams;
        });
        //$this->cache->delete('team_key');
        return $value;
    }
}