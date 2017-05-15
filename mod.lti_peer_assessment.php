<?php
# @Author: ps158
# @Date:   2017-03-28T09:28:19+11:00
# @Last modified by:   ps158
# @Last modified time: 2017-05-04T09:59:22+10:00

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// ------------------------------------------------------------------------
define("PREVIEW_CONTEXT", 999999999);
/**
 * EE Learning Tools Integration Module Front End File.
 *
 * @category	Module
 *
 * @author		Paul Sijpkes
 *
 * @link
 */
class Lti_peer_assessment
{
    private $module_name = 'lti_peer_assessment';
    private $help_url = 'https://bold.newcastle.edu.au/padocs/';

    private $lti_object = null;

    private $form_inited = false;

    private $grade_column_default = '0';
    private $comment_column_default = 'n/a';
    private $allow_self_assessment_default = 0;
    private $total_score_default = 100;
    private $group_name_space_fillers = array('_gc_');
    private $include_self_in_mean_score = 0;

    private static $apeg_url = '';

    private $table_class = 'table';
    private $button_class = 'btn button-default';
    private $score_class = 'score_spacer';
    private $img_class = 'img-circle img-responsive';
    private $help_glyph_class = 'glyphicon glyphicon-question-sign';

    private $EE;

    private $no_permission_message = "<p>You don't have permission to change Settings or Download grades.
        				Please speak to your supervisor if you believe you should have access.</p>";

    public static $labels = array(
    'standard_mean' => 'Standard Mean',
    'spark_plus' => 'SPARK Plus',
    'sap_original' => 'SAP original & SAPA',
    'sap_knee' => 'SAP knee & SAPA',
    'sap_linear' => 'SAP linear & SAPA',
);

    public static $score_calculation = array('standard_mean' => false,
                                    'spark_plus' => array(), );

    public static $spark_plus = array(
              'sap_original' => false,
              'sap_knee' => true,
              'sap_linear' => false,
            );

    public static $plugin_settings =
                      array(
                        'active' => 1,
                        'show_grade_column' => false,
                        'show_comments' => true,
                        'allow_self_assessment' => true,
                        'show_column_scores' => true,
                        'include_self_in_mean_score' => false,
                        'score_calculation' => array(),
                        'user_access' => '',
                        'total_score' => 10,
                        'feedback_only' => false, //@TODO add feedback_only
       );

    private static function build_options($options_array)
    {
        $arr = array('options' => array(), 'selected' => false);

        foreach ($options_array as $key => $selected) {
            $arr['options'][$key] = static::$labels[$key];
            if ($selected === true) {
                $arr['selected'] = $key;
            }
        }
        if ($arr['selected'] === false) {
            foreach ($options_array as $key => $selected) {
                $arr['options'][$key] = static::$labels[$key];
                if (is_array($selected)) { // spark plus
                $arr['selected'] = $key;
                }
            }
        }

        return $arr;
    }

    private static function build_calc_options($arr)
    {
        if (!isset($arr['score_calculation'])) {
            if (!is_array($arr)) {
                $arr = array();
            }
            $arr['score_calculation'] = static::$score_calculation;
        }

        return static::build_options($arr['score_calculation']);
    }

    private static function build_spark_options($arr)
    {
        if (!isset($arr['spark_plus'])) {
            $arr['spark_plus'] = static::$spark_plus;
        }

        return static::build_options($arr['spark_plus']);
    }

    public function __construct()
    {
        $this->EE = get_instance();
        static::$plugin_settings['score_calculation'] = static::$score_calculation;
        static::$plugin_settings['score_calculation']['spark_plus'] = static::$spark_plus;

        if(!isset($_REQUEST["ACT"])) {
              $this->lti_object = Learning_tools_integration::get_instance();

              $path = parse_url(ee()->config->site_url(), PHP_URL_PATH);

              if (ee()->TMPL->fetch_param('table_class')) {
                  $this->table_class = ee()->TMPL->fetch_param('table_class');
              }

              if (ee()->TMPL->fetch_param('button_class')) {
                  $this->button_class = ee()->TMPL->fetch_param('button_class');
              }

              if (ee()->TMPL->fetch_param('score_class')) {
                  $this->score_class = ee()->TMPL->fetch_param('score_class');
              }

              if (ee()->TMPL->fetch_param('img_class')) {
                  $this->img_class = ee()->TMPL->fetch_param('img_class');
              }

              if (ee()->TMPL->fetch_param('help_glyph_class')) {
                  $this->help_glyph_class = ee()->TMPL->fetch_param('help_glyph_class');
              }

        static::$plugin_settings = $this->_get_plugin_settings_array();
      }

    if (empty(static::$apeg_url)) {
        static::$apeg_url = ee()->config->site_url();
    }
    }

    private function _group_context_query($member_id) {
        $query = ee()->db->get_where('lti_group_contexts', array('member_id' => $member_id, 'internal_context_id' => $this->lti_object->internal_context_id));
        return $query;
    }

    public function user_is_in_group() {
        $res = $this->_group_context_query($member_id);

        return $res->num_rows() > 0;
    }

    private function get_user_credentials($member_id)
    {
        if (!empty($this->lti_object->isInstructor)) {
            return;
        }

        $res = $this->_group_context_query($member_id);

        if ($res->num_rows() == 0) {
            return;
        }

        $data = $res->row();

        return $data;
    }

    private function _where_instructor_settings()
    {
        return array('institution_id' => $this->lti_object->institution_id, 'course_key' => $this->lti_object->course_key);
    }

    private function _query_instructor_settings()
    {
        return ee()->db->get_where('lti_instructor_settings', $this->_where_instructor_settings());
    }

    private function _query_instructor_member_list()
    {
        ee()->db->where('is_instructor', '1');
        $uid = ee()->session->userdata('member_id');
        ee()->db->where('member_id != '.$uid);
        ee()->db->where('context_id', $this->lti_object->context_id);
        ee()->db->select('username, member_id');

        return ee()->db->get('lti_member_contexts');
    }

    private function set_instructor_settings($data)
    {
        $query = $this->_query_instructor_settings();

        $existing = array($this->module_name => array());

        if ($query->num_rows() > 0 && $query->row()->plugins_active) {
            $existing = unserialize($query->row()->plugins_active);
            $existing[$this->module_name] = $data;

            ee()->db->where($this->_where_instructor_settings());
            ee()->db->update('lti_instructor_settings', array('plugins_active' => serialize($existing)));
        } else {
            $existing[$this->module_name] = $data;

            ee()->db->set($this->_where_instructor_settings());
            ee()->db->insert('lti_instructor_settings', array('plugins_active' => serialize($existing)));
        }
    }

    private function _get_plugin_settings_array()
    {
        $query = $this->_query_instructor_settings();

        $plugin_settings = static::$plugin_settings;

        if ($query->num_rows() == 0) {
            $this->set_instructor_settings($plugin_settings);
        } else {
            $ser = $query->row()->plugins_active;

            if (!empty($ser)) {
                $unser = unserialize($ser);
                if (count($unser) > 0) {
                    $plugin_settings = $unser[$this->module_name];

                        // default settings only include one field
                        if (count(array_keys($unser[$this->module_name])) == 1) {
                            $this->set_instructor_settings(static::$plugin_settings);
                        }
                } else {
                    $this->set_instructor_settings($plugin_settings);
                }
            }
        }

        return $plugin_settings;
    }

    private function get_score_column_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        $score = '';
        if (!empty($plugin_settings['show_grade_column'])) {
            $score = "lti_peer_assessments.score,";
        }

        return $score;
    }

    private function get_comment_column_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        $comment = '';
        if (!empty($plugin_settings['show_comments'])) {
            $comment = "lti_peer_assessments.comment,";
        }

        return $comment;
    }

    private function get_comment_toggle()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return !empty($plugin_settings['show_comments']);
    }

    private function get_view_sapa_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return (boolean)$plugin_settings['show_sapa'];
    }

    private function get_self_assessment_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return !empty($plugin_settings['allow_self_assessment']);
    }

    private function get_self_mean_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return !empty($plugin_settings['include_self_in_mean_score']);
    }

    private function get_score_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return $plugin_settings['total_score'];
    }

    private function get_score_calculation_setting()
    {
        $plugin_settings = $this->_get_plugin_settings_array();

        return $plugin_settings['score_calculation'];
    }

    private function _query_get_instructor_settings()
    {
        ee()->db->where(array('course_key' => $this->lti_object->course_key));

        return ee()->db->get('lti_instructor_settings');
    }

    private function get_instructor_settings_row()
    {
        return $this->_query_get_instructor_settings()->row();
    }

    private function get_instructor_general_settings_array()
    {
        $row = $this->get_instructor_settings_row();

        if ($row) {
            $settings = unserialize($row->plugins_active);

            return $settings;
        }
    }

    private function get_course_rubric_id()
    {
        $course_row = ee()->db->get_where('lti_course_contexts', array('id' => $this->lti_object->course_id, 'institution_id' => $this->lti_object->institution_id))->row();

        if ($course_row) {
            $id = $course_row->id;
        }

        $row = ee()->db->get_where('lti_course_link_resources', array('course_id' => $id))
                ->row();
        if ($row) {
            $rubric_id = $row->rubric_id;
        }

        return $rubric_id;
    }

    private function get_rubric_data_array()
    {
        $cache_path = ee()->config->item('lti_cache');
        $rubric_id = $this->get_course_rubric_id();

        $course_upload_dir = $this->lti_object->context_id.$this->lti_object->institution_id.$this->lti_object->course_id;

        $path = $cache_path.$course_upload_dir.DIRECTORY_SEPARATOR.'rubrics'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.$rubric_id.'.data';

        $array = array();
        $array['cache_path'] = $cache_path.$course_upload_dir.DIRECTORY_SEPARATOR.'rubrics'.DIRECTORY_SEPARATOR.'excel'.DIRECTORY_SEPARATOR;
        $array['zip_path'] = $cache_path.$course_upload_dir.DIRECTORY_SEPARATOR.'rubrics'.DIRECTORY_SEPARATOR.'zip'.DIRECTORY_SEPARATOR;

        if (file_exists($path)) {
          $data_file = file_get_contents($path);
          $array = array_merge($array, unserialize($data_file));
        }

        if (!file_exists($array['cache_path'])) {
            mkdir($array['cache_path']);
            mkdir($array['zip_path']);
        }

        return $array;
    }

    private function _pa_table_header(&$table) {
      $table .= "<div class='col-xs-12 col-md-6 col-lg-6 peer_grades well col-md-offset-1 col-xs-offset-0 col-lg-offset-1'>";
      $table .= "<h2>How your group rates you.</h2>";
      $table .= "<table class='".$this->table_class."'><thead><tr><th>Criteria</th><th>Average Grade<br><span id='percToggle'>View as Percentage</span></th></tr></thead>";
      $table .= "<tbody>";
    }

    private function _pa_table_calculate(&$results, &$comments, &$sums) {
      if ($results->num_rows() > 0) {

          foreach($results->result_array() as $row) {
                $data = json_decode($row['rubric_json']);

                if($data) {
                  foreach($data->rows as $i => $rub_row) {
                          if(!isset($sums[$i])) {
                              $sums[$i] = (float) 0.0;
                          }
                          if($rub_row !== NULL) {
                            $sums[$i] += (float) $rub_row->score;
                          }
                  }
                }
                  $comments[] = $row['comment'];
          }

          shuffle($comments);
    }
  }

