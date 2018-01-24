var ltipa = ltipa || {};
// check if self has assessed
ltipa.check_yourself =  function() {
      var val = 0, val2 = 0;

      val = $("form#assessments #self_assess_row textarea").val().length;

      if(val > 0) {
          $("form#assessments tr input, form#assessments tr button, form#assessments tr textarea").prop("disabled", false);
          $("form#assessments tr[style]").css({"opacity": "inherit"});
      } else {
          $("form#assessments tr").not(".keepfocus").css({"opacity" : "0.2"}).find("input, button, textarea").prop("disabled", true);
      }
  };

$("#assessments table").on("updateTableState", function() {
<<<<<<< HEAD
    if(ltipa) {
            ltipa.check_yourself();
    }
=======
            ltipa.check_yourself();
>>>>>>> ccef1b4fff8082ca0f7d67bf076b535700760622
});

$(document).ready(function() {
      // move self to top
    var self_assess_row = $("form#assessments #self_assess_row").detach();
    $(self_assess_row).prependTo('form#assessments table tbody');

    $("#assessments #self_assess_row input, form#assessments #self_assess_row textarea").on('keyup', ltipa.check_yourself);

    $("#assessments table").trigger("updateTableState");
});
