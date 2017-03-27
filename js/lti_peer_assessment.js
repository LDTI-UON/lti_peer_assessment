
		$(document).ready(function() {
		$("style").append('.focusUser { background-color: lightblue; } .okmsg {color: #00748B;}');

		$('.student_assess').keyup(function () {
			if($(this).prop('type') !== 'text') return;

			this.value = this.value.replace(/[^0-9]/g,'');

      var score = <?= $js_vars["score"] ?>;

			if(this.value > score) {
				this.value = score;
			}
			if(this.value < 0) {
				this.value = 0;
			}
		});

		$("button#Save").click(function() {
			$("input[name='locked']").val('0');
			$("form#assessments").submit();
		});

        $(".comment").on("keyup", function(e) {
            $("span#char_count").remove();
            var chars = "<span id='char_count'><br>"+$(this).val().length+"</span>";
            $(this).after(chars);
        });

		$("button#assess").click(function(e) {
			e.preventDefault();

			var formok = true;
			var confirmed = false;
			$("input[type='text']").each(function() {
				var me = this;
				$(me).css('border', 'none');
				if(isNaN($(this).val())) {
					$(me).css('border', '2px solid red');
					formok = false;
				}
			});

			$(".comment").each(function() {
				var me = this;
				$(me).css('border', 'none');
				if($(this).val().length == 0) {
					$(me).css('border', '2px solid red');
					formok = false;
				}
				if($(this).val().length > 65535) {
					var l = $(this).val().length - 65535;
					$(me).css('border', '2px solid red');
					$(me).after("<p style='color:red'><b>Too much text, you are "+l+" characers over the limit of 65535</b></p>");
					formok = false;
				}
			});

			if(typeof pool_validation === 'function') {
				formok = pool_validation();
			}

			if(formok === true) {
				confirmed = bootbox.confirm("Once you have submitted you will not be able to return to this form to amend your marks.  Are you sure?",
				function(confirmed) {
					if(confirmed){
							$("input[name='locked']").val('1');
							$("form#assessments").submit();
					}
				});

			} else {
				bootbox.alert("Please fill in all the comment fields and ensure you only enter numeric values for the score.");
			}
		});

		$(".savemsg, .saveErrorMsg").fadeIn(200).fadeOut(200).fadeIn(200).delay(10000).fadeOut(200);

		// trigger total message
		$('.student_assess').first().trigger('keyup');

		<?php require_once(__DIR__.'/percToggle.js'); ?>

		});
