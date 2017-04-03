<?php

// takes $row as input
if(!array_key_exists('student_table_plugin_headers', $vars)) {
  $vars['student_table_plugin_headers'] = array();
  $vars['student_table_plugin_col_indexes'] = array();
  $vars['student_table_scripts'] = array();
  $vars['student_table_actions'] = array();
}
if(!array_key_exists($plugin, $vars['student_table_plugin_headers'])) {
  $vars['student_table_plugin_headers'][$plugin] = array_merge($vars['student_table_plugin_headers'], array(lang('student_table_header6'), lang('student_table_header7')));
  $vars['student_table_plugin_col_indexes'][$plugin] = array_merge($vars['student_table_plugin_col_indexes'], array('lti_peer_assessment_unlock', 'lti_peer_assessment_clear'));
}
if(!array_key_exists($plugin, $vars['student_table_scripts'])) {
  $vars['student_table_scripts'][$plugin] = file_get_contents(PATH_THIRD."$plugin/js/$plugin"."_student_table.js");
}
if(!array_key_exists($plugin, $vars['student_table_actions'])) {

      ee()->db->select('action_id');
      ee()->db->where(array('class' => ucfirst($plugin), 'method' => 'unlock_last_submission'));
      $result = ee()->db->get('actions');
      $action_id = $result->row()->action_id;

      $vars['student_table_actions'][$plugin]['unlock_last_submission'] = $action_id;

      ee()->db->where(array('class' => ucfirst($plugin), 'method' => 'clear_last_submission'));
      $result = ee()->db->get('actions');
      $action_id = $result->row()->action_id;

      $vars['student_table_actions'][$plugin]['clear_last_submission'] = $action_id;
}

$vars['students'][$row['member_id']]['lti_peer_assessment_unlock'] = "<button class='$this->button_class lti_peer_assessment_unlock' data-id='$row[member_id]' data-cxt='$row[group_context_id]'>Unlock</button>";
$vars['students'][$row['member_id']]['lti_peer_assessment_clear'] = "<button class='$this->button_class btn-danger lti_peer_assessment_clear' data-id='$row[member_id]' data-cxt='$row[group_context_id]'>Clear</button>";