private function _feedback_query($score_toggle) {
        $member_id = ee()->session->userdata('member_id');

        $mrow = $this->get_user_credentials($member_id);

        if ($mrow == null) {
            return '<p>You are not a member of any groups for '.$this->lti_object->course_name.'</p>';
        }

        $assessor_group_id = $mrow->group_id;

        ee()->db->select("lti_group_contexts.id as group_context_id, members.member_id,
                            members.screen_name, lti_group_contexts.group_id, lti_group_contexts.group_name, $score_toggle
                            lti_peer_assessments.comment, lti_peer_assessments.rubric_json, lti_peer_assessments.locked, lti_peer_assessments.time");
        ee()->db->from('lti_group_contexts');
        ee()->db->join('lti_member_contexts', 'lti_group_contexts.internal_context_id = lti_member_contexts.id');
        ee()->db->join('members', 'members.member_id = lti_member_contexts.member_id');
        ee()->db->join("lti_peer_assessments", "lti_peer_assessments.group_context_id = lti_group_contexts.id", 'left outer');
        ee()->db->where(array('lti_group_contexts.group_id' => $assessor_group_id,
                            "lti_peer_assessments$str.member_id" => $member_id,
                              "lti_peer_assessments$str.resource_link_id" => $this->lti_object->resource_link_id,
                              "lti_peer_assessments$str.locked"=> '1'));

        $results = ee()->db->get();

        return $results;
  }

    public function feedback()
    {
        if (!empty($this->lti_object->isInstructor)) {
            return;
        }
        $is_preview = ee()->config->_global_vars['is_preview_user'];
        $variables = array();
        $variable_row = array();

        $variable_row['user_has_assessed'] = $this->user_has_assessed();

        $settings = $this->_get_plugin_settings_array();

        if (!empty($settings) && $settings['show_grade_column'] == 0 && $settings['show_comments'] == 0) {
            return;
        }

        $score_toggle = '';

        $score_toggle = $this->get_score_column_setting();
        $results = $this->_feedback_query($score_toggle);

        if(is_string($results)) {
            return;
        }

        $comment_toggle = $this->get_comment_toggle();

        $table = '';
        $output = '';

        // get rubric template
        $rubric_template = $this->get_rubric_data_array();

        $sums = array();
        $comments = array();

        $this->_pa_table_calculate($results, $comments, $sums);

        $variable_row['has_comments'] = count($comments) > 0;

        $sc = $results->num_rows();
        $variable_row['has_been_assessed'] = $sc > 0;

        if ($variable_row['has_been_assessed']) {

        $grade_header = '';

        $table = "";

        $criteria_rows = array();
        if (!empty($score_toggle) && $rubric_template !== FALSE)  {
              $base_val = $rubric_template['maxvalue'];

              $total = (float)0.0;
              foreach($rubric_template['row_headers'] as $i => $criteria) {
                      if(isset($sums[$i])) {
                            $mean = (float) $sums[$i] / $sc;
                            $mean = number_format($mean, 2, ".", "");
                      } else {
                            $mean = 'N/A';
                      }

                      if(isset($rubric_template['row_weights'][$i])) {
                          $max = $base_val * ($rubric_template['row_weights'][$i] / 100);
                      } else {
                          $max = $base_val / count($rubric_template['row_headers']);
                      }

                      $criteria_rows[] = array('criteria' => $criteria, 'mean' => $mean, 'max' => $max);

                      $total += (float) $mean;
              }

              $variable_row['criteria_rows'] = $criteria_rows;
              $variable_row['total'] = $total;
              $variable_row['mean'] = $mean;
              $variable_row['total_max'] = $rubric_template['maxvalue'];
        }

        $comments_arr = array();

        if($comment_toggle) {
          if(!$is_preview) {
              foreach($comments as $comment) {
                if(strlen(trim($comment)) > 0) {
                  $comments_arr[] = array("comment" => $comment);
                }
              }
            $variable_row['comments'] = $comments_arr;
          } else {
              $variable_row['comments'] = ee()->config->item("preview_comments");
          }
        }
          }

        $variables[] = $variable_row;

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
    }

    public function group_name()
    {
        if (!empty($this->lti_object->isInstructor)) {
            return;
        }
        $is_preview = ee()->config->_global_vars['is_preview_user'];

        if($is_preview) {
            return lang("group_preview");
        }

        $member_id = ee()->session->userdata('member_id');

        $mrow = $this->get_user_credentials($member_id);

        if ($mrow == null) {
            return lang('user_has_no_group');
        }

        return $this->removeSpaceFillers($mrow->group_name);
    }

/*
 * Blackboard inserts space fillers, remove these for display.
 *
 */
private function removeSpaceFillers($group_name)
{
    $filtered = $group_name;

    foreach ($this->group_name_space_fillers as $filler) {
        $filtered = str_replace($filler, ' ', $group_name);
    }

    return $filtered;
}

    private function clearFlags(&$settings_array)
    {
        foreach ($settings_array as $key => $val) {
            if (!is_array($settings_array[$key])) {
                $settings_array[$key] = false;
            } else {
                $this->clearFlags($settings_array[$key]);
            }
        }
    }

  //  static $settings_called = FALSE;
    public function settings()
    {

        $is_super = ee()->session->userdata('group_id') == '1';

        $show_grade_column = isset($_POST['show_grade_column']) && $_POST['show_grade_column'] == 'yes' ? 1 : 0;
        $show_comments = isset($_POST['show_comments']) && $_POST['show_comments'] == 'yes' ? 1 : 0;
        $instructors = isset($_POST['instructors']) ? $_POST['instructors'] : array();
        $allow_self_assessment = isset($_POST['allow_self_assessment']) && $_POST['allow_self_assessment'] == 'yes' ? 1 : 0;
        $include_self_in_mean_score = isset($_POST['include_self_in_mean_score']) && $_POST['include_self_in_mean_score'] == 'yes' ? 1 : 0;
        $total_score = isset($_POST['total_score']) && is_numeric($_POST['total_score']) ? $_POST['total_score'] : $this->total_score_default;
        $score_calculation = isset($_POST['score_calculation']) ? $_POST['score_calculation'] : array_search(true, static::$score_calculation);
        $spark_plus = isset($_POST['spark_plus']) ? $_POST['spark_plus'] : array_search(true, static::$spark_plus);
        $show_sapa = isset($_POST['show_sapa']) && $_POST['show_sapa'] == 'yes' ? 1 : 0;

        $saved = '';

        $theme_url = URL_THIRD_THEMES.$this->module_name;
        $addon_path = PATH_THIRD.$this->module_name;

        $plugin_settings = $this->_get_plugin_settings_array();

        if (isset($_POST['show_grade_column'])) {
            $plugin_settings['show_grade_column'] = $show_grade_column;
            $plugin_settings['show_comments'] = $show_comments;
            $plugin_settings['allow_self_assessment'] = $allow_self_assessment;
            $plugin_settings['include_self_in_mean_score'] = $include_self_in_mean_score;
            $plugin_settings['total_score'] = $total_score;
            $plugin_settings['show_sapa'] = $show_sapa;

            if (is_array(static::$score_calculation[$score_calculation])) {
                if ($score_calculation === 'spark_plus') {
                    $this->clearFlags($plugin_settings['score_calculation']);
                    $plugin_settings['score_calculation']['spark_plus'] = &static::$spark_plus;

                    $this->clearFlags($plugin_settings['score_calculation']['spark_plus']);
                    $plugin_settings['score_calculation']['spark_plus'][$spark_plus] = true;
                }
            } else {
                $this->clearFlags($plugin_settings['score_calculation']);
                $plugin_settings['score_calculation'][$score_calculation] = true;
            }

            if ($is_super) {
                $plugin_settings['user_access'] = implode(',', $instructors);
            }
            $gcolumn = $plugin_settings;
            static::$plugin_settings = $plugin_settings;

            $this->set_instructor_settings($plugin_settings);

            $saved = ' Settings Saved';
        } else {
            $gcolumn = @array('show_grade_column' => $plugin_settings['show_grade_column'],
                     'show_comments' => $plugin_settings['show_comments'],
                  'allow_self_assessment' => $plugin_settings['allow_self_assessment'],
                  'include_self_in_mean_score' => $plugin_settings['include_self_in_mean_score'],
                  'total_score' => $plugin_settings['total_score'],
                  'show_sapa' => $plugin_settings['show_sapa'],
             );

             if(!empty($plugin_settings['user_access'])) {
                $instructors = explode(',', $plugin_settings['user_access']);
             }
        }

        $form = $this->no_permission_message;

        $user_access = explode(',', $plugin_settings['user_access']);
        $uid = ee()->session->userdata('member_id');
        $variables = array();

        if ($this->lti_object->isInstructor) {
            ee()->load->helper('form');

            $variable_row = array();
            $variable_row['settings_javascript'] = " ";
            $variable_row['form_open'] = form_open_multipart(static::$apeg_url.'/'.ee()->uri->uri_string(), array("class" => $this->lti_object->form_class));

            if ($is_super) {

                $results = $this->_query_instructor_member_list();

                $options = array();
                foreach ($results->result_array() as $row) {
                    $options[$row['member_id']] = $row['username'];
                }

                $variable_row['instructors_dropdown'] = form_dropdown('instructors[]', $options, $instructors, "multiple='multiple' style='min-width: 16em; padding: 0.3em' class='selectpicker'");

                //$variable_row['settings_sejavascript'] .= file_get_contents($addon_path.'/js/'.$this->module_name.'_settings.js');
            }

            $calc_options = static::build_calc_options($plugin_settings);

            $spark_options = static::build_spark_options($plugin_settings);

            $labels = ee()->TMPL->fetch_param('radio_labels');
            $labels = explode(',',$labels);

            $variable_row['score_calculation_dropdown'] =
                        form_dropdown('score_calculation', $calc_options['options'], $calc_options['selected'],  "style='min-width: 12em; padding: 0.3em'").
                          form_dropdown('spark_plus', $spark_options['options'], $spark_options['selected'],  "style='min-width: 12em; padding: 0.3em'");

            $variable_row['settings_javascript'] .= file_get_contents($addon_path.'/js/'.$this->module_name.'_settings.js');

            $radios = array();

            $radios[] = array('radio' => form_radio('show_grade_column', 'yes', $gcolumn['show_grade_column'] == '1'), 'label' => $labels[0]);
            $radios[] = array('radio' => form_radio('show_grade_column', 'no', $gcolumn['show_grade_column'] != '1'), 'label' => $labels[1]);

            $variable_row['show_grade_column'] = $radios;

            $radios = array();
            $radios[] = array('radio' => form_radio('show_comments', 'yes', $gcolumn['show_comments'] == '1'), 'label' => $labels[0]);
            $radios[] = array('radio' => form_radio('show_comments', 'no', $gcolumn['show_comments'] != '1'), 'label' => $labels[1]);

            $variable_row['show_comments'] = $radios;

            $radios = array();
            $radios[] = array('radio' => form_radio('show_sapa', 'yes', $gcolumn['show_sapa'] == '1'), 'label' => $labels[0]);
            $radios[] = array('radio' => form_radio('show_sapa', 'no', $gcolumn['show_sapa'] != '1'), 'label' => $labels[1]);

            $variable_row['show_sapa'] = $radios;

            $radios = array();
            $radios[] = array('radio' => form_radio('allow_self_assessment', 'yes', $gcolumn['allow_self_assessment'] == '1'), 'label' => $labels[0]);
            $radios[] = array('radio' => form_radio('allow_self_assessment', 'no', $gcolumn['allow_self_assessment'] != '1'), 'label' => $labels[1]);

            $variable_row['allow_self_assessment'] = $radios;

            $radios = array();
            $radios[] = array('radio' => form_radio('include_self_in_mean_score', 'yes', $gcolumn['include_self_in_mean_score'] == '1'), 'label' => $labels[0]);
            $radios[] = array('radio' => form_radio('include_self_in_mean_score', 'no', $gcolumn['include_self_in_mean_score'] != '1'), 'label' => $labels[1]);

            $variable_row['include_self_in_mean_score'] = $radios;

            $data = array(
              'name' => 'total_score',
              'id' => 'total_score',
              'value' => $gcolumn['total_score'],
              'maxlength' => '4',
              'size' => '30',
              'style' => 'width:12%',
            );

            $variable_row['total_score'] = form_input($data);

            $variable_row['submit_button'] = form_submit('settings', 'Save Settings', $this->lti_object->form_submit_class);

            if (strlen($saved) > 0) {
                $variable_row['submit_button'] .= "<span style='color: #00748b'>$saved</span>";
            }
            $variable_row['form_close'] = form_close();
        }

      $variable_row['chosen_css'] = ee()->config->item("chosen_css");
      $variables[] = $variable_row;

       return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
    }

    private function get_rubric()
    {
        $resource_link_id = $this->lti_object->resource_link_id;

        //ee()->logger->developer("Rubric link id: ".$resource_link_id);

        $where = array('resource_link_id' => $resource_link_id);
        $res = ee()->db->get_where('lti_course_link_resources', $where);

        $rubric = array();

        if ($res->num_rows() == 1) {
            $rubric['id'] = $res->row()->rubric_id;

            return $rubric;
        }

        return false;
    }

    public function max_grade()
    {
        return $this->get_score_setting();
    }

    /* Note:  if assessment has a previous then member has assessed. (test this...?)  */

  private function _group_member_list_query($assessor_group_id, $member_id, $locked = FALSE, $preview = FALSE) {

    ee()->db->distinct();
    ee()->db->select("lti_group_contexts.id as group_context_id,  members.member_id,
                        members.screen_name, lti_group_contexts.group_id, lti_group_contexts.group_name, lti_peer_assessments.score,
                        lti_peer_assessments.comment, lti_peer_assessments.rubric_json, lti_peer_assessments.locked, lti_peer_assessments.current");
    ee()->db->from("lti_group_contexts", "lti_peer_assessments");
    ee()->db->join("lti_member_contexts", "lti_group_contexts.internal_context_id = lti_member_contexts.id");
    ee()->db->join("members", "members.member_id = lti_member_contexts.member_id");
    ee()->db->join("lti_peer_assessments", "lti_peer_assessments.group_context_id = lti_group_contexts.id", "left outer");

    $where = array(
                  "lti_group_contexts.group_id" => $assessor_group_id,
                  "lti_peer_assessments.current" => TRUE
                );

    if($preview === FALSE) {
        $where["lti_peer_assessments.assessor_member_id"] = $member_id;
    }

    // exclusive query here...
    if($locked === TRUE) {
        $where["lti_peer_assessments$str.locked"] = "1";
        $where["lti_peer_assessments$str.resource_link_id"] = $this->lti_object->resource_link_id;
    }

    ee()->db->where($where);
    $res = ee()->db->get();

  return $res;
}

private function user_has_assessed() {
      $member_id = ee()->session->userdata('member_id');

      $mrow = $this->get_user_credentials($member_id);

      if ($mrow == null) {
          return FALSE;
      }

      $assessor_group_id = $mrow->group_id;

      $is_preview = ee()->config->_global_vars['is_preview_user'];

      $group_members = $this->_group_member_list_query($assessor_group_id, $member_id, TRUE, $is_preview);

      return $group_members->num_rows() > 0;
}


/*
 *  Rolling submission form, means students can assess as many times as they like.
 *
 */
public function form()
{
    if (!empty($this->lti_object->isInstructor)) {
        return;
    }

    if (isset($_POST['no_reload'])) {
        return;
    }

    $form_table_name = 'lti_peer_assessments';

    $row_start_tag = "td";

    $allow_self_assessment = $this->get_self_assessment_setting();

    $tmp_pool = ee()->TMPL->fetch_param('use_pool');
    $grade_pool = !empty($tmp_pool);

    $member_id = ee()->session->userdata('member_id');
    $rubric = $this->get_rubric();

    $is_preview = ee()->config->_global_vars['is_preview_user'];

    if(! $is_preview) {
          $mrow = $this->get_user_credentials($member_id);

          if ($mrow == null) {
              return;
          }

          $assessor_group_id = $mrow->group_id;
          $assessor_group_context_id = $mrow->id;
    } else {
          ee()->db->select(array("id","group_id"));
          ee()->db->from("lti_group_contexts");
          ee()->db->where(array("context_id" => 'universal'));
          $r = ee()->db->get();

          $assessor_group_id = $r->row()->group_id;
          $assessor_group_context_id = $r->row()->id;
    }

    $save_message = '';

    ee()->load->helper('form');

    $form = '';//."<h1>".$this->lti_object->resource_link_id."</h1>";

    $action = isset($_POST['assess_action']) ? $_POST['assess_action'] : 'retrieve';

    if (!isset($_POST['new_action']) && $action === 'assign-marks') {
        $post_data = array();

        foreach ($_POST as $key => $value) {
            $fullkey = explode('_', $key);
            $data_type = $fullkey[0];

            if ($data_type === 'score') {
                $student_id = $fullkey[1];
                $num = is_numeric($value) ? $value : 0;
                $post_data[$student_id]['score'] = $num;
            }

            if ($data_type === 'comment') {
                $student_id = $fullkey[1];
                $post_data[$student_id]['comment'] = $value;
            }

            if ($data_type === 'rubric') {
                $student_id = $fullkey[1];
                $post_data[$student_id]['rubric_json'] = $value;
            }
        }

        $total_affected = 0;

        foreach ($post_data as $student_id => $row) {

            ee()->db->where(array('TMP_POST_ID' => $student_id, 'assessor_member_id' => $member_id));
            $rubric_json = !empty($row['rubric_json']) ? $row['rubric_json'] : '';
            $locked = $is_preview ? FALSE : ee()->input->post('locked');
            ee()->db->update($form_table_name, array('score' => $row['score'], 'comment' => $row['comment'], 'rubric_json' => $rubric_json, 'resource_link_id' => $this->lti_object->resource_link_id, 'locked' => $locked, 'current' => ! $locked, 'TMP_POST_ID' => null));

            $total_affected +=  ee()->db->affected_rows();
        }

        if ($_POST['locked'] == 1) {
            if ($total_affected > 0) {
                $variable_row['message'] = 'Your assessment has been submitted successfully.';
            } else {
                $variable_row['error_message'] = "There was an error submitting your assessment.";
            }
        }
        if ($_POST['locked'] == 0) {
            if ($total_affected > 0) {
                $variable_row['save_message'] = "Saved";
            } else {
                $variable_row['save_error_message']  = "There was a network issue saving, try refreshing and check your connection.";
            }
        }
    }
    if ((isset($_POST['new_action']) || $action === 'retrieve') || ($action === 'assign-marks' && $_POST['locked'] == 0)) {

        $results = $this->_group_member_list_query($assessor_group_id, $member_id, FALSE, $is_preview);
        // remove duplicates
        $dupes = array();
        $array = $results->result_array();

        foreach($array as $key => $row) {
          if($row['resource_link_id'] != $this->lti_object->resource_link_id) {
              unset($array[$key]);
          }
        }

        $lock_count = 0;

        if (count($array) > 0) {
            $attributes = array('id' => 'assessments');
            $variable_row['form_open'] = form_open_multipart(static::$apeg_url.'/'.ee()->uri->uri_string(), $attributes);
            $variable_row['form_open'] .= form_hidden('assess_action', 'assign-marks')
            . form_hidden('locked', '0');

          $table_rows = array();
          $r_array = $array; //$results->result_array();

          if($is_preview) {
              $preview_user = ee()->config->item('preview_user');
              $preview_user["group_context_id"] = PREVIEW_CONTEXT;
              $preview_user['member_id'] = $this->lti_object->member_id;
              $r_array[] = $preview_user;
          }

          foreach ($r_array as $asmrow) {
                $render_row = ($allow_self_assessment == 1 || $asmrow['member_id'] != $member_id);

                if ($render_row) {
                    $str_random = '';
                    $count = 0;
                    $success = -1;
                    do {
                      $str_random = Learning_tools_integration::str_random();
                      $data = array('TMP_POST_ID' => $str_random);
                      $r = ee()->db->get_where($form_table_name, $data);

                    } while ($r->num_rows() > 0);
                        /* check for current assessment */
                        $where = array('assessor_member_id' => $member_id,
                                            'group_id' => $assessor_group_id,
                                            'group_context_id' => $asmrow['group_context_id'],
                                            'member_id' => $asmrow['member_id'],
                                          'current' => 1);

                        ee()->db->where($where);

                        $peer_res = ee()->db->get_where($form_table_name, $where);

                        if($asmrow['group_context_id'] !== PREVIEW_CONTEXT) {
                                if ($peer_res->num_rows() == 0) {
                                    /* if instructor has unlocked previous assessment : */
                                    $where = array('assessor_member_id' => $member_id,
                                                      'group_id' => $assessor_group_id,
                                                      'group_context_id' => $asmrow['group_context_id'],
                                                      'member_id' => $asmrow['member_id'],
                                                      'current' => 0, 'locked' => 0);

                                    ee()->db->where($where);

                                    $peer_res = ee()->db->get_where($form_table_name, $where);

                                        if ($peer_res->num_rows() == 0) {
                                            // create a new assessment
                                              $data['resource_link_id'] = $this->lti_object->resource_link_id;
                                              $insert_data = array_merge($where, $data);
                                              ee()->db->insert($form_table_name, $insert_data);
                                              $reload = TRUE;
                                        } else {
                                           // update existing row
                                           $data['current'] = 1;
                                            ee()->db->where($where);
                                            ee()->db->update($form_table_name, $data);

                                            $reload = TRUE;
                                        }
                                } else {
                                  // update existing row
                                     ee()->db->where($where);
                                     ee()->db->update($form_table_name, $data);
                                }
                      }

                    if($asmrow['current'] == TRUE) {
                      $row = array();

                        $val = $asmrow['score'];
                        $comment = $asmrow['comment'];

                        if ($rubric !== false) {
                            $rubric_json = htmlentities($asmrow['rubric_json'], ENT_QUOTES, 'UTF-8');
                            $html_safe_screen_name = htmlentities(json_encode(array('screen_name' => $asmrow['screen_name'])));

                            $row["score_input"] = "<strong class='$this->score_class' id='show_score_$str_random' style='font-size: 14pt; color: darkblue;'>$val</strong><input type='hidden' name='score_$str_random' class='student_assess' id='score_$str_random' value='$val'/>
                        						<button class='$this->button_class' id=\"openRubric\" data-val=\"$rubric[id]\" data-screen_name=\"$html_safe_screen_name\">Grade</button>
                        						<input type='hidden' name='rubric_$str_random' id='rubric_$str_random' value='$rubric_json'/>";
                        } else {
                            $row["score_input"] = "<input type='text' name='score_$str_random' class='student_assess' id='score_$str_random' value='$val'/>";
                        }

                        $row["group_member_screen_name"] = $asmrow['screen_name'];
                        $row["comment_textarea"] = "<textarea id='comment_$str_random' class='comment' name='comment_$str_random'>$comment</textarea>";

                        if($allow_self_assessment && $asmrow['member_id'] == $member_id) {
                            $self = " id='self_assess_row' class='keepfocus'";
                        } else {
                            $self = "";
                        }

                        $row["self_assess_id"] = $self;

                        $table_rows[] = $row;
                  }
                }
            }

            if(!$is_preview && isset($reload)) {
              ee()->db->where(array('assessor_member_id' => $member_id, 'group_id' => $assessor_group_id));
              $res = ee()->db->get($form_table_name);

              if($res->num_rows() > 0) {
                foreach($res->result_array() as $row) {
                  $group_context_id = $row['group_context_id'];
                  $previous_id = $row['previous_id'];

                    if(empty($previous_id) && isset($group_context_id )) {
                        ee()->db->where(array('assessor_member_id' => $member_id, 'group_context_id' => $group_context_id, 'locked' => 1));
                        ee()->db->order_by('time', 'desc');

                        $res = ee()->db->get($form_table_name);
                        if($res->num_rows() > 0) {
                              $previous_id = $res->row()->id;

                              ee()->db->where(array('assessor_member_id' => $member_id, 'group_context_id' => $group_context_id, 'current' => 1, 'locked' => 0));
                              ee()->db->update($form_table_name, array('previous_id' => $previous_id));
                        }
                    }

                  unset($group_context_id);
                }
              }

              echo "Loading please wait...";
              echo "<script>(function() { setTimeout(function() { document.location.reload(); }, 1500); })();</script>";
              exit();
            }

            $variable_row['user_has_assessed'] = $this->user_has_assessed($rolling);
            $variable_row['self_assessment_allowed'] = $allow_self_assessment;
            $variable_row['table_row_count'] = count($table_rows);
            $variable_row['table_rows'] = $table_rows;

            $primary_class=$this->lti_object->submit_primary_class;
            $warning_class=$this->lti_object->submit_warning_class;

            $variable_row['form_close'] = "<button $primary_class id='Save' title='Save marks and return to edit later.'>Save</button> $save_message";
            $variable_row['form_close']  .= "<button $warning_class id='assess'>Submit Assessment</button>";
            $variable_row['form_close']  .= form_close();

            $set_score = $this->get_score_setting();
            $variable_row['form_close'] .= Learning_tools_integration::outputJavascript(array('score' => $set_score));
            if ($grade_pool === true) {
                $variable_row['form_close'] .= Learning_tools_integration::outputJavascript(null,'pool');
            }
            if($allow_self_assessment) {
                $variable_row['form_close'] .= Learning_tools_integration::outputJavascript(null,'self_assess');
            }

        } else {
            $variable_row['message'] = 'You are not registered for peer assessment, please check with your Lecturer.';
        }
    }

    $vars = array();
    $vars['form'] = $form;
    $vars['base_url'] = $this->lti_object->base_url;

    $variable_row['has_rubric'] = $rubric !== FALSE;
    $variable_row['rubric_javascript'] = $rubric ? ee()->load->view('rubric-interface', $vars, true) : NULL;

    $variables[] = $variable_row;

    return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
}

private function form_javascript(&$form, $set_score = 0, $allow_self_assessment, $grade_pool) {
  $js_vars = array('score' => $set_score);
  $form .= Learning_tools_integration::outputJavascript($js_vars);

  if ($grade_pool === true) {
      $form .= Learning_tools_integration::outputJavascript($js_vars, 'pool');
  }

  if ($allow_self_assessment) {
      $form .= Learning_tools_integration::outputJavascript($js_vars, 'self_assess');
  }
}
    private function _set_studentDoc_header(&$student_doc, $student_name)
    {
        $student_doc->getProperties()->setCreator('Paul Sijpkes')
                             ->setLastModifiedBy('ExpressionEngine LTI Peer Assesment Plugin')
                             ->setTitle($student_name)
                             ->setSubject('Student Statistics')
                             ->setDescription('Peer assessment results, generated using ExpressionEngine LTI Plugin using PHPExcel.')
                             ->setKeywords('peer assessment office 2007 openxml php')
                             ->setCategory('Peer Assessment File');

        $student_doc->setActiveSheetIndex(0)
                           ->setCellValue('B1', "$student_name Stats - Peer Assessment")
                           ->setCellValue('A4', 'Username')
                           ->setCellValue('A5', 'Group Name')
                           ->setCellValue('A6', 'Mean Score')
                           ->setCellValue('A7', 'Total Score')
                           ->setCellValue('A8', 'No. Group Members')
                           ->setCellValue('A9', 'No. Assessed')
                           ->setCellValue('A10', '')
                           ->setCellValue('A11', 'SPARK SPA Factor')
                           ->setCellValue('A12', 'SPARK SAPA Factor')
                          ->setCellValue('C4', 'Student Rubrics');

        $sheet = $student_doc->getActiveSheet(0);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        $sheet->getStyle('B1:E1')->applyFromArray(
         array(
         'font' => array(
             'bold' => true,
             'color' => array('rgb' => '000000'),
             'size' => 14,
             'name' => 'Arial',
         ), )
       );

        $sheet->getStyle('A4:C12')->applyFromArray(
        array(
        'font' => array(
            'bold' => true,
            'color' => array('rgb' => '000000'),
            'size' => 11,
            'name' => 'Arial',
        ), )
      );

        return $sheet;
    }

    private function _set_mainDocument_header(&$mainDocument, $max_assessors = 0)
    {
        $settings = $this->_get_plugin_settings_array();

    // Set document properties
$mainDocument->getProperties()->setCreator('Paul Sijpkes')
                             ->setLastModifiedBy('ExpressionEngine LTI Peer Assesment Plugin')
                             ->setTitle('Office 2007 XLSX Test Document')
                             ->setSubject('Office 2007 XLSX Test Document')
                             ->setDescription('Peer assessment results, generated using ExpressionEngine LTI Plugin using PHPExcel.')
                             ->setKeywords('peer assessment office 2007 openxml php')
                             ->setCategory('Peer Assessment File');

        if ($settings['score_calculation']['standard_mean']) {
            // Add some data
  $mainDocument->setActiveSheetIndex(0)
            ->setCellValue('A1', $this->lti_object->course_name.' Peer Assessment Report')
            ->setCellValue('A2', 'First Name')
            ->setCellValue('B2', 'Last Name')
            ->setCellValue('C2', 'Username')
            ->setCellValue('D2', 'Group Name')
            ->setCellValue('E2', 'Mean Score')
            ->setCellValue('F2', 'Multiplier')
            ->setCellValue('G2', 'No. Group Members')
            ->setCellValue('H2', 'No. Assessed')
            /*->setCellValue('I2', 'Peer Assessment Details')*/;
        } else {
            $mainDocument->setActiveSheetIndex(0)
            ->setCellValue('A1', $this->lti_object->course_name.' Peer Assessment Report')
            ->setCellValue('A2', 'First Name')
            ->setCellValue('B2', 'Last Name')
            ->setCellValue('C2', 'Username')
            ->setCellValue('D2', 'Group Name')
            ->setCellValue('E2', 'SAPA Factor')
            ->setCellValue('F2', 'SPA Factor')
            ->setCellValue('G2', 'No. Group Members')
            ->setCellValue('H2', 'No. Assessed')
          /*  ->setCellValue('I2', 'Peer Assessment Details')*/;
        }
        $sheet = $mainDocument->getActiveSheet();

      /*  $assessorHeaders = array();

        for ($i = 0; $i < $max_assessors; ++$i) {
            $assessorHeaders[] = 'Assessor '.($i + 1);
            $assessorHeaders[] = '';
            $assessorHeaders[] = '';
            $assessorHeaders[] = '';
        }

        $mainDocument->getActiveSheet()
    ->fromArray(
        $assessorHeaders,   // The data to set
        null,        // Array values with this value will not be set
        'I3'         // Top left coordinate of the worksheet range where                          //    we want to set these values (default is A1)
    );

        for ($col = 8; $col < ($max_assessors * 4) + 7; $col += 4) {
            $col_str1 = PHPExcel_Cell::stringFromColumnIndex($col);
            $col_str2 = PHPExcel_Cell::stringFromColumnIndex($col + 3);

            $mainDocument->setActiveSheetIndex(0)->mergeCells($col_str1.'3:'.$col_str2.'3');

            $col_str3 = PHPExcel_Cell::stringFromColumnIndex($col + 1);
            $col_str4 = PHPExcel_Cell::stringFromColumnIndex($col + 2);
            $col_str5 = PHPExcel_Cell::stringFromColumnIndex($col + 3);

            $mainDocument->setActiveSheetIndex(0)
            ->setCellValue($col_str1.'4', 'Full Name')
            ->setCellValue($col_str3.'4', 'Username')
            ->setCellValue($col_str4.'4', 'Score')
            ->setCellValue($col_str5.'4', 'Comment');
        }

        $sheet->mergeCells('H1:'.$highestColumnStr.'1');
        */

        $highestColumn = PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());

        for ($col = 1; $col < $highestColumn; ++$col) {
            $col_str = PHPExcel_Cell::stringFromColumnIndex($col);

            $sheet->getColumnDimension($col_str)->setAutoSize(true);
        }

        $sheet->getColumnDimension('A')->setWidth(15);
        $highestColumnStr = $sheet->getHighestColumn();

        $sheet->getStyle('A1:'.$highestColumnStr.'4')->applyFromArray(
                      array(
                          'fill' => array(
                              'type' => PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => '8DB4E2'),
                          ),
                      )
                );

        $sheet->getStyle('A1:'.$highestColumnStr.'4')->applyFromArray(
    array(
    'font' => array(
        'bold' => true,
        'color' => array('rgb' => '000000'),
        'size' => 11,
        'name' => 'Arial',
    ), )
);

        $sheet->getStyle('A1')->applyFromArray(
    array(
    'font' => array(
        'size' => 18,
    ), )
);

        $sheet->getStyle('A2:'.$highestColumnStr.'2')->applyFromArray(
    array(
    'font' => array(
        'size' => 14,
    ), )
);

        $sheet->getStyle('A3:'.$highestColumnStr.'3')->applyFromArray(
    array(
    'font' => array(
        'size' => 12,
    ), )
);

        $sheet->getStyle('A4:'.$highestColumnStr.'4')->applyFromArray(
    array(
    'font' => array(
        'size' => 11,
    ), )
);

        $sheet->getStyle('A5:'.$highestColumnStr.'5')->applyFromArray(
    array(
    'font' => array(
        'size' => 10,
    ), )
);

        $short_title = substr('Peer Assessments', 0, 31);

        // Rename worksheet
        $mainDocument->getActiveSheet()->setTitle($short_title);
        $mainDocument->getActiveSheet()->freezePane('A5');

        return array('short_title' => $short_title, 'highestColumnStr' => $highestColumnStr);
    }

    private function rubric_layout(&$sheet) {
        $cellStr = "A";
        $index = 3;
        $hc = $sheet->getActiveSheet()->getHighestColumn();

        $sheet->getActiveSheet()->getColumnDimension($cellStr)->setAutoSize(true);


        $colLength = count($this->rubric_template_array['col_headers']);
        // criteria
        foreach($this->rubric_template_array['row_headers'] as $header) {
              $sheet->getActiveSheet()->setCellValue($cellStr.$index++, $header);
        }

        $cellStr = "B";
        // rating titles
        $index = 2;
        foreach($this->rubric_template_array['col_headers'] as $header) {
              $sheet->getActiveSheet()->setCellValue($cellStr++.$index, $header);
        }

        if(count($this->rubric_template_array['row_weights']) > 0) {
        $sheet->getActiveSheet()->setCellValue($cellStr.$index, "Weighting");

        // row weightings
        $index = 3;

        foreach($this->rubric_template_array['row_weights'] as $weight) {
              $sheet->getActiveSheet()->setCellValue($cellStr.$index++, $wv);
        }
        }

        $index = 3;
        $cellStr = "B";

        foreach($this->rubric_template_array['col_scores'] as $key => $score) {
            $k = $key+1;
            if(is_array($score)) {
                $sheet->getActiveSheet()->setCellValue($cellStr.$index, $score["range"]["min"]." - ".$score["range"]["max"]);
            } else {
                $sheet->getActiveSheet()->setCellValue($cellStr++.$index, $score);
            }
            if(($k % $colLength) == 0) {
                $index = $index + 1;
                $cellStr = "B";
            }
        }
    }

    private static function addNChars($char, $n) {
        $f = ord($char) + $n;

        return chr($f);
    }

    private function set_rubric_criteria(&$sheet, $rubric_data, $lastCol) {

          if(!$rubric_data) return;

          $criteria_select_style = array(
              'fill' => array(
                  'type' => PHPExcel_Style_Fill::FILL_SOLID,
                  'color' => array('rgb' => 'CC2244'),
              ),
          );
          $cellStr = "A";
          $index = 3;

          $mySheetIndex = $sheet->getActiveSheetIndex();

          $hc = $sheet->getActiveSheet()->getHighestColumn();
          $hr = $sheet->getActiveSheet()->getHighestRow(); // -1 accounts for label
          $hr_offset = $sheet->getActiveSheet()->getHighestRow();

          $str = "=";
          $hicol = null;
          $col = null;

          foreach($rubric_data['rows'] as $key => $row) {
              $k = $key+3;
              $c = static::addNChars($cellStr, (int)$row['col']);
              $val = $sheet->setActiveSheetIndex($mySheetIndex)->getCell($c.$k)->getValue();

              if(strrpos($val, "-") !== FALSE) {
                  $score = 0;

                  if(isset($row['score'])) {
                     $score = $row['score'];
                  }

                  $sheet->setActiveSheetIndex($mySheetIndex)->setCellValue($c.$k, $score);
              }
              $sheet->setActiveSheetIndex($mySheetIndex)->getStyle($c.$k)->applyFromArray(
                  $criteria_select_style
                );

              if(!empty($this->rubric_template_array['row_weights'][$key])) {
                    $str .= "+$c$k * ($hc$k / 100)";
              } else {
                    $str .= "+$c$k";
              }

              $title = $sheet->getActiveSheet()->getTitle();

              // Average criteria results sheet
              $criteria = (string)$sheet->setActiveSheetIndex(1)->getCell("A$k")->getCalculatedValue();

              if(strlen($criteria) == 0) {
                    $sheet->getActiveSheet()->setCellValue("A$k", "='$title'!A$k")->setCellValue("B$k", (float)0.0);
              }

              if($k == 3) {
                  $col = $sheet->getActiveSheet()->getHighestColumn();
              }

              $sheet->getActiveSheet()->setCellValue($col.$k, "='$title'!$c$k");

              // set header
              $sheet->getActiveSheet()->setCellValue($col.'2', $title);

              if($lastCol === TRUE) {
                        if($hicol == null) {
                            $hicol = $col;
                            ++$hicol;
                        }
                      $sheet->getActiveSheet()->setCellValue($hicol.$k,
                      "=AVERAGE(B$k:$col$k)");

                      $sheet->getActiveSheet()->setCellValue($hicol.'2', 'Mean Score');
              }
          }

          $sheet->setActiveSheetIndex($mySheetIndex)->setCellValue("A".($hr+1), 'Total Score');
          $str = $sheet->getActiveSheet()->setCellValue("B".($hr+1), $str);
          // format student sheet
          $sheet->getActiveSheet()->getStyle('A1:'.$hc.'2')->applyFromArray(
                        array(
                            'fill' => array(
                                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('rgb' => 'caf3f9'),
                            ),
                            'font' => array(
                                'bold' => true,
                                'color' => array('rgb' => '000000'),
                                'size' => 14,
                                'name' => 'Arial',
                            )
                        )
                  );

          $sheet->getActiveSheet()->getStyle('A3:A'.($hr-3))->applyFromArray(
                                array(
                                    'fill' => array(
                                        'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                        'color' => array('rgb' => 'caf3f9'),
                                    )
                                  )
                                );

          $sheet->getActiveSheet()->getStyle('A3:'.$hc.($hr+2))->applyFromArray(
                      array(
                          'font' => array(
                              'bold' => true,
                              'color' => array('rgb' => '000000'),
                              'size' => 12,
                              'name' => 'Arial',
                          )
                        )
                  );

          foreach(range('A',$hc) as $columnID) {
                $sheet->getActiveSheet()->getColumnDimension($columnID)
                  ->setAutoSize(true);
          }

          //format criteria summary sheet
          $hr = $sheet->setActiveSheetIndex(1)->getHighestRow();
          $hc = $sheet->getActiveSheet()->getHighestColumn();
          $hc1 = $hc;
          --$hc1;

          $sheet->getActiveSheet()->setCellValue('A1', 'Criteria Summary');
          $sheet->getActiveSheet()->setCellValue('A2', 'Assessor:');
          $sheet->getActiveSheet()->getStyle('A2')->getAlignment()->
          setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);

          $sheet->getActiveSheet()->getStyle('A1')->applyFromArray(array(
              'font' => array(
                  'bold' => true,
                  'color' => array('rgb' => '000000'),
                  'size' => 14,
                  'name' => 'Arial',
              )
            )
          );

          $sheet->getActiveSheet()->getStyle('A1:'.$hc.'2')->applyFromArray(
                      array(
                          'fill' => array(
                              'type' => PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => 'caf3f9'),
                          )
                        )
                  );

          $sheet->getActiveSheet()->getStyle('A3:A'.$hr)->applyFromArray(
                      array(
                          'font' => array(
                              'bold' => true,
                              'color' => array('rgb' => '000000'),
                              'size' => 12,
                              'name' => 'Arial',
                          ),
                          'fill' => array(
                              'type' => PHPExcel_Style_Fill::FILL_SOLID,
                              'color' => array('rgb' => 'caf3f9'),
                          )
                        )
                  );

          $sheet->getActiveSheet()->getStyle('B3:'.$hc1.$hr)->applyFromArray(
                      array(
                          'font' => array(
                              'bold' => false,
                              'color' => array('rgb' => '000000'),
                              'size' => 10,
                              'name' => 'Arial',
                          )
                        )
                  );

        $sheet->getActiveSheet()->getStyle('A1:'.$hc.'2')->applyFromArray(
                    array(
                        'font' => array(
                            'bold' => true,
                            'color' => array('rgb' => '000000'),
                            'size' => 14,
                            'name' => 'Arial',
                        )
                      )
                );

          $sheet->getActiveSheet()->getStyle($hc.'3:'.$hc.$hr)->applyFromArray(
                      array(
                          'font' => array(
                              'bold' => false,
                              'color' => array('rgb' => '000000'),
                              'size' => 14,
                              'name' => 'Arial',
                          )
                        )
                  );

          foreach(range('B',$hc) as $columnID) {
                $sheet->getActiveSheet()->getColumnDimension($columnID)
                  ->setAutoSize(false)->setWidth(12);
          }

          $sheet->getActiveSheet()->getColumnDimension('A')
            ->setAutoSize(true);
   }

    public function download_excel()
    {
        if (!$this->lti_object->isInstructor) {
                return $this->no_permission_message;
        }

            $form = '';
            $message = '';

        if (ee()->input->post('peer_assess_action') === 'download_peer_assessments') {

        include_once PATH_THIRD.'lti_peer_assessment'.DIRECTORY_SEPARATOR.'libraries'.DIRECTORY_SEPARATOR.'PHPExcel'.DIRECTORY_SEPARATOR.'Classes'.DIRECTORY_SEPARATOR.'PHPExcel.php';

        $max_assessors = 0;
        $rows = $this->excel_instructor_report($max_assessors);

// Create new PHPExcel object
$mainDocument = new PHPExcel();

$studentDocs = array();
$studentDocAssessorSheets = array();

// set locked header for spreadsheet
if (!$format_array = $this->_set_mainDocument_header($mainDocument, $max_assessors)) {
    return false;
}

                $short_title = $format_array['short_title'];
                $highestColumnStr = $format_array['highestColumnStr'];


$rowcount = 5;

                $colors = array('E6EFD7', 'F2DCDB', 'FBE4D0');
                $color_index = 0;
                $prev_group_name = '';
                $group_start_row = 5;
                $settings = $this->_get_plugin_settings_array();

                $doc_index = 1;

                $this->rubric_template_array = $this->get_rubric_data_array();

                // hyperlink index to sheet in studentdoc
                $sheet_hl_index = array();

                $mainDocument->setActiveSheetIndex(0)->getStyle('A5:'.$highestColumnStr.$rowcount)->applyFromArray(
                                array(
                                      'font' => array(
                                          'bold' => false,
                                          'color' => array('rgb' => '000000'),
                                          'size' => 11,
                                          'name' => 'Arial',
                                      ),
                                    )
                            );

try {
                foreach ($rows as $row) {
                    if ($settings['score_calculation']['standard_mean']) {
                        $mainDocument->setActiveSheetIndex(0)
                                    ->setCellValue('A'.$rowcount, $row['firstname'])
                                    ->setCellValue('B'.$rowcount, $row['lastname'])
                                    ->setCellValue('C'.$rowcount, $row['username'])
                                    ->setCellValue('D'.$rowcount, $row['group_name']) // set color later
                                    ->setCellValue('E'.$rowcount, $row['mean'])
                                    ->setCellValue('F'.$rowcount, $row['multiplier'])
                                    ->setCellValue('G'.$rowcount, $row['group_members'])
                                    ->setCellValue('H'.$rowcount, $row['assess_count'] );
                    } else {
                        $mainDocument->setActiveSheetIndex(0)
                                      ->setCellValue('A'.$rowcount, $row['firstname'])
                                      ->setCellValue('B'.$rowcount, $row['lastname'])
                                      ->setCellValue('C'.$rowcount, $row['username'])
                                      ->setCellValue('D'.$rowcount, $row['group_name']) // set color later
                                      ->setCellValue('E'.$rowcount, $row['SAPA_factor'])
                                      ->setCellValue('F'.$rowcount, $row['SPA_factor'])
                                      ->setCellValue('G'.$rowcount, $row['group_members'])
                                      ->setCellValue('H'.$rowcount, $row['assess_count']);

                        $mainDocument->getActiveSheet(0)->getStyle('E5:F'.$rowcount)->getNumberFormat()->setFormatCode('#,##0.00');
                    }

          $colcount = 8;
          $student_sheet = 1;

          $link_style = array(
            'font' => array(
              'bold' => true,
              'underline' => true,
              'color' => array('rgb' => '0000FF'),
              'size' => 14,
              'name' => 'Arial',
            ), );

          foreach ($row['assessor_cols'] as $cols) {
                $colIndex = 0;
                $colCount = count($cols);

                foreach ($cols as $key => $col) {
                  $colIndex = $colIndex + 1;
              // create student document instance
              if (!key_exists($row['username'], $studentDocs)) {
                  $label = 'Student Stats';
                  $label = substr($label, 0, 31);
                  $studentDocs[$row['username']] = new PHPExcel();

                  $studentDocs[$row['username']]->setActiveSheetIndex(0)->setTitle($label)->setCellValue('A1', 'Back to Main Document')
    ->getStyle('A1')->applyFromArray($link_style);

                  $active = $this->_set_studentDoc_header($studentDocs[$row['username']], $row['firstname']." ".$row['lastname']);

                  $active->getCell('A1')
                    ->getHyperlink()->setUrl('main.xlsx');
                  $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B4', $row['username']);

                  $doc_index = $doc_index + 1;
                }
                if ($key === 'comment') {
                              /*    $mainDocument->setActiveSheetIndex(0)->getStyle($col_str.$rowcount)->applyFromArray(
                          array(
                          'font' => array(
                              'bold' => false,
                              'italic' => true,
                          ), )
                  );*/

                  //    $mainDocument->setActiveSheetIndex(0)->getColumnDimension($col_str)->setAutoSize(false)->setWidth(20);

                      $studentDocs[$row['username']]->setActiveSheetIndex($student_sheet)->setCellValue('A15', 'Comment: ');
                      $studentDocs[$row['username']]->getActiveSheet()->setCellValue('B15', $col);
                }
                  if($key === 'rubric_json') {
                    $studentDocs[$row['username']]->setActiveSheetIndex($student_sheet);
                    $lastCol = ($colCount == $colIndex);
                    $this->set_rubric_criteria($studentDocs[$row['username']], $col, $lastCol);
                }

            if ($key === 'username') {
              // set hyperlink list in column C
              if(!isset($sheet_hl_index[$row['username']])) {
                (int)$sheet_hl_index[$row['username']] = 5;
              }

              $hyperlink_cell = 'C'.$sheet_hl_index[$row['username']];
              $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue($hyperlink_cell, $col);
              $studentDocs[$row['username']]->getActiveSheet()->getCell($hyperlink_cell)->getHyperlink()->setUrl("sheet://'$col'!A1");

              $formula = $studentDocs[$row['username']]->getActiveSheet()->getCell('B7')->getValue();

              if(empty($formula)) {
                  $formula = "=";
              }

              $hr = $studentDocs[$row['username']]->getActiveSheet()->getHighestRow()+2; // add two as total not yet added
              $formula .= "+('$col'!B".$hr.")";

              $studentDocs[$row['username']]->getActiveSheet()->getCell('B7')->setValue($formula);
              $studentDocs[$row['username']]->getActiveSheet()->getCell('B6')->setValue("=B7/B8");

              $ls = $link_style;
              $ls['font']['size'] = 12;
              $studentDocs[$row['username']]->getActiveSheet()->getStyle($hyperlink_cell)->applyFromArray($ls);

              (int)$sheet_hl_index[$row['username']] += 1;

              if($student_sheet < 2) {
                  // mean assessment sheet
                  $studentDocs[$row['username']]->createSheet(1);
                  $studentDocs[$row['username']]->setActiveSheetIndex(1)->setTitle('Criteria Summary');

                  // set link in main document
                  $mainDocument->setActiveSheetIndex(0);

                  $mainDocument->getActiveSheet()->getStyle("C".$rowcount)->applyFromArray($link_style);

                  $mainDocument->getActiveSheet()->getCell("C".$rowcount)
                          ->getHyperlink()
                          ->setUrl("$row[username].xlsx");

                  $student_sheet = 2;
                }

                // create assessor sheet here, use $col (username) to maintain uniqueness of sheet
                $studentDocs[$row['username']]->createSheet($student_sheet)->setTitle($col);
                $studentDocs[$row['username']]->setActiveSheetIndex($student_sheet)->setCellValue('A1', $cols['full_name'].' Assessor Rubric');

                $this->rubric_layout($studentDocs[$row['username']]);
            }

            $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('A14', 'Criteria Summary');
            $studentDocs[$row['username']]->getActiveSheet()->getStyle('A14')->applyFromArray($link_style);

            $studentDocs[$row['username']]->getActiveSheet()->getCell('A14')
                    ->getHyperlink()
                    ->setUrl("sheet://'Criteria Summary'!A1");

                    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B5', $row['group_name']);

                //    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B6', $mean);

                                    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B8', $row['group_members']);

                                    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B9', $row['assess_count']);

                                    if (isset($row['SPA_factor']) && is_numeric($row['SPA_factor'])) {
                                        $spa = $row['SPA_factor'];
                                    } else {
                                        $spa = 'N/A';
                                    }

                                    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B11', $spa);

                                    if (isset($row['SAPA_factor']) && is_numeric($row['SAPA_factor'])) {
                                        $sapa = $row['SAPA_factor'];
                                    } else {
                                        $sapa = 'N/A';
                                    }

                                    $studentDocs[$row['username']]->setActiveSheetIndex(0)->setCellValue('B12', $sapa);

                                    ++$colcount;
                                }
                                  $student_sheet = $student_sheet + 1;
                            }

                            if(key_exists($row['username'], $studentDocs)) {
                                  $hr = $studentDocs[$row['username']]->setActiveSheetIndex(0)->getHighestRow();

                                //  $hc_str = PHPExcel_Cell::stringFromColumnIndex($hc);

                                  $studentDocs[$row['username']]->getActiveSheet()->getStyle("A1:C".$hr)->applyFromArray(array(
                                      'fill' => array(
                                          'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                          'color' => array('rgb' => $colors[$color_index]),
                                      ),
                                  ));

                                  $mainDocument->setActiveSheetIndex(0)->getStyle("A$group_start_row:$highestColumnStr$rowcount")->applyFromArray(
                                    array(
                                        'fill' => array(
                                            'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                            'color' => array('rgb' => $colors[$color_index]),
                                        ),
                                    )
                                  );
                            }

                              if($rowcount == ($group_start_row + $row['group_members'])) {
                                $color_index = $color_index < count($colors) - 1 ? $color_index + 1 : 0;

                                $group_start_row = $rowcount;
                              }

                            $rowcount = $rowcount + 1;
                            $prev_group_name = $row['group_name'];
                        }
} catch(Exception $e) {
          die("There was a problem with your download. ".$e.getMessage());
}

// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$mainDocument->setActiveSheetIndex(0);

// Redirect output to a client’s web browser (ZIP)
header('Content-Type: application/zip, application/octet-stream');
                header("Content-Disposition: attachment;filename=\"".$this->lti_object->course_name.".zip\"");
                header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');

// If you're serving to IE over SSL, then the following may be needed
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

$objWriter = PHPExcel_IOFactory::createWriter($mainDocument, 'Excel2007');

                $cache_path = ee()->config->item('lti_cache');

                $objWriter->save($this->rubric_template_array['cache_path'].'main.xlsx');

                foreach ($studentDocs as $key => $studentDoc) {
                    $objWriter = PHPExcel_IOFactory::createWriter($studentDoc, 'Excel2007');
                    $objWriter->save($this->rubric_template_array['cache_path']."$key.xlsx");
                }

                $zip = new ZipArchive();

                $zip_filename = $this->rubric_template_array['zip_path'].$this->lti_object->course_name.'.zip';
                $zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($this->rubric_template_array['cache_path']),
    RecursiveIteratorIterator::LEAVES_ONLY
);

                foreach ($files as $name => $file) {
                    // Skip directories (they would be added automatically)
    if (!$file->isDir()) {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($this->rubric_template_array['cache_path']));

        // Add current file to archive
        $zip->addFile($filePath, $relativePath);
    }
                }

                $zip->close();

                readfile($zip_filename);
                flush();

                unset($zip);
                unset($studentDocs);
                unset($mainDocument);

                return;
            }

            ee()->load->helper('form');
            $form .= '<p>Download the current list of peer assessments.</p>';
            $form .= form_open_multipart(static::$apeg_url.'/'.ee()->uri->uri_string());
            $form .= form_hidden('peer_assess_action', 'download_peer_assessments');
            $form .= form_submit('submit', 'Download', $this->lti_object->form_submit_class);
            $form .= "<p>$message</p>";
            $form .= form_close();

            return $form;

    }

    public function help_link()
    {
        $section = strtolower(ee()->TMPL->fetch_param('section'));
        $sub_section = strtolower(ee()->TMPL->fetch_param('sub_section'));
        //$class = ee()->TMPL->fetch_param('class');

        $link = "<a target='_blank' href='%url%'><span class='$this->help_glyph_class'></span></a>";

        $help_config = parse_ini_file(PATH_THIRD.$this->module_name.DIRECTORY_SEPARATOR.'help_links.ini', true);

        $this->help_url = isset($help_config['help_url']['base']) ? $help_config['help_url']['base'] : $this->help_url;

        $has_rubric = false;
        $allow_self_assessment = false;

        $r = ee()->db->get_where('lti_course_link_resources', array('course_id' => $this->lti_object->course_id));
        if ($r->row()) {
            $has_rubric = true;
        }

        $allow_self_assessment = $this->get_self_assessment_setting();

        $url = '';
        switch ($section) {
        case 'students':
              if ($has_rubric === true && $allow_self_assessment === true) {
                  $url = $this->help_url.$help_config[$section]['rubric_and_self_assess'];
              } elseif ($has_rubric === true) {
                  $url = $this->help_url.$help_config[$section]['with_rubric'];
              } else {
                  $url = $this->help_url.$help_config[$section]['no_rubric'];
              }
        break;
        case 'instructors':
            $url = $this->help_url.$help_config[$section][$sub_section];
      }

        $link = str_replace('%url%', $url, $link);

        return $link;
    }

