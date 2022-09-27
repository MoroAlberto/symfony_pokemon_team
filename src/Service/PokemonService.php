<?php

namespace App\Service;

use App\Entity\Ability;
use App\Entity\Pokemon;
use App\Entity\Type;
use App\Repository\AbilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokemonService
{
    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;

    public function __construct(HttpClientInterface $client,  EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function newPokemon(): Pokemon
    {
        $randomPokemon = $this->getRandomPokemon();
        $pokemon = new Pokemon();
        $pokemon->setName($randomPokemon['name']);
        $pokemon->setBaseExperience($randomPokemon['base_experience']);
        $pokemon->setSprite($randomPokemon['sprites']['front_default']);
        $this->entityManager->persist($pokemon);//not sure
        foreach ($randomPokemon['abilities'] as $abilityArray) {
            $ability = new Ability();
            $ability->setName($abilityArray['ability']['name']);
            $ability->addPokemon($pokemon);
            $this->entityManager->persist($ability);
            $this->entityManager->flush();
            $pokemon->addAbility($ability);
        }
        foreach ($randomPokemon['types'] as $typeArray) {
            $type = new Type();
            $type->setName($typeArray['type']['name']);
            $type->addPokemon($pokemon);
            $this->entityManager->persist($type);
            $this->entityManager->flush();
            $pokemon->addType($type);
        }
        $this->entityManager->persist($pokemon);
        $this->entityManager->flush();
        return $pokemon;
    }

    /**
     * @return array
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    private function getRandomPokemon(): array
    {
        $randId = rand(1, 905);
        $response = $this->client->request(
            'GET',
            'https://pokeapi.co/api/v2/pokemon/' . $randId . '/'
        );
        //$contentType = $response->getHeaders()['content-type'][0];
        //$contentType = 'application/json'
        $statusCode = $response->getStatusCode();
        if ($statusCode == 200) {
            return $response->toArray();
        } else {
            throw new Exception('Error with pokemon API ' . $statusCode);
        }
    }
}