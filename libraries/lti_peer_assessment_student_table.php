<?php
$sample_members = ee()->config->item('sample_members');
$sample_length = count($sample_members["ids"]);

ee()->db->where(array("lti_peer_assessments.resource_link_id" => $resource_link_id));
ee()->db->join("lti_group_contexts", "lti_group_contexts.member_id = lti_peer_assessments.assessor_member_id");
ee()->db->group_by("assessor_member_id");
$res = ee()->db->get("lti_peer_assessments");
$total_students = $res->num_rows() - $sample_length;
$vars['lti_peer_assessment']['total_students'] = array("label" => "Total Students: ",  "value" => $total_students);

$completed_peer_assessment = 0;
$id = array();
foreach($res->result_array() as $row) {

    ee()->db->where(array("assessor_member_id" => $row['assessor_member_id'], "locked" => '1', "resource_link_id" => $resource_link_id));
    ee()->db->join("lti_group_contexts", "lti_group_contexts.member_id = lti_peer_assessments.assessor_member_id");
    ee()->db->group_by("assessor_member_id");
    $res = ee()->db->get('lti_peer_assessments');
    if($res->num_rows() > 0) {
        if(! in_array($row['assessor_member_id'], $sample_members["ids"])) {
            ++$completed_peer_assessment;
            $ids[] = $row['assessor_member_id'];
        }
    }
}

$members_tbl = ee()->db->dbprefix('members');

if(isset($plugin_filters["filter_submitted"])) {
      if($sub = $plugin_filters["filter_submitted"]) {
        if(isset($ids) && count($ids) > 0) {
            if($sub === "ns") {
              $str = implode(",", $ids);
              $wsql .= " AND $members_tbl.member_id NOT IN($str)";
            }
            if($sub === "s") {
              $str = implode(",", $ids);
              $wsql .= " AND $members_tbl.member_id IN($str)";
            }
        }
      }
}

$percentage = $total_students > 0 ? floor(($completed_peer_assessment / $total_students) * 100) : 0;

$vars['lti_peer_assessment']['completed_peer_assessment'] = array("label" => "Students Completed: ",  "value" => $completed_peer_assessment);
$vars['lti_peer_assessment']['percentage_completed'] =  array("label" => "Percentage Completed: ",  "value" => $percentage. "%");
$vars['lti_peer_assessment']['heading'] = array("text" => "Peer Assessment Completion Rates");
