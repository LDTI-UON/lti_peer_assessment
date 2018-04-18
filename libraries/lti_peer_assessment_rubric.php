<?php
$raw_id = ee()->input->get('rubric_id');

$parsed = explode("|", $raw_id);

$rubric_id = $parsed[0];

$rubric_score = isset($parsed[1]) ? $parsed[1]: 0;

if(!empty($rubric_id)) {

    $show_scores = "0";

    if(isset($_POST['show_scores'])) {
        $show_scores = ee()->input->post('show_scores');
    }

    $where =  array("resource_link_id" => $this->resource_link_id);
    $res = ee()->db->get_where('lti_course_link_resources', $where);

    if($rubric_id === 'del') {
        if($res->num_rows() == 0) {
          $settings = array("rubric" => array("show_column_scores" => $show_scores));
          $ser = serialize($settings);
          $data = array("course_id" => $this->course_id, "resource_link_id" => $this->resource_link_id, "rubric_id" => "no_rubric", "resource_settings" => $ser);
              ee() -> db -> insert('lti_course_link_resources', $data);
        }
        else {
            ee() -> db -> update('lti_course_link_resources', array('rubric_id' => 'no_rubric'),  $where);
        }
    } else {
        $settings = array("rubric" => array("show_column_scores" => $show_scores));
        $ser = serialize($settings);
        $data = array("course_id" => $this->course_id, "resource_link_id" => $this->resource_link_id, "rubric_id" => $rubric_id, "resource_settings" => $ser);

        if($res->num_rows() == 0) {
            ee() -> db -> insert('lti_course_link_resources', $data);
        } else {
            ee()->db->where("resource_link_id", $this->resource_link_id);
            ee()->db->update("lti_course_link_resources", $data);
        }
    }
}

// rubric overrides score setting.
if(!empty($rubric_score)) {
    $where = array("course_key" => $this->course_key, "institution_id" => $this->institution_id);

    $res = ee()->db->get_where('lti_instructor_settings', $where);
    $settings = unserialize($res->row()->plugins_active);
    if($settings) {
        $settings['lti_peer_assessment']['total_score'] = $rubric_score;
    } else {
      throw new Exception("No settings found for lti peer assessment $this->course_key $this->institution_id");
    }

    $data = array('plugins_active' => serialize($settings));

    if($res->num_rows() > 0) {
        ee()->db->where($where);
        ee()->db->update("lti_instructor_settings", $data);
    }
}