/* CSV download */
public function download_csv()
{
    $settings = $this->get_instructor_general_settings_array();
    if(!$settings) return false; // not ready...

    $instructors = array();
    if(isset($settings[$this->module_name]['user_access'])) {
        $instructors = explode(',', $settings[$this->module_name]['user_access']);
    }
    //$member_id =  ee() -> session -> userdata('member_id');
    $member_id = ee()->session->userdata('member_id');

    if ($this->lti_object->isInstructor) {
        $form = '';
       // $len = 0;
        $message = '';
        if (ee()->input->post('peer_assess_action') == 'download_peer_assessments_csv') {
            $totals = $this->instructor_report();

            ee()->load->helper('download');

            $file = tempnam('lti_uploads', 'tmp_');
            $handle = fopen($file, 'w+b');

            //echo "<b>Count: ".count($totals)."</b>";
            if (count($totals) > 0) {
                fputcsv($handle, array($this->lti_object->course_name.' peer assessments'));

                fputcsv($handle, array('Full Name', 'Student No', 'Group No', 'Group Name', 'Mean Score', 'Multiplier', 'No of Group Members', 'No Assessed this Student', 'Comments'));

                foreach ($totals as $line) {
                    $line = str_replace("\r\n", "\n", $line);
                    fputcsv($handle, $line);
                }
            } else {
                $message .= "<span style='color:darkred'><b>No assessments to download</b><br></span>";
            }

            $data = file_get_contents($file);

            $data = mb_convert_encoding($data, 'UTF-8');
           // $len = strlen($data);
            unlink($file);

            force_download($this->lti_object->course_name.' Assessments.csv', $data);
        }

        ee()->load->helper('form');
        $form .= '<p>Download the current list of peer assessments.</p>';
        $form .= form_open_multipart(static::$apeg_url.'/'.ee()->uri->uri_string());
        $form .= form_hidden('peer_assess_action', 'download_peer_assessments_csv');
    //    $form .= form_hidden('csrf_token', CSRF_TOKEN); auto in EE 3
        $form .= form_submit('submit', 'Download', $this->lti_object->form_submit_class);
        $form .= "<p>$message</p>";
        $form .= form_close();

        return $form;
    } else {
        return $this->no_permission_message;
    }
}

