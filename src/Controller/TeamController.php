<?php

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use App\Service\PokemonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TeamController extends AbstractController
{
    #[Route('/team', name: 'homepage_team')]
    #[Route('/', name: 'default')]
    public function index(Request $request): Response
    {
        return $this->render('team/index.html.twig', [
            'controller_name' => 'TeamController',
        ]);
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    #[Route('/team/create', name: 'create_team')]
    public function createTeam(Request $request, EntityManagerInterface $entityManager): Response
    {
        $team = new Team();

        $team->setName('');
        $team->setCreatedAt(new \DateTime());
        $team->setUpdatedAt(new \DateTimeImmutable());

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $team = $form->getData();
            $team = $this->addPokemonListToTeam($entityManager, $team);
            $entityManager->persist($team);
            $entityManager->flush();
//            $this->addFlash(
//                'success',
//                'Your changes were saved!'
//            );
            return $this->redirectToRoute('list_teams');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            //todo remove in prod
            //dd($form->getErrors());
        }


        return $this->render('team/create.html.twig', [
            'form' => $form->createView()
        ]);
    }


    #[Route('/team/list', name: 'list_teams')]
    public function listTeams(TeamRepository $teamRepository): Response
    {
        $teams = $teamRepository->findBy(
            array(),
            array('created_at' => 'ASC')
        );

        return $this->render('team/list.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/team/{teamId}/edit', name: 'edit_team')]
    public function editTeam(
        Team $teamId,
        Request $request,
        EntityManagerInterface $entityManager,
        TeamRepository $teamRepository
    ): Response {
        $team = $teamRepository->find($teamId);
        $form = $this->createForm(TeamType::class, $team);
        $form->remove('created_at');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $team = $form->getData();
            $entityManager->persist($team);
            $entityManager->flush();
            return $this->redirectToRoute('list_teams');
        }

        return $this->render('team/edit.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function addPokemonListToTeam($entityManager, Team $team): Team
    {
        for ($i = 1; $i <= 6; $i++) {
            $pokemonService = new PokemonService(HttpClient::create());
            $pokemon = $pokemonService->newPokemon();
            $entityManager->persist($pokemon);
            $entityManager->flush();
            $team->addPokemon($pokemon);
        }
        return $team;
    }
}
