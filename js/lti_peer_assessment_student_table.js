
//ar id = $(e.target).attr("data-id");

var request_obj = [];
var group_names = {};
var group_buttons = [];

var load_ticks = function() {
$('.table tr td:nth-of-type(6) > button').each(function(i, v) {
      var id = $(v).attr("data-id");
      var cxt = $(v).attr("data-cxt");
      var rli = $(v).attr("data-resource-link-id");

      request_obj.push({id: id, cxt: cxt, rli: rli, ha: false, t: false});

      var name = $(v).closest('td').prev('td').text();

      if(cxt && !(cxt in group_names)) {
            group_names[cxt] = {name: name, count: 1, td: $(v).parent()};
      } else if(cxt){
            group_names[cxt].count++;
      }
});

var o = null;

$("table tr button").prop("disabled", true);

$.post(base_url+"?ACT="+acts.lti_peer_assessment.helper_user_has_assessed,
  {map: request_obj},

    function(res) {
        res = JSON.parse(res);

        res.forEach(function(v,i) {

              var but = $("button[data-id='"+v.id +"']");
              var td = but.parents('tr').find('td:nth-child(1)');

              if ( td.hasClass("success") ) {

                  if(v.ha && v.cxt) {
                      td.addClass('tick');
                      but.prop("disabled", false);
                  } else if(v.t && v.cxt) {
                      td.addClass('thinking');
                  }
              } else {
                  if(v.ha && v.cxt && v.t) {
                      td.addClass('problem');
                  }
              }
        });
    }
    );
};

load_ticks();

$(".lti_peer_assessment_unlock").bind("click", function(e) {
  var id = $(e.target).attr("data-id");
  var cxt = $(e.target).attr("data-cxt");
  var rli = $(e.target).attr("data-resource-link-id");

  $.post(base_url+"?ACT="+acts.lti_peer_assessment.unlock_last_submission, {
      id: id,
      cxt: cxt,
      rli: rli
  }, function(r) {
      var d = JSON.parse(r);
      if(d.rows_affected > 0) {
            bootbox.alert("The student can now access their last assessment again.");
      } else {
            bootbox.alert("The assessment was already unlocked.");
      }

      $(e.target).parents('tr').find('td:nth-child(1)').removeClass('tick').addClass('thinking');
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
    }, function(r) {
      var d = JSON.parse(r);
      if(d.rows_affected > 0) {
            bootbox.alert("The student's submissions were deleted.");
      } else {
            bootbox.alert("Nothing to delete.");
      }
    });
  });
});

var enable_group_marking = function() {
var str = '<button id="group_mark" class="hover_button" data-cxt="0"></button>';
var button = $(str);
var rollover_style = "position: absolute; background-color: #f7d77e; border: thin solid red; padding: 0.2em; opacity: 1.0; z-index: 10000";
$(".spark-only").show();
$(".mean-only").hide();

$("tr td:nth-child(5)").bind("mouseover", function(e) {
    $(e.target).addClass("add-pointer");
    var $btn = $(e.target).next('td').find('button');
    var cxt = $btn.attr('data-cxt');
    var rli = $btn.attr("data-resource-link-id");
    var igm = $btn.attr("data-igm");

    if(!cxt) return false;

    $('button[data-cxt="'+cxt+'"]').parent().siblings().css({'background-color' : 'lightgreen'}).not(this).not("#msg").css({'opacity': 0.3 });
  //  console.log(igm);
    if(igm) {
      $(e.target).append("<p id='msg' style='"+rollover_style+"'>Current mark for '"+group_names[cxt].name + "': <br><span style='font-size:14pt'>"+igm+"</span><br />Click to change.</p>");
    } else {
      $(e.target).append("<p id='msg' style='"+rollover_style+"'>Give a group mark to '"+group_names[cxt].name+"'</p>");
    }

    var prompt;
    $(e.target).bind('click', function() {
      if(prompt) return false;

              prompt = bootbox.prompt("Please give a group mark for '"+group_names[cxt].name+"'<br><br>(enter -1 to clear grade)",
                function(res) {
                  if(isNaN(res)) {
                    bootbox.alert("The mark must be numeric");
                  }
                  if(res) {
                    $.post(base_url+"?ACT="+acts.lti_peer_assessment.instructor_group_mark,
                    {mark: res, cxt: cxt, rli: rli}, function(d) {
                        var n = JSON.parse(d);
                        if(n.rows_affected > 0) {
                            if(res == -1) {
                              bootbox.alert("The grade was cleared");
                            } else {
                              bootbox.alert("The grade was updated to: "+res);
                            }
                        } else {
                            bootbox.alert("Everything is up to date.");
                        }
                        prompt = null;
                        var mark = res > -1 ? res : "";

                        $('button[data-cxt="'+cxt+'"]').each(
                            function(i,v){
                                $(v).attr("data-igm", mark);
                            }
                        );

                        $(e.target).unbind("click");
                    });
                  }
                }
              );
    });

}).bind("mouseout", function(e) {
    var cxt = $(e.target).next('td').find('button').attr('data-cxt');
    $(e.target).find('#msg').remove();
      $('button[data-cxt="'+cxt+'"]').parent().siblings().css('background-color', '').css({opacity: '', 'font-weight': ''});
      $(e.target).unbind("click");
});
};
/* enable group marking features only with SPARK */
$(document).ready(function() {
    if($('select[name="score_calculation"] option[value="spark_plus"]:selected').length > 0) {
        enable_group_marking();
    }

    $('select[name="score_calculation"]').bind("change", function(e) {
        var val = $(e.target).find("option:selected").val();
        if(val === 'spark_plus') {
            enable_group_marking();
            $(".spark-only").show();
            $(".mean-only").hide();
            bootbox.alert("In SPARK mode instructors give a group mark which is moderated by the students marks.");
        } else {
            $("tr td:nth-child(5)").unbind("mouseover").unbind("mouseout");
            $(".mean-only").show();
            bootbox.alert("In mean mode students give each other a grade without instructor involvement. There is no self-assessment in this mode.")
        }
    });

    $('input[name="filter_submitted"]').bind("change", function (e) {
  			 $("form#filters").submit();
  	});

    $('button#clear_filters').bind("click", function(e) {
        e.preventDefault();
          $('input[name=filter_submitted]').prop('checked', false);
          //$("form#filters").children('input[name="filter_submitted"]').remove();
          $("form#filters").submit();
    });
});
