<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The custom error pages are shown when debug is off, as in production.
 */
class ErrorPagesTest extends FunctionalTestCase
{
    protected function createTestClient(): KernelBrowser
    {
        return static::createClient(['debug' => false]);
    }

    public function testUnknownPageShowsTheCustom404Page(): void
    {
        $crawler = $this->client->request('GET', '/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', 'Page not found');
        $this->assertSame('/', $crawler->selectLink('Back to the homepage')->attr('href'));
        $this->assertSelectorExists('nav.navbar', 'The page uses the layout of the app');
    }

    public function testUnknownTeamShowsTheCustom404Page(): void
    {
        $this->client->request('GET', '/team/999999/edit');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextContains('h1', 'Page not found');
    }

    public function testOtherErrorsShowTheCustomErrorPage(): void
    {
        $this->client->request('GET', '/pokemon/new');

        $this->assertResponseStatusCodeSame(405);
        $this->assertSelectorTextContains('h1', 'Something went wrong');
        $this->assertSelectorTextContains('main', '405');
        $this->assertSelectorExists('a[href="/"]');
    }
}
