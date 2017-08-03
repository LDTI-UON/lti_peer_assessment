
$(".lti_peer_assessment_unlock").bind("click", function(e) {
  var id = $(e.target).attr("data-id");
  var cxt = $(e.target).attr("data-cxt");
  var rli = $(e.target).attr("data-resource-link-id");

  $.post(base_url+"?ACT="+acts.lti_peer_assessment.unlock_last_submission, {
      id: id,
      cxt: cxt,
      rli: rli
  }, function(d) {
      var d = JSON.parse(d);
      if(d.rows_affected > 0) {
            bootbox.alert("The student can now access their last assessment again.");
      } else {
            bootbox.alert("The assessment was already unlocked.");
      }
  });
});

$(".lti_peer_assessment_clear").bind("click", function(e) {
    var id = $(e.target).attr("data-id");
    var cxt = $(e.target).attr("data-cxt");
    var rli = $(e.target).attr("data-resource-link-id");
      bootbox.confirm("<p>This will delete all the users assessments.</p><p>Are you sure?</p>",
        function() {
    $.post(base_url+"?ACT="+acts.lti_peer_assessment.clear_last_submission,
    {
      id: id,
      cxt: cxt,
      rli: rli
    }, function(d) {
      var d = JSON.parse(d);
      if(d.rows_affected > 0) {
            bootbox.alert("The student's submissions were deleted.");
      } else {
            bootbox.alert("Nothing to delete.");
      }
    });
  });
});
