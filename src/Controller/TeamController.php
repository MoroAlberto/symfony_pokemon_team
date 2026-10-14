<?php

namespace App\Controller;

use App\Entity\Team;
use App\Exception\InvalidTeamDraftException;
use App\Form\TeamType;
use App\Service\PokemonService;
use App\Service\TeamDraft;
use App\Service\TeamService;
use App\Service\TypeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamController extends AbstractController
{
    #[Route('/team', name: 'homepage_team', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_default');
    }

    #[Route('/team/create', name: 'create_team', methods: ['GET', 'POST'])]
    public function createTeam(
        Request $request,
        PokemonService $pokemonService,
        TeamDraft $teamDraft
    ): Response {
        $team = new Team();
        $team->setCreatedAt(new \DateTime());
        $team->setUpdatedAt(new \DateTime());

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);
        $draftId = (string) $form->get('draft')->getData();

        if ($form->isSubmitted() && $teamDraft->isCompleted($draftId)) {
            // The same page was submitted again (double click, or Back then Save): nothing is saved
            $this->addFlash('warning', TeamDraft::ALREADY_SAVED_MESSAGE);

            return $this->redirectToRoute('list_teams');
        }

        if ($form->isSubmitted()) {
            try {
                $pokemon = $teamDraft->pick($draftId, json_decode((string) $form->get('pokemonTokens')->getData(), true));
            } catch (InvalidTeamDraftException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
            if ($form->isValid()) {
                $pokemonService->addPokemonToTeam($team, $pokemon);
                $teamDraft->complete($draftId);
                $this->addFlash('success', 'Your changes were saved!');

                return $this->redirectToRoute('list_teams');
            }
        }

        // A new page, or a page whose draft expired, starts a new team
        if (!$teamDraft->exists($draftId)) {
            $draftId = $teamDraft->start();
        }

        return $this->render('team/create.html.twig', [
            'form' => $form,
            'draft_id' => $draftId,
            'draft' => $teamDraft->all($draftId),
            'max_pokemon' => Team::MAX_POKEMON,
        ]);
    }

    #[Route('/team/list', name: 'list_teams', methods: ['GET'])]
    public function listTeams(TeamService $teamService, TypeService $typeService): Response
    {
        return $this->render('team/list.html.twig', [
            'teams' => $teamService->getTeamsCache(),
            'types' => $typeService->getTypeCached(),
        ]);
    }

    #[Route('/team/{id}/edit', name: 'edit_team', methods: ['GET', 'POST'])]
    public function editTeam(Team $team, Request $request, TeamService $teamService): Response
    {
        $form = $this->createForm(TeamType::class, $team, ['with_pokemon' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $teamService->save($team);
            $this->addFlash('success', 'Your changes were saved!');

            return $this->redirectToRoute('list_teams');
        }

        return $this->render('team/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
