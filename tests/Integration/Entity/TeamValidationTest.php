<?php

namespace App\Tests\Integration\Entity;

use App\Entity\Team;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TeamValidationTest extends KernelTestCase
{
    #[DataProvider('validNames')]
    public function testValidNames(string $name): void
    {
        $this->assertCount(0, $this->validate($name));
    }

    public static function validNames(): iterable
    {
        yield 'simple' => ['Team Rocket'];
        yield 'unicode' => ['Squadra Pokémon ⚡'];
        yield 'max length' => [str_repeat('a', 255)];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNames(string $name): void
    {
        $this->assertCount(1, $this->validate($name));
    }

    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('a', 256)];
    }

    private function validate(string $name): iterable
    {
        self::bootKernel();
        $team = (new Team())->setName($name);

        return static::getContainer()->get(ValidatorInterface::class)->validate($team);
    }
}
