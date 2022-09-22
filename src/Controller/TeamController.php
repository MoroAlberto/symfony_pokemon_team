<?php

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TeamController extends AbstractController
{
    #[Route('/team', name: 'app_team')]
    public function index(Request $request): Response
    {
        //dump($request);
        return $this->render('team/index.html.twig', [
            'controller_name' => 'TeamController',
        ]);
    }

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
            $entityManager->persist($team);
            $entityManager->flush();
            $this->addFlash(
                'success',
                'Your changes were saved!'
            );
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
        $teams = $teamRepository->findAll();

        return $this->render('team/list.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/team/{teamId}/edit', name: 'edit_team')]
    public function editTeam(Team $teamId, TeamRepository $teamRepository): Response
    {
        $team = $teamRepository->find($teamId);
        return $this->render('team/edit.html.twig', [
            'team' => $team,
        ]);
    }
}
