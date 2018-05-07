<?php
$selected1 = "";
$selected2 = "";

$sub = isset($plugin_filters["filter_submitted"]) ? $plugin_filters["filter_submitted"] : NULL;

if($sub) {
    if($sub == "ns") {
          $selected2 = "selected";
    }
    if($sub == "s") {
          $selected1 = "selected";
    }
}

$ppage_output .= "<p>
                  <label for='filter_submitted1'>Students submitted: ".form_checkbox(array('name' => 'filter_submitted', 'id' => 'filter_submitted1', 'value' => 's', 'checked' => $selected1))."</label>";
$ppage_output .= "<label for='filter_submitted2'>Students not submitted: </label>".form_checkbox(array('name' => 'filter_submitted', 'id' => 'filter_submitted2', 'value' => 'ns', 'checked' => $selected2))."</label>";
$ppage_output .= "<br><button id='clear_filters' name='clear_filters' class='btn btn-default' title='Clear Search Filters'>Clear Filters</button></p> "
?>
