$("#percToggle").click(function(e) {
    var arr = $(this).closest("table").find("td");

    if ($(this).hasClass('perc_on')) {

        arr.each(function() {
            var el = $(this)[0];
            var str = $.data(el, "text").value;

            if (isNaN(str)) {
                $(this).text(str);
            }
        });
        $(this).removeClass('perc_on');

        $(this).text("View as Percentage");
    } else {
        $(this).addClass('perc_on');

        arr.each(function() {
            var el = $(this)[0];
            //console.log($(el).html());
            var str = $(this).text();
            var nos = str.split('/');

            var n = parseFloat(nos[0]);
            var m = parseFloat(nos[1]);

            var perc = (n / m) * 100;
            perc = parseFloat(Math.round(perc).toFixed(1));

            $(this).text(perc.toString() + " %");
            $.data(el, "text", {
                value: str
            });
        });

        $(this).text("View as Raw Score");
    }
});
