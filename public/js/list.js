$(".selectpicker").change(function () {
    let TypeIdToShow = $(this).val();
    $('tr[id^="team_id_"]').each(function () {
        let visibleRow = false;
        let IDs = [];
        $(this).find('td ul li').each(function () {
            IDs.push(parseInt($(this).data('type')));
        });
        for (let i = 0; i < TypeIdToShow.length; i++) {
            if ($.inArray(parseInt(TypeIdToShow[i]), IDs) > -1) {
                visibleRow = true;
                break;
            }
        }
        if (visibleRow) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});
$('select').selectpicker('selectAll');

