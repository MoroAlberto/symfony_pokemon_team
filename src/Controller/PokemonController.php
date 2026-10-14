<?php

namespace App\Controller;

use App\Exception\InvalidTeamDraftException;
use App\Exception\PokeApiException;
use App\Service\PokemonService;
use App\Service\TeamDraft;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PokemonController extends AbstractController
{
    /**
     * Draws a random Pokémon and adds it to the team being created.
     *
     * Expects a JSON body: {"draft": "<draft id of the page>", "keep": ["<tokens of the Pokémon shown on the page>"]}
     */
    #[Route('/pokemon/new', name: 'new_pokemon', methods: ['POST'])]
    public function newPokemon(Request $request, PokemonService $pokemonService, TeamDraft $teamDraft): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (JsonException) {
            return $this->json(['error' => 'Invalid request.'], Response::HTTP_BAD_REQUEST);
        }
        $draftId = \is_string($body['draft'] ?? null) ? $body['draft'] : '';

        try {
            if (\is_array($body['keep'] ?? null)) {
                $teamDraft->retain($draftId, $body['keep']);
            }
            $pokemon = $teamDraft->add($draftId, $pokemonService->fetchRandomPokemon(...));
        } catch (InvalidTeamDraftException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (PokeApiException) {
            return $this->json(
                ['error' => 'The Pokémon API is not available, please try again later.'],
                Response::HTTP_BAD_GATEWAY
            );
        }

        return $this->json($pokemon);
    }
}
