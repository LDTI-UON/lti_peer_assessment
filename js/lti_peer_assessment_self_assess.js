var ltipa = ltipa || {};
// check if self has assessed
ltipa.check_yourself =  function() {
      var val = 0, val2 = "NaN";

      val = $("form#assessments #self_assess_row textarea")[0].value.length;
      val2 = $("form#assessments #self_assess_row .student_assess")[0].value;
      //console.log(val," ",val2);
    /*  var _c = true;
      if(val2 === 0) {
          confirm("Are you sure you want to award yourself a 0?",
            function() {
                _c = false;
            });
      }*/
      if(val > 0 && val2 > 0) {
          $("form#assessments tr input, form#assessments tr button, form#assessments tr textarea").prop("disabled", false);
          $("form#assessments tr[style]").css({"opacity": "inherit"});
      } else {
          $("form#assessments tr").not(".keepfocus").css({"opacity" : "0.2"}).find("input, button, textarea").prop("disabled", true);
      }
  };

$("#assessments table").on("updateTableState", function() {
    if(ltipa) {
            ltipa.check_yourself();
    }
});

$(document).ready(function() {
      // move self to top
    var self_assess_row = $("form#assessments #self_assess_row").detach();
    $(self_assess_row).prependTo('form#assessments table tbody');

    $("#assessments #self_assess_row input, form#assessments #self_assess_row textarea").on('keyup', ltipa.check_yourself);

    $("#assessments table").trigger("updateTableState");
});
