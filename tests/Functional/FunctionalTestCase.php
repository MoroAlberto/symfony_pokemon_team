<?php

namespace App\Tests\Functional;

use App\Entity\Team;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

abstract class FunctionalTestCase extends WebTestCase
{
    use DatabaseTestTrait;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
    }

    /**
     * Draws a random Pokémon like the "Gotta Catch 'Em All" button does.
     */
    protected function drawPokemon(?KernelBrowser $client = null): array
    {
        $client ??= $this->client;
        $client->request('POST', '/pokemon/new');

        return json_decode($client->getResponse()->getContent(), true);
    }

    /**
     * Opens the create page, draws Pokémon and submits the form with their tokens.
     *
     * @param list<string>|null $tokens Tokens to submit, defaults to the ones of the drawn Pokémon
     * @param string|null $rawTokens Raw value of the hidden field, overrides $tokens
     */
    protected function createTeamThroughTheForm(
        string $name,
        int $pokemonCount = 2,
        ?array $tokens = null,
        ?string $rawTokens = null,
    ): Crawler {
        $crawler = $this->client->request('GET', '/team/create');
        $drawnTokens = [];
        for ($i = 0; $i < $pokemonCount; ++$i) {
            $drawnTokens[] = $this->drawPokemon()['token'];
        }

        $form = $crawler->selectButton('Save team')->form();

        return $this->client->submit($form, [
            'team[name]' => $name,
            'team[pokemonTokens]' => $rawTokens ?? json_encode($tokens ?? $drawnTokens),
        ]);
    }

    protected function countTeams(): int
    {
        return $this->getEntityManager()->getRepository(Team::class)->count([]);
    }

    protected function findTeam(string $name): ?Team
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->getRepository(Team::class)->findOneBy(['name' => $name]);
    }
}
