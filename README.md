# Symfony Pokémon team planner

This is a little "for-fun" app

I sourced the Pokémon data from the following sources:

- The general Pokémon data came from [The Open Pokemon API](https://pokeapi.co/).

The data and images from this site comes from the Open PokeAPI.
I claim no commercial/intellectual rights to these data sets, just merely using them to create something neat and learn
about some cool tools.

## Installation

You can install this program by executing the following commands:

```bash
git clone https://github.com/MoroAlberto/symfony_pokemon_team.git
docker-compose up -d
symfony server:start -d
symfony console doctrine:schema:create ?
```

Now, [go to localhost](http://127.0.0.1/team/create) to create your Pokémon team to try to excel at the Pokémon
tournament!.

This program have 2 page:

- [create page](http://127.0.0.1/team/create) when you can create your random Pokémon team, chose a powerful name for
  your team and let chance choose the Pokémon.
- [list page](http://127.0.0.1/team/list) when you can check all the created Pokémon teams.

In list page you can modify the name of a team.