private function get_report_query($member_id = NULL, $order_by = NULL)
{
    $type = ee()->TMPL->fetch_param('type', 'single');

    $where_array = array("lti_member_contexts.context_id" => $this->lti_object->context_id, "lti_member_contexts.tool_consumer_instance_id" => $this->lti_object->tool_consumer_instance_id, "lti_peer_assessments.resource_link_id" => $this->lti_object->resource_link_id);

    if($order_by == NULL) {
        $order_by_str = "lti_group_contexts.group_name";
    } else {
        $order_by_str = $order_by;
    }

    if($member_id !== NULL) {
        $where_array["lti_peer_assessments.member_id"] = $member_id;
    }

    ee()->db->select("lti_peer_assessments.group_id, lti_peer_assessments.member_id, lti_peer_assessments.assessor_member_id, lti_peer_assessments.rubric_json, lti_group_contexts.group_name, lti_group_contexts.group_id, members.screen_name, members.username, lti_peer_assessments.score, lti_peer_assessments.comment");
    ee()->db->join("members", "lti_peer_assessments.member_id = members.member_id");
    ee()->db->join("lti_group_contexts", "lti_group_contexts.id = lti_peer_assessments.group_context_id");
    ee()->db->join("lti_member_contexts", "lti_group_contexts.internal_context_id = lti_member_contexts.id");
    ee()->db->where($where_array);
    ee()->db->order_by($order_by_str);

    return ee()->db->get("lti_peer_assessments");
}

