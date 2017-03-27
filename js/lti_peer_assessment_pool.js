/**
 *   Grade pool functions
 */
$("button#assess").attr('disabled','disabled');

var getTotal = function() {
			var total = 0;
			$(".student_assess").removeClass('focusUser');
			$(".student_assess").each(function(i,o) {
				if(isNaN($(o).val())) {
					$(this).addClass('focusUser');
					return false;
				}
				total += Number($(o).val());
				if(total > <?= $js_vars["score"] ?>) {
					$(this).addClass('focusUser');
				}
			});
			return total;
		};
		var total = getTotal();
		
		$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You have "+total+" points to distribute.</td>");	
		
		$("table.pa-table").on('keyup', ".student_assess", function() {
				$(".errormsg,.okmsg").remove();
				var total = getTotal();
				
				if(total === false) {
					$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>You can only enter numeric values for the score.</td>");
					$("button#assess").attr('disabled','disabled');
					return false;
				}
				
				var diff = Math.abs(<?= $js_vars["score"] ?> - total);
				if(total > <?= $js_vars["score"] ?>) {
					$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>You've given out "+ diff +" too many points, please amend.</td>");
					$("button#assess").attr('disabled','disabled');
				} else if(total < <?= $js_vars["score"] ?>) {
					$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You've got "+ diff +" points left, please distribute them.</td>");
					$("button#assess").attr('disabled','disabled');
				} else if(total == <?= $js_vars["score"] ?>) {
					$(".student_assess").last().closest('tr').after("<tr class='okmsg'><td colspan='3'>You've distributed all 100 points.</td>");
					$("button#assess").removeAttr('disabled');
				} else {
				    $("button#assess").attr('disabled','disabled');
				}
		});
		
		var pool_validation = function() {
			var total = getTotal();
			if(total > <?= $js_vars["score"] ?> || total < <?= $js_vars["score"] ?>) {
				$(".student_assess").last().closest('tr').after("<tr class='errormsg'><td colspan='3'>Please ensure that your scores add up to 100.</td>");
				$("button#assess").attr('disabled','disabled');
				return false;
			}
		return true;
		}
