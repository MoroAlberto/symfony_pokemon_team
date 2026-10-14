const team = document.getElementById('PokemonTeam');
const tokensField = document.querySelector('[data-pokemon-tokens]');
const draftField = document.querySelector('[data-team-draft]');
const catchButton = document.getElementById('catchPokemon');

function freeSlots() {
    return team.getElementsByClassName('free');
}

function shownTokens() {
    return Array.from(team.querySelectorAll('[data-token]')).map(card => card.dataset.token);
}

function updateTokensField() {
    tokensField.value = JSON.stringify(shownTokens());
}

// PokeAPI names look like "mr-mime"
function readable(name) {
    return name.replaceAll('-', ' ');
}

// "mr-mime" -> "Mr Mime", like the text-capitalize class does in the cards
function title(name) {
    return readable(name).replace(/\b\w/g, letter => letter.toUpperCase());
}

function typeBadge(type) {
    const badge = document.getElementById('typeBadgeTemplate').content.firstElementChild.cloneNode(true);
    badge.classList.replace('type-', 'type-' + type);
    badge.textContent = type;
    return badge;
}

// Replaces an empty slot with the card of the caught Pokémon, built from the <template> rendered by Twig
function fillSlot(slot, pokemon) {
    const card = document.getElementById('pokemonCardTemplate').content.firstElementChild.cloneNode(true);
    const field = name => card.querySelector(`[data-field="${name}"]`);
    field('sprite').src = pokemon.sprite;
    field('sprite').alt = pokemon.name;
    field('name').textContent = readable(pokemon.name);
    field('baseExperience').textContent = pokemon.baseExperience;
    field('types').replaceChildren(...pokemon.types.map(typeBadge));
    field('abilities').textContent = pokemon.abilities.map(readable).join(', ');
    card.dataset.token = pokemon.token;
    slot.replaceWith(card);
}

async function addPokemonToTeam() {
    if (freeSlots().length === 0) {
        notify('A trainer cannot own more than 6 Pokémon.', 'warning');
        return;
    }

    catchButton.disabled = true;
    try {
        // Send the Pokémon shown on the page, so the server forgets any whose response was lost
        const response = await fetch(team.dataset.newPokemonUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({draft: draftField.value, keep: shownTokens()}),
        });
        const data = await response.json();
        if (!response.ok) {
            notify(data.error);
            return;
        }
        fillSlot(freeSlots()[0], data);
        notify(`Gotcha! ${title(data.name)} was caught!`, 'success');
        updateTokensField();
    } catch (error) {
        notify('Unable to catch a Pokémon, please try again.');
    } finally {
        catchButton.disabled = false;
    }
}

catchButton.addEventListener('click', addPokemonToTeam);

document.getElementById('formTeam').addEventListener('submit', event => {
    if (shownTokens().length === 0) {
        notify("Please add at least one Pokémon to your team by pressing the \"Gotta Catch 'Em All\" button.", 'warning');
        event.preventDefault();
    }
});

updateTokensField();
