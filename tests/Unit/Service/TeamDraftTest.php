<?php

namespace App\Tests\Unit\Service;

use App\Entity\Team;
use App\Service\TeamDraft;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class TeamDraftTest extends TestCase
{
    private TeamDraft $draft;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->draft = new TeamDraft($requestStack);
    }

    public function testDraftIsEmptyByDefault(): void
    {
        $this->assertSame([], $this->draft->all());
        $this->assertFalse($this->draft->isFull());
    }

    public function testAddStoresThePokemonUnderTheReturnedToken(): void
    {
        $token = $this->draft->add(['name' => 'pikachu']);

        $this->assertTrue($this->draft->has($token));
        $this->assertSame([$token => ['name' => 'pikachu']], $this->draft->all());
    }

    public function testTokensAreUniqueAndNotGuessable(): void
    {
        $tokens = [];
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $tokens[] = $this->draft->add(['name' => 'pikachu']);
        }

        $this->assertCount(Team::MAX_POKEMON, array_unique($tokens));
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $token);
        }
    }

    public function testGetReturnsThePokemonInTheRequestedOrder(): void
    {
        $first = $this->draft->add(['name' => 'bulbasaur']);
        $second = $this->draft->add(['name' => 'charmander']);

        $this->assertSame([['name' => 'charmander'], ['name' => 'bulbasaur']], $this->draft->get([$second, $first]));
    }

    public function testHasRejectsUnknownTokens(): void
    {
        $this->draft->add(['name' => 'pikachu']);

        $this->assertFalse($this->draft->has('0123456789abcdef'));
    }

    public function testDraftIsFullAfterMaxPokemon(): void
    {
        for ($i = 0; $i < Team::MAX_POKEMON - 1; ++$i) {
            $this->draft->add(['name' => 'pikachu']);
        }
        $this->assertFalse($this->draft->isFull());

        $this->draft->add(['name' => 'pikachu']);

        $this->assertTrue($this->draft->isFull());
    }

    public function testAddingToAFullDraftFails(): void
    {
        for ($i = 0; $i < Team::MAX_POKEMON; ++$i) {
            $this->draft->add(['name' => 'pikachu']);
        }

        $this->expectException(\LogicException::class);

        $this->draft->add(['name' => 'one too many']);
    }

    public function testClear(): void
    {
        $token = $this->draft->add(['name' => 'pikachu']);

        $this->draft->clear();

        $this->assertSame([], $this->draft->all());
        $this->assertFalse($this->draft->has($token));
    }
}
