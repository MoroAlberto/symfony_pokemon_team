<?php

namespace App\Service;

use App\Entity\Ability;
use App\Entity\Pokemon;
use App\Entity\Team;
use App\Entity\Type;
use App\Exception\PokeApiException;
use App\Repository\AbilityRepository;
use App\Repository\TypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PokemonService
{
    public const API_URL = 'https://pokeapi.co/api/v2/pokemon/';
    // Highest national Pokédex number available on PokeAPI
    public const MAX_POKEMON_ID = 1025;

    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $entityManager,
        private TypeRepository $typeRepository,
        private AbilityRepository $abilityRepository,
        private TeamService $teamService,
        private TypeService $typeService,
    ) {
    }

    /**
     * Fetches a random Pokémon from PokeAPI.
     *
     * @return array{apiId: int, name: string, baseExperience: int, sprite: string, abilities: list<string>, types: list<string>}
     *
     * @throws PokeApiException
     */
    public function fetchRandomPokemon(): array
    {
        $apiId = random_int(1, self::MAX_POKEMON_ID);

        try {
            $response = $this->client->request('GET', self::API_URL.$apiId.'/');
            if (200 !== $response->getStatusCode()) {
                throw new PokeApiException('Error with pokemon API '.$response->getStatusCode());
            }
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new PokeApiException('Error with pokemon API: '.$e->getMessage(), 0, $e);
        }

        return ['apiId' => $apiId] + $this->normalize($data);
    }

    /**
     * Persists the given Pokémon (as returned by fetchRandomPokemon) and adds them to the team.
     *
     * @param list<array{name: string, baseExperience: int, sprite: string, abilities: list<string>, types: list<string>}> $pokemonList
     */
    public function addPokemonToTeam(Team $team, array $pokemonList): void
    {
        // Types and abilities created in this batch, so that two Pokémon sharing a new type don't duplicate it
        $types = [];
        $abilities = [];

        foreach ($pokemonList as $pokemonData) {
            $pokemon = new Pokemon();
            $pokemon->setName($pokemonData['name']);
            $pokemon->setBaseExperience($pokemonData['baseExperience']);
            $pokemon->setSprite($pokemonData['sprite']);

            foreach ($pokemonData['abilities'] as $name) {
                $abilities[$name] ??= $this->abilityRepository->findOneBy(['name' => $name])
                    ?? (new Ability())->setName($name);
                $pokemon->addAbility($abilities[$name]);
                $this->entityManager->persist($abilities[$name]);
            }
            foreach ($pokemonData['types'] as $name) {
                $types[$name] ??= $this->typeRepository->findOneBy(['name' => $name])
                    ?? (new Type())->setName($name);
                $pokemon->addType($types[$name]);
                $this->entityManager->persist($types[$name]);
            }

            $team->addPokemon($pokemon);
            $this->entityManager->persist($pokemon);
        }

        $this->entityManager->persist($team);
        $this->entityManager->flush();
        // The team, and maybe new types, must show up in the cached lists
        $this->teamService->invalidateCache();
        $this->typeService->invalidateCache();
    }

    /**
     * @throws PokeApiException When the PokeAPI data does not have the expected shape
     */
    private function normalize(array $data): array
    {
        $names = fn (mixed $list, string $key) => \is_array($list)
            ? array_map(fn (mixed $item) => $item[$key]['name'] ?? null, $list)
            : null;
        $pokemon = [
            'name' => $data['name'] ?? null,
            // Some recent Pokémon have no base experience or sprite on PokeAPI
            'baseExperience' => $data['base_experience'] ?? 0,
            'sprite' => $data['sprites']['front_default'] ?? '',
            'abilities' => $names($data['abilities'] ?? null, 'ability'),
            'types' => $names($data['types'] ?? null, 'type'),
        ];

        $isListOfStrings = fn (mixed $list) => \is_array($list) && array_is_list($list)
            && $list === array_filter($list, 'is_string');
        if (!\is_string($pokemon['name']) || !\is_int($pokemon['baseExperience']) || !\is_string($pokemon['sprite'])
            || !$isListOfStrings($pokemon['abilities']) || !$isListOfStrings($pokemon['types'])) {
            throw new PokeApiException('Unexpected data from pokemon API');
        }

        return $pokemon;
    }
}
