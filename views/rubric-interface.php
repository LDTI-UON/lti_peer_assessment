<style>
button#openRubric {
    margin-bottom: 2%;
    margin-left: 3%;
}
#rubric_container {
  position: absolute;
  top: 0;
  left: 0;
  display: none;
}
</style>

<div id='rubricWrapper'>
<?= $form ?>

<div id='floating' style='position: absolute; left: 2%; top: 5%; width: 96%; height: auto; display: none'> </div>

<script type="text/javascript">
    window.flashRow = function(input_id) {
      setTimeout(function() {
          $("#score_"+input_id).closest('tr').find('td').css({ opacity: 0.0 }).animate({ opacity: 1.0 }, 500).animate({ opacity: 0.0 }, 500).animate({ opacity: 1.0 });
      }, 300);
    };

		var session_expired = function(data) {
			if( $(data).find('.session_expired').length > 0 ) {

				bootbox.confirm('Your session has expired.\\n Please return to the course and click the link again',
          function() {
    						window.history.back();
          }
        );

			}

			return false;
		};

		var populate_rubric = function(id, input_id, pre_pop, user, is_instructor) {
			if(id != 0) {


        $.post('<?= $base_url ?>/rubric',  {
          'no_reload': '1',
          'pre_pop': pre_pop,
          'user' : user,
          'id' : id,
          'input_id' : input_id,
         }, function(data) {
              if(!session_expired(data)) {
                  if($("#rubric_container").length === 0) {
                        $("body").append("<div id=rubric_container></div>");
                  }

                  $("#rubric_container").html(data).show();
                  $(".contentPane").css({ margin: 0 });
                  $(".container-fluid").hide();
              }
        });


			}
		};

    	$("#rubricWrapper").on("click", "button#openRubric", function(e) {
    				e.preventDefault();
    				var id = $(e.target).data("val");
    				var prev = $(this).prev().attr("id");
					  var pre_pop = $(this).next().attr("value");
			      var user = $(e.target).data("screen_name");

            prev = prev.split("_")[1];
    				populate_rubric(id, prev, pre_pop, user);
                            
    	});

</script>
</div>
