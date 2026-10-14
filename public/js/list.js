// Shows only the teams having at least one of the checked types (teams without Pokémon are always shown)
const filters = document.querySelectorAll('[data-type-filter]');

function applyFilter() {
    const checked = new Set(Array.from(filters).filter(filter => filter.checked).map(filter => filter.value));
    document.querySelectorAll('tr[data-types]').forEach(row => {
        const types = row.dataset.types.split(' ').filter(type => type !== '');
        row.hidden = types.length > 0 && !types.some(type => checked.has(type));
    });
}

filters.forEach(filter => filter.addEventListener('change', applyFilter));
