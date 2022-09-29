# Symfony Pokémon team planner

This is a little "for-fun" app

I sourced the Pokémon data from the following sources:

- The general Pokémon data came from [The Open Pokemon API](https://pokeapi.co/).
- The general Pokémon type images came from [Veekun](https://veekun.com/dex/media/).

The data from this site comes from the Open PokeAPI, and images come from Veekun.
I claim no commercial/intellectual rights to these data sets, just merely using them to create something neat and learn
about some cool tools.

## Installation

You can install this program by executing the following commands:

```bash
git clone https://github.com/MoroAlberto/symfony_pokemon_team.git
docker-compose up -d
symfony server:start -d
symfony console doctrine:schema:create
```

After this, [go to localhost](http://127.0.0.1/) to create your Pokémon team to try to excel at the Pokémon
tournament!.
