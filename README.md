# Symfony Pokémon team planner

This is a little "for-fun" app: draw random Pokémon, build teams of up to six and browse them by type.

The Pokémon data and sprites come from [The Open Pokemon API](https://pokeapi.co/).

I claim no commercial/intellectual rights to these data sets, just merely using them to create something neat and learn
about some cool tools.

## Getting started

The app is built on Symfony 8.1 and needs PHP 8.5. The data is stored in an SQLite file (`var/data.db`), so there is
no database server to set up.

### With Docker (recommended)

You only need Docker and `make`:

```bash
git clone https://github.com/MoroAlberto/symfony_pokemon_team.git
cd symfony_pokemon_team
make start
```

Then [go to localhost:8000](http://localhost:8000) to create your Pokémon team and try to excel at the Pokémon
tournament!

Type `make` to see all the commands:

| Command        | What it does                                              |
|----------------|-----------------------------------------------------------|
| `make start`   | Install everything and start the app                      |
| `make up`      | Start the app (`APP_PORT=8001 make up` for another port)  |
| `make down`    | Stop the app                                              |
| `make test`    | Run the test suite                                        |
| `make db`      | Create or update the database schema                      |
| `make logs`    | Follow the web server logs                                |
| `make shell`   | Open a shell in the PHP container                         |

### Without Docker

With PHP 8.5+ (and the `pdo_sqlite` and `mbstring` extensions) and Composer:

```bash
composer install
php bin/console doctrine:schema:update --force
php -S localhost:8000 -t public
```

## How it works

- **Catching**: the "Gotta Catch 'Em All" button asks the server for a Pokémon. The server picks a random one on
  PokeAPI and keeps it in a *draft* of the team stored in the session (`src/Service/TeamDraft.php`).
- **Saving**: the browser only sends back the tokens of the Pokémon it shows. The server saves the matching Pokémon
  from the draft, so the Pokémon and their stats cannot be forged by the browser. A team has 1 to 6 Pokémon.
- **Several tabs**: each create page has its own draft, so you can build several teams at the same time.
- **List**: the teams and the types are cached (`TeamService`, `TypeService`), and the cache is cleared whenever a
  team is created or edited.

## Tests

The tests use their own SQLite database (`var/test.db`) and a fake PokeAPI, so they need no network access:

```bash
make test
# or, without Docker
vendor/bin/phpunit
```

- `tests/Unit`: entities, form and services in isolation
- `tests/Integration`: services with the real container and database
- `tests/Functional`: the pages, as a browser would use them
