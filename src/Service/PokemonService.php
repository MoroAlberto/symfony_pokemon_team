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
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PokemonData: a Pokémon drawn from PokeAPI. DrawnPokemon: the same, with the token given by TeamDraft.
 *
 * @phpstan-type PokemonData array{apiId: int, name: string, baseExperience: int, sprite: string, abilities: list<string>, types: list<string>}
 * @phpstan-type DrawnPokemon array{token: string, apiId: int, name: string, baseExperience: int, sprite: string, abilities: list<string>, types: list<string>}
 */
class PokemonService
{
    // Highest national Pokédex number available on PokeAPI
    public const MAX_POKEMON_ID = 1025;

    public function __construct(
        // The client configured in config/packages/framework.yaml
        #[Target('pokeapi.client')]
        private HttpClientInterface $pokeapiClient,
        private EntityManagerInterface $entityManager,
        private TypeRepository $typeRepository,
        private AbilityRepository $abilityRepository,
    ) {
    }

    /**
     * Fetches a random Pokémon from PokeAPI.
     *
     * @return PokemonData
     *
     * @throws PokeApiException
     */
    public function fetchRandomPokemon(): array
    {
        $apiId = random_int(1, self::MAX_POKEMON_ID);

        try {
            // Relative to the base_uri of the pokeapi.client (config/packages/framework.yaml)
            $response = $this->pokeapiClient->request('GET', 'pokemon/'.$apiId.'/');
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
     * @param list<PokemonData> $pokemonList
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
    }

    /**
     * @param array<mixed> $data Pokémon as returned by PokeAPI
     *
     * @return array{name: string, baseExperience: int, sprite: string, abilities: list<string>, types: list<string>}
     *
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
