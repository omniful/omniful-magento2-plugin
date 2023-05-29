require(["jquery"], function ($) {
  "use strict";

  $(function () {
    $.fn.onoff = function (so) {
      var sd = {
          value: {
            on: "",
            off: "",
          },
          class: "athleteform-onoff",
          wrap: "container",
          button: "button",
        },
        s = $.extend(true, {}, sd, so);
      return $(this).each(function () {
        var a = $(this),
          d1c = s.class + "-" + s.wrap;
        if (
          !a.is("select") ||
          !a.children("option[value=0]").length ||
          !a.children("option[value=1]").length ||
          a.parent("." + d1c).length
        ) {
          return a;
        }
        var b1 = $("<div>").attr({
            class: s.class + "-" + s.button,
          }),
          d1 = $("<div>").attr({
            id: a.attr("id") + "_" + s.wrap,
            class: d1c,
          });
        d1.attr("class", d1.attr("class"));
        if (a.is(":disabled")) {
          //d1.toggleClass('disabled', a.is(':disabled'));
        }
        d1.toggleClass("checked", "0" != a.val());
        a.hide().wrap(d1).before(b1);
        d1 = a.parent();
        a.off("change.onofftrigger").on("change.onofftrigger", function (e) {
          d1.toggleClass("disabled", a.is(":disabled"));
          if (d1.hasClass("disabled")) {
            return e.preventDefault();
          }
          d1.toggleClass("checked", "0" != a.val());
        });
        d1.off("click.onofftrigger").on("click.onofftrigger", function (e) {
          if (d1.hasClass("disabled")) {
            return e.preventDefault();
          }
          if (a.is(":enabled")) {
            var value = "0" != a.val() ? "0" : "1";
            a.val(value);
            a[0].dispatchEvent(new Event("change"));
            a.trigger("change");
          }
        });
        return a;
      });
    };

    $(".on-off-trigger").onoff();
  });
});
