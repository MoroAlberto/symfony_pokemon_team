<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014084845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial schema: teams, Pokémon, types and abilities';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotSqlite();

        // Referenced tables first, then the tables holding foreign keys to them
        $this->addSql('CREATE TABLE team (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE pokemon (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, base_experience INTEGER NOT NULL, sprite VARCHAR(255) NOT NULL, team_id INTEGER DEFAULT NULL, CONSTRAINT FK_62DC90F3296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_62DC90F3296CD8AE ON pokemon (team_id)');
        $this->addSql('CREATE TABLE ability (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE TABLE ability_pokemon (ability_id INTEGER NOT NULL, pokemon_id INTEGER NOT NULL, PRIMARY KEY (ability_id, pokemon_id), CONSTRAINT FK_1E1671618016D8B2 FOREIGN KEY (ability_id) REFERENCES ability (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1E1671612FE71C3E FOREIGN KEY (pokemon_id) REFERENCES pokemon (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1E1671618016D8B2 ON ability_pokemon (ability_id)');
        $this->addSql('CREATE INDEX IDX_1E1671612FE71C3E ON ability_pokemon (pokemon_id)');
        $this->addSql('CREATE TABLE type_pokemon (type_id INTEGER NOT NULL, pokemon_id INTEGER NOT NULL, PRIMARY KEY (type_id, pokemon_id), CONSTRAINT FK_4AFDFF06C54C8C93 FOREIGN KEY (type_id) REFERENCES type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4AFDFF062FE71C3E FOREIGN KEY (pokemon_id) REFERENCES pokemon (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4AFDFF06C54C8C93 ON type_pokemon (type_id)');
        $this->addSql('CREATE INDEX IDX_4AFDFF062FE71C3E ON type_pokemon (pokemon_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotSqlite();

        // Tables holding foreign keys first, then the tables they point to
        $this->addSql('DROP TABLE ability_pokemon');
        $this->addSql('DROP TABLE type_pokemon');
        $this->addSql('DROP TABLE pokemon');
        $this->addSql('DROP TABLE ability');
        $this->addSql('DROP TABLE type');
        $this->addSql('DROP TABLE team');
    }

    private function abortIfNotSqlite(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof SQLitePlatform,
            'This migration was written for SQLite.'
        );
    }
}
