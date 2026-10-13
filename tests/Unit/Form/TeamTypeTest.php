<?php

namespace App\Tests\Unit\Form;

use App\Entity\Team;
use App\Form\TeamType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;

// TypeTestCase creates an event dispatcher mock without expectations
#[AllowMockObjectsWithoutExpectations]
class TeamTypeTest extends TypeTestCase
{
    public function testSubmitUpdatesTheTeamName(): void
    {
        $team = new Team();
        $form = $this->factory->create(TeamType::class, $team);

        $form->submit(['name' => 'Team Rocket', 'pokemonTokens' => '["abc"]']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('Team Rocket', $team->getName());
        $this->assertSame('["abc"]', $form->get('pokemonTokens')->getData());
    }

    public function testEmptyNameIsSubmittedAsEmptyString(): void
    {
        $team = new Team();
        $form = $this->factory->create(TeamType::class, $team);

        $form->submit(['name' => '']);

        $this->assertSame('', $team->getName());
    }

    public function testPokemonFieldCanBeDisabled(): void
    {
        $form = $this->factory->create(TeamType::class, new Team(), ['with_pokemon' => false]);

        $this->assertTrue($form->has('name'));
        $this->assertFalse($form->has('pokemonTokens'));
    }
}
