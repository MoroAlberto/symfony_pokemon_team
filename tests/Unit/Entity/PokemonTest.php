<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Ability;
use App\Entity\Pokemon;
use App\Entity\Type;
use PHPUnit\Framework\TestCase;

class PokemonTest extends TestCase
{
    public function testAddTypeIsIdempotent(): void
    {
        $pokemon = new Pokemon();
        $type = (new Type())->setName('fire');

        $pokemon->addType($type);
        $pokemon->addType($type);

        $this->assertSame([$type], $pokemon->getTypes()->toArray());
    }

    public function testRemoveType(): void
    {
        $pokemon = new Pokemon();
        $fire = (new Type())->setName('fire');
        $flying = (new Type())->setName('flying');
        $pokemon->addType($fire)->addType($flying);

        $pokemon->removeType($fire);

        $this->assertSame([$flying], array_values($pokemon->getTypes()->toArray()));
    }

    public function testAddAbilityIsIdempotent(): void
    {
        $pokemon = new Pokemon();
        $ability = (new Ability())->setName('blaze');

        $pokemon->addAbility($ability);
        $pokemon->addAbility($ability);

        $this->assertSame([$ability], $pokemon->getAbilities()->toArray());
    }

    public function testRemoveAbility(): void
    {
        $pokemon = new Pokemon();
        $ability = (new Ability())->setName('blaze');
        $pokemon->addAbility($ability);

        $pokemon->removeAbility($ability);

        $this->assertCount(0, $pokemon->getAbilities());
    }

    public function testSetters(): void
    {
        $pokemon = (new Pokemon())
            ->setName('charizard')
            ->setBaseExperience(240)
            ->setSprite('https://example.com/6.png');

        $this->assertSame('charizard', $pokemon->getName());
        $this->assertSame(240, $pokemon->getBaseExperience());
        $this->assertSame('https://example.com/6.png', $pokemon->getSprite());
        $this->assertNull($pokemon->getTeam());
    }
}
