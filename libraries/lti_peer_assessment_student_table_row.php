
<?php
# @Author: ps158
# @Date:   2017-03-28T09:28:19+11:00
# @Last modified by:   ps158
# @Last modified time: 2017-04-12T11:27:04+10:00


$resource_link_id = ee()->config->_global_vars['resource_link_id'];

// takes $row as input
if(!array_key_exists('student_table_plugin_headers', $vars)) {
  $vars['student_table_plugin_headers'] = array();
  $vars['student_table_plugin_col_indexes'] = array();
  $vars['student_table_scripts'] = array();
  $vars['student_table_actions'] = array();
  $vars['student_table_classes'] = array();
}
$action_id = 0;

if(!array_key_exists($plugin, $vars['student_table_plugin_headers'])) {
  $header7 = ee()->session->userdata('group_id') === 1 ? lang('student_table_header7') : '';
  $vars['student_table_plugin_headers'][$plugin] = array_merge($vars['student_table_plugin_headers'], !empty($header7) ? array(lang('student_table_header6'), $header7) : array(lang('student_table_header6')) );
  $vars['student_table_plugin_col_indexes'][$plugin] = array_merge($vars['student_table_plugin_col_indexes'], !empty($header7) ? array('lti_peer_assessment_unlock', 'lti_peer_assessment_clear') : array('lti_peer_assessment_unlock'));
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

      ee()->db->where(array('class' => ucfirst($plugin), 'method' => 'instructor_group_mark'));
      $result = ee()->db->get('actions');
      $action_id = $result->row()->action_id;

      $vars['student_table_actions'][$plugin]['instructor_group_mark'] = $action_id;
}
if(!array_key_exists($plugin, $vars['student_table_classes'])) {
      ee()->db->where(array('class' => ucfirst($plugin), 'method' => 'helper_user_has_assessed'));
      $result = ee()->db->get('actions');
      $action_id = $result->row()->action_id;
      $vars['student_table_actions'][$plugin]['helper_user_has_assessed'] = $action_id;
}

if(isset($row['group_id'])) {

    ee()->db->where(array("member_id" => $row['member_id'], "group_context_id" => $row['group_context_id'], "group_id" => $row["group_id"]));
    $result = ee()->db->limit(1)->get('lti_peer_assessments');
    $ra = $result->row();

    $vars['students'][$row['member_id']]['lti_peer_assessment_unlock'] = "<button class='$this->button_class lti_peer_assessment_unlock' data-id='$row[member_id]' data-cxt='$row[group_id]' data-resource-link-id='$resource_link_id' data-igm='$instructor_group_mark'>Unlock</button>";

    if(ee()->session->userdata('group_id') === 1) {
        $vars['students'][$row['member_id']]['lti_peer_assessment_clear'] = "<button class='$this->button_class btn-danger lti_peer_assessment_clear' data-id='$row[member_id]' data-cxt='$row[group_id]' data-resource-link-id='$resource_link_id'>Clear</button>";
    }
}
