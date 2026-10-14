<?php

namespace App\Tests\Functional;

class TeamEditTest extends FunctionalTestCase
{
    public function testEditPageShowsTheCurrentName(): void
    {
        $team = $this->createTeam('Team Rocket');

        $this->client->request('GET', '/team/'.$team->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('team[name]', 'Team Rocket');
        $this->assertSelectorNotExists('input[name="team[pokemonTokens]"]');
    }

    public function testEditTheName(): void
    {
        $team = $this->createTeam('Old name', ['pikachu' => [112, ['electric']]], '2024-01-01');

        $crawler = $this->client->request('GET', '/team/'.$team->getId().'/edit');
        $this->client->submit($crawler->selectButton('Edit team')->form(), ['team[name]' => 'New name']);

        $this->assertResponseRedirects('/team/list');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Your changes were saved!');

        $edited = $this->findTeam('New name');
        $this->assertSame($team->getId(), $edited->getId());
        $this->assertCount(1, $edited->getPokemon(), 'The Pokémon should not change');
        $this->assertEquals(new \DateTime('2024-01-01'), $edited->getCreatedAt());
        $this->assertEqualsWithDelta(new \DateTime(), $edited->getUpdatedAt(), 10);
    }

    public function testTheNameIsRequired(): void
    {
        $team = $this->createTeam('Team Rocket');

        $crawler = $this->client->request('GET', '/team/'.$team->getId().'/edit');
        $this->client->submit($crawler->selectButton('Edit team')->form(), ['team[name]' => '']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('#team_name.is-invalid');
        $this->assertNotNull($this->findTeam('Team Rocket'));
    }

    public function testEditingAnUnknownTeamReturns404(): void
    {
        $this->client->request('GET', '/team/999/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditingWithANonNumericIdReturns404(): void
    {
        $this->client->request('GET', '/team/abc/edit');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCsrfProtection(): void
    {
        $team = $this->createTeam('Team Rocket');

        $this->client->request('POST', '/team/'.$team->getId().'/edit', ['team' => [
            'name' => 'Hacked',
            '_token' => 'csrf-token',
        ]], server: ['HTTP_ORIGIN' => 'https://evil.example.com', 'HTTP_REFERER' => 'https://evil.example.com/']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNotNull($this->findTeam('Team Rocket'));
    }
}
