<?php

namespace App\Tests\Functional;

class TeamListTest extends FunctionalTestCase
{
    public function testEmptyList(): void
    {
        $this->client->request('GET', '/team/list');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Your teams');
        $this->assertSelectorTextContains('main', 'No team yet');
        $this->assertSelectorCount(0, 'tbody tr');
        $this->assertSelectorNotExists('#typeFilter');
    }

    public function testListShowsTheTeams(): void
    {
        $team = $this->createTeam('Team Rocket', [
            'ekans' => [58, ['poison']],
            'koffing' => [68, ['poison']],
            'meowth' => [58, ['normal']],
        ], '2024-05-17');

        $crawler = $this->client->request('GET', '/team/list');

        $this->assertResponseIsSuccessful();
        $row = $crawler->filter('#team_id_'.$team->getId());
        $this->assertSame((string) $team->getId(), $row->filter('th')->text());
        $cells = $row->filter('td');
        $this->assertSame('Team Rocket', $cells->eq(0)->text());
        $this->assertCount(3, $cells->eq(1)->filter('img'));
        $this->assertSame('184', $cells->eq(2)->text(), 'Sum of the base experience');
        $this->assertSame(
            ['normal', 'poison'],
            $cells->eq(3)->filter('.type-badge')->each(fn ($badge) => $badge->text()),
            'Each type once, sorted by name'
        );
        $this->assertSame('normal poison', $row->attr('data-types'), 'Used by the type filter');
        $this->assertSame('17-05-2024', $cells->eq(4)->text());
        $this->assertSame('/team/'.$team->getId().'/edit', $cells->eq(5)->filter('a')->attr('href'));
    }

    public function testTheNumberOfQueriesDoesNotGrowWithTheTeams(): void
    {
        $this->createTeam('Team 1', ['pikachu' => [112, ['electric']], 'squirtle' => [63, ['water']]]);
        $queriesForOneTeam = $this->countQueriesOfTheList();

        for ($i = 2; $i <= 5; ++$i) {
            $this->createTeam('Team '.$i, ['pikachu' => [112, ['electric']], 'squirtle' => [63, ['water']]]);
        }
        $queriesForFiveTeams = $this->countQueriesOfTheList();

        $this->assertSelectorCount(5, 'tbody tr');
        // Without the joins of TeamRepository::findAllWithPokemonAndTypes(), each team would add 3 queries
        $this->assertSame($queriesForOneTeam, $queriesForFiveTeams);
    }

    public function testTeamsAreSortedByCreationDate(): void
    {
        $this->createTeam('Second', [], '2024-02-01');
        $this->createTeam('First', [], '2024-01-01');

        $crawler = $this->client->request('GET', '/team/list');

        $this->assertSame(['First', 'Second'], $crawler->filter('tbody tr td:first-of-type')->each(fn ($td) => $td->text()));
    }

    public function testTypeFilterListsAllTypesSortedByName(): void
    {
        $this->createTeam('Team', ['squirtle' => [63, ['water']], 'charmander' => [62, ['fire']]]);

        $crawler = $this->client->request('GET', '/team/list');

        $this->assertSame(['fire', 'water'], $crawler->filter('#typeFilter label')->each(fn ($label) => $label->text()));
        $this->assertSame(['fire', 'water'], $crawler->filter('#typeFilter input:checked')->extract(['value']), 'All types are shown at first');
    }

    public function testTeamNamesAreEscaped(): void
    {
        $this->createTeam('<script>alert(1)</script>');

        $this->client->request('GET', '/team/list');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $this->client->getResponse()->getContent());
        $this->assertSelectorTextContains('tbody td', '<script>alert(1)</script>');
    }

    public function testANewTeamShowsUpImmediately(): void
    {
        $this->client->request('GET', '/team/list');
        $this->assertSelectorCount(0, 'tbody tr');

        $this->createTeamThroughTheForm('Brand new team');
        $this->client->request('GET', '/team/list');

        $this->assertSelectorCount(1, 'tbody tr');
        $this->assertSelectorTextContains('tbody', 'Brand new team');
    }

    public function testAnEditedTeamShowsUpImmediately(): void
    {
        // Regression test: the list used to keep showing the old name for one hour
        $team = $this->createTeam('Old name');
        $this->client->request('GET', '/team/list');
        $this->assertSelectorTextContains('tbody', 'Old name');

        $crawler = $this->client->request('GET', '/team/'.$team->getId().'/edit');
        $this->client->submit($crawler->selectButton('Edit team')->form(), ['team[name]' => 'New name']);
        $this->client->request('GET', '/team/list');

        $this->assertSelectorTextContains('tbody', 'New name');
        $this->assertSelectorTextNotContains('tbody', 'Old name');
    }

    public function testNewTypesShowUpInTheFilter(): void
    {
        $this->client->request('GET', '/team/list');
        $this->assertSelectorNotExists('#typeFilter');

        $this->createTeamThroughTheForm('Team', 1);
        $crawler = $this->client->request('GET', '/team/list');

        $this->assertSelectorCount(1, '#typeFilter label');
        $this->assertSame('grass', $crawler->filter('#typeFilter label')->text());
    }

    private function countQueriesOfTheList(): int
    {
        // Start like a real request: no team already in memory, and only the queries of the request are counted
        $this->getEntityManager()->clear();
        static::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->enableProfiler();
        $this->client->request('GET', '/team/list');
        $this->assertResponseIsSuccessful();

        return $this->client->getProfile()->getCollector('db')->getQueryCount();
    }
}