private function get_latest_peer_assessment($member_id = NULL) {
    return $this->get_report_query($member_id, "time DESC");
}

    private function inc_assessors(&$members_assessed_this_student, $member_id)
    {
        if(array_key_exists($member_id, $members_assessed_this_student)) {
            $members_assessed_this_student[$member_id] += 1;
        }
    }

    private function init_assessors($score, $init, $member_id, &$members_assessed_this_student, &$totals)
    {
        $members_assessed_this_student[$member_id] = $init;
        $totals[$member_id] = $score;
    }

    private function excel_instructor_report(&$max_assessors = 0)
    {
        $max_score = $this->get_score_setting();
        $include_self_in_mean_score = $this->get_self_mean_setting();

        $excel_rows = array();
        $members_assessed_this_student = array();
        $totals = array();
        $group_counts = array();

        /* for SPARK SPA and SAPA algorithm */
        $group_totals = array();
        $peer_ratings = array();

        /* sample data for debugging*/

        //$res_array = unserialize(file_get_contents(dirname(__FILE__).'/testdata.txt'));
        //$debug_assessor_member_array = unserialize(file_get_contents(dirname(__FILE__).'/assessor_sample_data.txt'));

        $results = $this->get_report_query();
        $settings = $this->_get_plugin_settings_array();
        $res_array = $results->result_array();

        foreach ($res_array as $row) {
            if (!array_key_exists($row['group_id'], $group_counts)) {
                $count_result = ee()->db->query("SELECT count(*) member_count FROM exp_lti_group_contexts WHERE group_id = '$row[group_id]'");
                    //$form .= var_export($count_result);
                    foreach ($count_result->result_array() as $count_row) {
                        $group_counts[$row['group_id']] = (int) $count_row['member_count'];
                        $peer_ratings[$row['group_id']] = array();
                    }
            }

                /* for debugging */
              //  $amr = $debug_assessor_member_array[$row['assessor_member_id']];
            $amr = ee()->db->get_where('members', array('member_id' => $row['assessor_member_id']))->row();

            if($amr) {

            $assessor_member_col = array('full_name' => $amr->screen_name,
                'username' => $amr->username,
                'score' => $row['score'],
                'comment' => $row['comment'],
                'rubric_json' => json_decode($row['rubric_json'], TRUE));

            $score = $row['score'];

            $full_name = explode(' ', $row['screen_name']);

            $firstname = $full_name[0];
            $n = count($full_name);
            $ln_arr = array_slice($full_name, -($n - 1), $n, true);
            $lastname = implode(' ', $ln_arr);

            if (array_key_exists($row['member_id'], $excel_rows)) {
                // TODO: allow this to be a toggle for instructors?
                   // $disallow_zero_grade = TRUE;
                    if ($score > 0) {
                        if ($this->include_self_in_mean_score || !$settings['score_calculation']['standard_mean']) {
                            $this->inc_assessors($members_assessed_this_student, $row['member_id']);
                        } else {
                            $this->inc_assessors($members_assessed_this_student, $row['member_id']);
                        }

                        if(array_key_exists($row['member_id'], $members_assessed_this_student)) {
                            $max_assessors = $members_assessed_this_student[$row['member_id']] > $max_assessors ? $members_assessed_this_student[$row['member_id']] : $max_assessors;
                        }

                        if(!isset($totals[$row['member_id']])) {
                              $totals[$row['member_id']] = 0;
                        }

                        $totals[$row['member_id']] += $score;

                        $peer_ratings[$row['group_id']][$row['member_id']] = $score;

                        // for SPA calcualtion
                        $group_totals[$row['group_id']][$row['member_id']] = $totals[$row['member_id']];

                        $excel_rows[$row['member_id']]['assessor_cols'][] = $assessor_member_col;
                    }
            } else {
                if ($score > 0) {
                  //  if ($this->include_self_in_mean_score || $amr->member_id !== $row['member_id']) {
                        $this->init_assessors($score, 1, $row['member_id'], $members_assessed_this_student, $totals);
                  //  }

                    if ($settings['score_calculation']['standard_mean']) {
                        $excel_rows[$row['member_id']] =
                            array('firstname' => $firstname,
                                  'lastname' => $lastname,
                                  'username' => $row['username'],
                                  'group_id' => $row['group_id'],
                                  'group_name' => $row['group_name'],
                                  'mean' => 0,
                                  'multiplier' => 0,
                                  'group_members' => $group_counts[$row['group_id']], 'assess_count' => 1,
                                  'assessor_cols' => array($assessor_member_col), );
                    } else {
                        $excel_rows[$row['member_id']] =
                            array('firstname' => $firstname,
                                  'lastname' => $lastname,
                                  'username' => $row['username'],
                                  'group_id' => $row['group_id'],
                                  'group_name' => $row['group_name'],
                                  'SAPA_factor' => 0,
                                  'SPA_factor' => 0,
                                  'group_members' => $group_counts[$row['group_id']], 'assess_count' => 1,
                                  'assessor_cols' => array($assessor_member_col), );
                    }
                } else {
                  //  if ($this->include_self_in_mean_score || $amr->member_id !== $row['member_id']) {
                        $this->init_assessors(0, 0, $row['member_id'], $members_assessed_this_student, $totals);
                  //  }

                    if ($settings['score_calculation']['standard_mean']) {
                        $excel_rows[$row['member_id']] =
                            array('firstname' => $firstname,
                                  'lastname' => $lastname,
                                  'username' => $row['username'],
                                  'group_id' => $row['group_id'],
                                  'group_name' => $row['group_name'],
                                  'mean' => 0,
                                  'multiplier' => 0,
                                  'group_members' => $group_counts[$row['group_id']], 'assess_count' => 0,
                                  'assessor_cols' => array(), );
                    } else {
                        $excel_rows[$row['member_id']] =
                                array('firstname' => $firstname,
                                      'lastname' => $lastname,
                                      'username' => $row['username'],
                                      'group_id' => $row['group_id'],
                                      'group_name' => $row['group_name'],
                                      'SAPA_factor' => 0,
                                      'SPA_factor' => 0,
                                      'group_members' => $group_counts[$row['group_id']], 'assess_count' => 0,
                                      'assessor_cols' => array(), );
                    }
                }
            }
          }
        }

        foreach ($res_array as $row) {
          if(isset($members_assessed_this_student[$row['member_id']])) {
            if ($members_assessed_this_student[$row['member_id']] > 0) {
                if ($settings['score_calculation']['standard_mean']) {    // standard mean
                    $members =  $members_assessed_this_student[$row['member_id']];
                    if(! $this->include_self_in_mean_score) { $members = $members - 1;}

                    if($members > 0) {
                        $mean_score = (int) $totals[$row['member_id']] / $members;
                    } else {
                        $mean_score = 0;
                      }
                    $excel_rows[$row['member_id']]['mean'] = round($mean_score, 0, PHP_ROUND_HALF_DOWN);
                    $excel_rows[$row['member_id']]['multiplier'] = round($mean_score / $max_score, 2, PHP_ROUND_HALF_DOWN);
                } else { // SPARK algorithm
                  if(isset($group_totals[$row['group_id']])) {
                    $group_mean = $this->group_mean($group_totals[$row['group_id']]);
                    if($group_mean > 0) {
                        $mean_score = $totals[$row['member_id']] / $group_mean;
                    } else{ $mean_score = 0;}
                    $SAPA_factor = $this->get_SAPA($row['member_id'], $peer_ratings[$row['group_id']]);

                    $SPA_factor = sqrt($mean_score);

                    if ($settings['score_calculation']['spark_plus']['sap_knee']) {
                        if ($mean_score < 1) {
                            $SPA_factor = $mean_score;
                        }
                    } elseif ($settings['score_calculation']['spark_plus']['sap_linear']) {
                        $SPA_factor = $mean_score;
                    }

                    $excel_rows[$row['member_id']]['SPA_factor'] = $SPA_factor;
                    $excel_rows[$row['member_id']]['SAPA_factor'] = $SAPA_factor;
                  }
                }
                $excel_rows[$row['member_id']]['assess_count'] = $members_assessed_this_student[$row['member_id']];
            } else {
                $mean_score = 0;
            }
          }
        }

        unset($results);
        unset($totals);
        unset($members_assessed_this_student);
        unset($group_totals);
        unset($peer_ratings);
        unset($group_counts);

        return $excel_rows;
    }

    private function get_SPARK_pa_array($member_id) {
      $results = $this->get_latest_peer_assessment($member_id);
      $res_array = $results->result_array();

      return $res_array;
    }

    /*
      SPARK - SPA factor - see http://sparkplus.com.au/factors/spark_plus_supporting_resources_version_2.1_STD.pdf
    */
    private function get_SPA($member_id, $peer_assessment_array) {

    }


    /* SPARK - SAPA factor see
    http://sparkplus.com.au/factors/spark_plus_supporting_resources_version_2.1_STD.pdf
    */

    public function SPARK_plus() {
        $member_id = ee()->session->userdata('member_id');
        $peer_assessment_array = $this->get_SPARK_pa_array($member_id);

        $variable_rows['sapa'] = $this->get_SAPA($member_id, $peer_assessment_array);
        $variable_rows['spa'] = $this->get_SPA($member_id, $peer_assessment_array);

        $variables = array();
        $variables[] = $variable_rows;

        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $variables);
    }

    private function get_SAPA($member_id, $peer_ratings_array)
    {
        $indiv_rating = 0;
        $mean_peer_rating = 0;
        if(isset($peer_ratings_array[$member_id])) {
            $indiv_rating = $peer_ratings_array[$member_id];

            $total = 0;
            $count = 0;
            foreach ($peer_ratings_array as $a_member_id => $score) {
              //  if(isset($a_member_id)) {
                    if($a_member_id != $member_id) {
                              $total += $score;
                              $count = $count + 1;
                    }
                //}
            }

          if($count > 0 && $total > 0/*&& $mean_peer_rating > 0*/) {
                  $mean_peer_rating = $total / $count;
                  $self_div_mean = $indiv_rating / $mean_peer_rating;

                  $SAPA = sqrt($self_div_mean);

                  return $SAPA;
            }
        }

        return 0;
    }

    private function indiv_mean($total, $assessors) {
      $only_non_zero = array();
      $indiv_total = 0;
      $mean = 0;

      foreach ($assessors as $rating) {
          if($rating > 0) {
                $indiv_total += $rating;
                array_push($only_non_zero, $rating);
          }
      }

      $members = count($only_non_zero);

      if($members > 0) {
          $mean = $indiv_total / $members;
          $mean = round($mean, 0, PHP_ROUND_HALF_DOWN);
      }

      return $mean;
    }

    private function group_mean($group_totals)
    {
        $group_total = 0;

        foreach ($group_totals as $total) {
            $group_total += (int) $total;
        }

        $members = count($group_totals);

        if($members > 0) {
            $mean = $group_total / $members;
        }

        return $mean;
    }

    private function group_self_rating($member_id)
    {
    }

