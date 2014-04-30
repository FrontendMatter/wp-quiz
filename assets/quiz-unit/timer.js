(function ($) {
    "use strict";

    $(function ()
    {

        var hours = parseInt(quiz_unit_timer.duration.hh) * 60 * 1000,
            minutes = parseInt(quiz_unit_timer.duration.mm) * 60 * 1000,
            seconds = parseInt(quiz_unit_timer.duration.ss) * 1000,
            ts = (new Date()).getTime() + (hours + minutes + seconds);

        $('#quiz-unit-timer').countdown({
            timestamp: ts
        });

    });

})(jQuery);
