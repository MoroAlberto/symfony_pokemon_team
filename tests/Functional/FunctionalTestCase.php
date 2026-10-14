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
     * Opens the create page and returns the id of its team draft.
     */
    protected function openCreatePage(): string
    {
        $crawler = $this->client->request('GET', '/team/create');

        return (string) $crawler->filter('[data-team-draft]')->attr('value');
    }

    /**
     * Draws a random Pokémon like the "Gotta Catch 'Em All" button does.
     *
     * @param list<string>|null $keep Tokens of the Pokémon shown on the page, sent like create.js does
     */
    protected function drawPokemon(string $draftId, ?array $keep = null): array
    {
        $this->client->jsonRequest('POST', '/pokemon/new', ['draft' => $draftId] + (null === $keep ? [] : ['keep' => $keep]));

        return json_decode($this->client->getResponse()->getContent(), true);
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
        $draftId = (string) $crawler->filter('[data-team-draft]')->attr('value');
        $drawnTokens = [];
        for ($i = 0; $i < $pokemonCount; ++$i) {
            $drawnTokens[] = $this->drawPokemon($draftId)['token'];
        }

        $form = $crawler->selectButton('Save team')->form();

        return $this->client->submit($form, [
            'team[name]' => $name,
            'team[pokemonTokens]' => $rawTokens ?? json_encode($tokens ?? $drawnTokens),
        ]);
    }

    /**
     * Submits the create form of the given draft, without opening the create page again.
     *
     * @param list<string> $tokens
     */
    protected function submitTokens(string $name, array $tokens, string $draftId): void
    {
        // Forms use a stateless CSRF token, checked against the Origin header
        $this->client->request('POST', '/team/create', ['team' => [
            'name' => $name,
            'draft' => $draftId,
            'pokemonTokens' => json_encode($tokens),
            '_token' => 'csrf-token',
        ]], server: ['HTTP_ORIGIN' => 'http://localhost']);
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