/* CSV instructor report*/
private function instructor_report(&$max_assessors = 0)
{
    $results = $this->get_report_query();
    $max_score = $this->get_score_setting();

    $settings = $this->_get_plugin_settings_array();
    $this->include_self_in_mean_score = $settings['include_self_in_mean_score'];

    $csv_rows = array();
    $members_assessed_this_student = array();
    $totals = array();
    $group_counts = array();
    $group_totals = array();
    $peer_ratings = array();

    foreach ($results->result_array() as $row) {
        if (!array_key_exists($row['group_id'], $group_counts)) {
            $count_result = ee()->db->query("SELECT count(*) member_count FROM exp_lti_group_contexts WHERE group_id = '$row[group_id]'");
                    //$form .= var_export($count_result);
                    foreach ($count_result->result_array() as $count_row) {
                        $group_counts[$row['group_id']] = (int) $count_row['member_count'];
                    }
        }

        $res = ee()->db->get_where('members', array('member_id' => $row['assessor_member_id']));

        $amr = NULL;
        if($res->num_rows() === 1) {

          $amr = $res->row();
        }
        if($amr !== NULL) {

                      $assessor_member_name = $amr->screen_name.' ('.$amr->username.')';
                      $assessor_member_name .= chr(13).' Gave mark: '.$row['score'].chr(13).chr(13);

                      if (!array_key_exists($row['member_id'], $csv_rows)) {
                            $members_assessed_this_student[$row['member_id']] = 0;
                            $totals[$row['member_id']] = 0;
                            $peer_ratings[$row['group_id']][$row['member_id']] = array();
                            $csv_rows[$row['member_id']] = array($row['screen_name'], $row['username'], $row['group_id'], $row['group_name'], 0,
                                    0, $group_counts[$row['group_id']], 1, "");
                      }

                    $score = $row['score'];
                    $members_assessed_this_student[$row['member_id']] = $members_assessed_this_student[$row['member_id']] + 1;  // count assessments (not all members may assess)
                    $max_assessors = $members_assessed_this_student[$row['member_id']] > $max_assessors ? $members_assessed_this_student[$row['member_id']] : $max_assessors;

                    $totals[$row['member_id']] = $totals[$row['member_id']] + $score;


                    $peer_ratings[$row['group_id']][$row['member_id']][] = $score;

                    // for SPA calcualtion
                    $group_totals[$row['group_id']][$row['member_id']] = $totals[$row['member_id']];

                    $csv_rows[$row['member_id']][8] .= $assessor_member_name."Comment: ".chr(13).$row['comment'].chr(13).'---------------'.chr(13);


      }
    }

    foreach ($results->result_array() as $row) {
      if(isset($members_assessed_this_student[$row['member_id']])) {
        if ($members_assessed_this_student[$row['member_id']] > 0) {
            $mean_score = (int) $totals[$row['member_id']] / $members_assessed_this_student[$row['member_id']];
            $group_mean = $this->group_mean($group_totals[$row['group_id']]);

            if($group_mean > 0) {
                $spa_mean_score = $totals[$row['member_id']] / $group_mean;
            } else{ $spa_mean_score = 0;}

            $mean_score = $totals[$row['member_id']] / $members_assessed_this_student[$row['member_id']];

            $csv_rows[$row['member_id']][4] = round($mean_score, 0, PHP_ROUND_HALF_DOWN);
            $csv_rows[$row['member_id']][5] = round(sqrt($spa_mean_score), 2, PHP_ROUND_HALF_DOWN); // SPA score multiplier
            $csv_rows[$row['member_id']][7] = $members_assessed_this_student[$row['member_id']];    } else {
            $mean_score = 0;
        }
      }
    }

    unset($results);
    unset($totals);
    unset($members_assessed_this_student);

    return $csv_rows;
}
    private function __current_submission_where_clause($member_id, $group_id) {
          return array("assessor_member_id" => $member_id, "group_id" => $group_id, "current" => 1);
    }

    private function _clear_pointer_to_current($member_id, $group_id) {
        $where = $this->__current_submission_where_clause($member_id, $group_id);

        ee()->db->where($where);
        ee()->db->update("lti_peer_assessments", array("current" => 0));
    }

    private function _get_last_assessment_ids($member_id, $group_id) {
          $where = $this->__current_submission_where_clause($member_id, $group_id);

          ee()->db->select("`previous_id` as `id`");
          ee()->db->where($where);
          $res = ee()->db->get("lti_peer_assessments");

          $ids = array();
          foreach($res->result_array() as $row) {
                  $ids[] = $row['id'];
          }
          $str = implode(",", $ids);

          if(strlen(trim($str)) > 0) {
                return "($str)";
          }

          return FALSE;
    }

    public function unlock_last_submission() {
        $affected = 0;
        $member_id = ee()->input->post('id');
        $group_id = ee()->input->post('cxt');

        $str = $this->_get_last_assessment_ids($member_id, $group_id); // MUST stay in this order!!

        if($str !== FALSE) {
          $this->_clear_pointer_to_current($member_id, $group_id);

          ee()->db->where("`id` IN $str");
          ee()->db->update('lti_peer_assessments', array('locked' => 0, 'current' => 1));
          $affected += ee()->db->affected_rows();
        }

        echo json_encode(array("rows_affected" => $affected));
        exit();
    }

    public function clear_last_submission() {
        $affected = 0;

        $member_id = ee()->input->post('id');
        $group_id = ee()->input->post('cxt');

        $str = $this->_get_last_assessment_ids($member_id, $group_id);
        $this->_clear_pointer_to_current($member_id, $group_id);

        ee()->db->where("`id` IN $str");
        ee()->db->update('lti_peer_assessments',
                    array('locked' => 0,
                          'rubric_json' => NULL,
                          'score' => 0,
                          'comment' => '',
                          'current' => 1));

        $affected += ee()->db->affected_rows();

        echo json_encode(array("rows_affected" => $affected));
        exit();
    }
}

/* End of file mod.learning_tools_integration.php */
/* Location: /system/expressionengine/third_party/learning_tools_integration/mod.learning_tools_integration.php */
