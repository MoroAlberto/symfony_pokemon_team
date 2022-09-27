<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Service\PokemonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class PokemonController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/pokemon/new', name: 'new_pokemon')]
    public function newPokemon(PokemonService $pokemonService): JsonResponse
    {
        $pokemon = $pokemonService->newPokemon();
        /*$encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);
        $jsonContent = $serializer->serialize($pokemon, 'json');
        dd($jsonContent);*/
        $arrayForJson = array(
          'name' => $pokemon->getName(),
          'sprite' => $pokemon->getSprite(),
          'base_experience' => $pokemon->getBaseExperience(),
          'types' => $pokemon->getTypes(),
          'abilities' => $pokemon->getAbilities()
        );
        return new JsonResponse($arrayForJson);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getRandomPokemon(PokemonService $pokemonService): Pokemon
    {
        return $pokemonService->newPokemon();
    }
}
