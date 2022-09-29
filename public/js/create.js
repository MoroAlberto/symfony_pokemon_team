function addPokemonToTeam() {
    $.getJSON("/pokemon/new", function (data) {
        let i;
        let pokemon = '<img src="' + data.sprite + '" class="card-img-top" alt="' + data.name + '">' +
            '<div class="card-body">' +
            '<h5 class="card-title">' + data.name + '</h5>' +
            '<h6 class="card-subtitle mb-2 text-muted">' + data.baseExperience + '</h6>' +
            '<p class="card-text">Abilities:';
        for (i = 0; i < data.abilities.length;) {
            pokemon += ' ' + data.abilities[i]['name'];
            i++;
            if (data.abilities.length !== i) {
                pokemon += ',';
            }
        }
        pokemon.substring(0, pokemon.length - 1);
        pokemon += '</p><p class="card-text">Types: ';
        for (i = 0; i < data.types.length;) {
            pokemon += ' ' + data.types[i]['name'];
            i++;
            if (data.types.length !== i) {
                pokemon += ',';
            }
        }
        pokemon.substring(0, pokemon.length - 1);
        pokemon += '</p></div>';
        const slot = $("#PokemonTeam div.free").first();
        if (slot.length <= 0) {
            alert("Error: A trainer cannot own more than 6 pokemon");
        }
        slot.removeClass("free");
        slot.html(pokemon);
        const HiddenPokemonList = $("#team_ajaxString");
        if (HiddenPokemonList.val() === "") {
            HiddenPokemonList.val(JSON.stringify(data));
        } else {
            HiddenPokemonList.val(HiddenPokemonList.val() + "," + JSON.stringify(data));
        }
    });
}

$("form#formTeam").submit(function (event) {
    event.preventDefault();
    const form = this;
    const slot = $("#PokemonTeam div.free");
    if (slot.length >= 6) {
        alert("Error: Please add at least one Pokemon on your team. Pressing the button Gotta Catch 'Em All");
        return false;
    }
    form.submit();
});
