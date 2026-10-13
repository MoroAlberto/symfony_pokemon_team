<?php

namespace App\Tests\Functional;

class HomepageTest extends FunctionalTestCase
{
    public function testHomepage(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome Trainer!');
        $this->assertSame('/team/create', $crawler->selectLink('Create new team')->attr('href'));
        $this->assertSame('/team/list', $crawler->selectLink('List of teams')->attr('href'));
    }

    public function testTeamRedirectsToHomepage(): void
    {
        $this->client->request('GET', '/team');

        $this->assertResponseRedirects('/');
    }

    public function testUnknownPageReturns404(): void
    {
        $this->client->request('GET', '/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
