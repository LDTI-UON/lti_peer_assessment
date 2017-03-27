<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* ExpressionEngine - by EllisLab
*
* @package		ExpressionEngine
* @author		ExpressionEngine Dev Team
* @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
* @license		http://expressionengine.com/user_guide/license.html
* @link		http://expressionengine.com
* @since		Version 2.0
* @filesource
*/

// ------------------------------------------------------------------------

/**
* EE Learning Tools Integration Module Install/Update File
*
* @package		ExpressionEngine
* @subpackage	Addons
* @category	Module
* @author		Paul Sijpkes
* @link		http://sijpkes.site11.com
*/

class Lti_peer_assessment_upd {

public $version = '0.8.34'; #build version#
public $mod_class = 'Lti_peer_assessment';

private $EE;

// this may need to be modified to add plugin name to field if more plugins
private $instructor_settings_table_name;
private $course_link_resources_table_name;
private $lti_peer_assessment_table_name;
private $lti_peer_assessment_rolling_table_name;
/**
* Constructor
*/
public function __construct()
{
$this->instructor_settings_table_name = ee()->db->dbprefix("lti_instructor_settings");
$this->course_link_resources_table_name = ee()->db->dbprefix("lti_course_link_resources");
$this->lti_peer_assessment_table_name = ee()->db->dbprefix("lti_peer_assessment");
$this->lti_peer_assessment_rolling_table_name = ee()->db->dbprefix("lti_peer_assessment_rolling");
}

// ----------------------------------------------------------------

/**
* Installation Method
*
* @return 	boolean 	TRUE
*/
public function install()
{
$mod_data = array(
	'module_name'			=> $this->mod_class,
	'module_version'		=> $this->version,
	'has_cp_backend'		=> "n",
	'has_publish_fields'	=> 'n'
);

ee()->db->insert('modules', $mod_data);

ee()->load->dbforge();

	$fields = array('id' => array('type' => 'INT',
																		'constraint' => '11',
																			 'null' => FALSE,
																	 'auto_increment' => TRUE),
										'group_id' => array('type' => 'MEDIUMINT',
																				'constraint' => '5',
																				'null' => FALSE ),
										'assessor_member_id' => array('type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE),
										'member_id' => array('type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE),
										'group_context_id' => array(
																						 'type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE
																			),
										'score' => array(
																						 'type' => 'SMALLINT',
																						 'constraint' => '3',
																						 'null' => FALSE
																			),
										 'comment' => array(
																						 'type' => 'TEXT',
																						 'null' => FALSE
																				),
										'rubric_json' => array(
																						 'type' => 'VARCHAR',
																								'constraint' => '1000',
																						 'null' => FALSE
																				),
										 'locked' => array( 'type' => 'TINYINT',
																				'constraint' => '1',
																				'null' => FALSE,
																				'default' => '0'
																				),
											'time' => array('type' => 'TIMESTAMP'),
										 'TMP_POST_ID' => array('type' => 'VARCHAR',
																						'constraint' => '8',
																						'null' => TRUE),
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('id', TRUE);
		ee()->dbforge->add_key(array('assessor_member_id', 'member_id','group_context_id'));
		ee()->dbforge->create_table('lti_peer_assessments', TRUE);

		$table_name = ee()->db->dbprefix("lti_peer_assessments");
		$sql = "ALTER TABLE $table_name ADD UNIQUE INDEX (assessor_member_id, member_id, group_context_id)";
		ee()->db->query($sql);

		// rolling peer assessment
				$fields = array(
											'id' => array('type' => 'INT',
																		'constraint' => '11',
																			 'null' => FALSE,
																	 'auto_increment' => TRUE),
										'group_id' => array('type' => 'MEDIUMINT',
																				'constraint' => '5',
																				'null' => FALSE ),
										'assessor_member_id' => array('type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE),
										'member_id' => array('type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE),
										'group_context_id' => array(
																						 'type' => 'MEDIUMINT',
																						 'constraint' => '5',
																						 'null' => FALSE,
																						 'auto_increment' => FALSE
																			),
										'score' => array(
																						 'type' => 'SMALLINT',
																						 'constraint' => '3',
																						 'null' => FALSE
																			),
										 'comment' => array(
																						 'type' => 'TEXT',
																						 'null' => FALSE
																				),
										'rubric_json' => array(
																						 'type' => 'VARCHAR',
																								'constraint' => '1000',
																						 'null' => FALSE
																				),
										 'locked' => array( 'type' => 'TINYINT',
																				'constraint' => '1',
																				'null' => FALSE,
																				'default' => '0'
																				),
												'time' => array('type' => 'TIMESTAMP'),
										 'TMP_POST_ID' => array('type' => 'VARCHAR',
																						'constraint' => '8',
																						'null' => TRUE),
		);

		ee()->dbforge->add_field($fields);
		ee()->dbforge->add_key('id', TRUE);
		ee()->dbforge->add_key(array('assessor_member_id', 'member_id', 'group_context_id'));
		ee()->dbforge->create_table('lti_peer_assessments_rolling', TRUE);

		$table_name = ee()->db->dbprefix("lti_peer_assessments_rolling");
		$sql = "CREATE INDEX assessor_rolling_context_index ON $table_name(assessor_member_id, member_id, group_context_id)";
		ee()->db->query($sql);

		/*
		*  Add instructor settings for this plugin
		*/
		$this->_alter_tables($this->version);

		/*
				Following 2 lines from Mfoo's answer here:
				http://stackoverflow.com/questions/3395798/mysql-check-if-a-column-exists-in-a-table-with-sql#7264865
		*/



		return TRUE;
}

public static $labels = array(
    "standard_mean" => "Standard Mean",
    "spark_plus" => "SPARK Plus",
    "sap_original" => "SAP original with SAPA",
    "sap_knee" => "SAP knee with SAPA",
    "sap_linear" => "SAP linear with SAPA",
);


public static $score_calculation = array("standard_mean" => FALSE,
                                    "spark_plus" => array());

public static $spark_plus = array(
              "sap_original" => FALSE,
              "sap_knee" => TRUE,
              "sap_linear" => FALSE
            );

public static $plugin_settings =
                      array(
                        "active" => 1,
                        "show_grade_column" => FALSE,
                        "show_comments" => FALSE,
                        "allow_self_assessment" => FALSE,
                        "show_column_scores" => FALSE,
                        "include_self_in_mean_score" => FALSE,
                        "score_calculation" => array(),
                        "user_access" => '',
                        "total_score" => 10,
       );

private function _alter_tables($current) {
			$plugin_settings = static::$plugin_settings;
			$plugin_settings['score_calculation'] = & static::$score_calculation;
			$plugin_settings['score_calculation']['spark_plus'] = & static::$spark_plus;

			$plugin_key = strtolower($this->mod_class);

			$res = ee()->db->get($this->instructor_settings_table_name);

			if($res->num_rows() > 0) {
						foreach($res->result() as $row) {
									$plugins = unserialize($row->plugins_active);

									if(empty($plugins)) { $plugins = array(); }

									$plugin_settings['show_grade_column'] = isset($row->show_grade_column) ? $row->show_grade_column : 0;
									$plugin_settings['show_comments'] = isset($row->show_comments) ? $row->show_comments : 0;
									$plugin_settings['allow_self_assessment'] = isset($row->allow_self_assessment) ? $row->allow_self_assessment : 0;
									$plugin_settings['show_column_scores'] = isset($row->show_column_scores) ? $row->show_column_scores : 0;
									$plugin_settings['include_self_in_mean_score'] = isset($row->include_self_in_mean_score) ? $row->include_self_in_mean_score : 0;
									$plugin_settings['user_access'] = isset($row->user_access) ? $row->user_access : '';
									$plugin_settings['total_score'] = isset($row->total_score) ? $row->total_score : 10;

									$plugins[$plugin_key] = $plugin_settings;

									$new_plugins = serialize($plugins);

									ee()->db->set('plugins_active', $new_plugins);
									ee()->db->where(array("course_key" => $row->course_key));
									ee()->db->update($this->instructor_settings_table_name);
						}
				}

				if ( ee()->db->table_exists( $this->lti_peer_assessment_table_name ) ) {
					$result = ee()->db->query("SHOW COLUMNS FROM `$this->lti_peer_assessment_table_name` LIKE 'score'");
					$exists = (count($result->result()) === 1) ? TRUE : FALSE;

					if( $exists ) {
							$alter_table = "ALTER TABLE  `$this->lti_peer_assessment_table_name` MODIFY `score` float unsigned;";
							ee()->db->query($alter_table);
					}
				}

				if ( ee()->db->table_exists( $this->lti_peer_assessment_rolling_table_name ) ) {
					$result = ee()->db->query("SHOW COLUMNS FROM `$this->lti_peer_assessment_rolling_table_name` LIKE 'score'");
					$exists = (count($result->result()) === 1) ? TRUE : FALSE;

					if( $exists ) {
							$alter_table = "ALTER TABLE  `$this->lti_peer_assessment_rolling_table_name` MODIFY `score` float unsigned;";
							ee()->db->query($alter_table);
					}
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'show_grade_column'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `show_grade_column`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'show_comments'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `show_comments`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'allow_self_assessment'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;


				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `allow_self_assessment`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'include_self_in_mean_score'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `include_self_in_mean_score`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'total_score'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `total_score`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'user_access'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `user_access`;";
						ee()->db->query($alter_instructor_settings);
				}
}

// ----------------------------------------------------------------

/**
* Uninstall
*
* @return 	boolean 	TRUE
*/
public function uninstall()
{
$mod_id = ee()->db->select('module_id')
						->get_where('modules', array(
							'module_name'	=> $this->mod_class
						))->row('module_id');

ee()->db->where('module_id', $mod_id)
			 ->delete('module_member_groups');

ee()->db->where('module_name', $this->mod_class)
			 ->delete('modules');


			 	if ( ee()->db->table_exists( $this->instructor_settings_table_name ) ) {
					$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'show_grade_column'");
					$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `show_grade_column`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'show_comments'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `show_comments`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->instructor_settings_table_name` LIKE 'allow_self_assessment'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;


				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->instructor_settings_table_name` DROP COLUMN `allow_self_assessment`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->course_link_resources_table_name` LIKE 'peer_assessment_show_column_scores'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->course_link_resources_table_name` DROP COLUMN `peer_assessment_show_column_scores`;";
						ee()->db->query($alter_instructor_settings);
				}

				$result = ee()->db->query("SHOW COLUMNS FROM `$this->course_link_resources_table_name` LIKE 'include_self_in_mean_score'");
				$exists = (count($result->result()) === 1) ? TRUE : FALSE;

				if( $exists ) {
						$alter_instructor_settings = "ALTER TABLE  `$this->course_link_resources_table_name` DROP COLUMN `include_self_in_mean_score`;";
						ee()->db->query($alter_instructor_settings);
				}
		}

ee()->load->dbforge();
ee()->dbforge->drop_table('lti_peer_assessments');
ee()->dbforge->drop_table('lti_peer_assessments_rolling');

ee()->db->delete('actions', array('class' => $this->mod_class, 'method' => 'message_preference'));

return TRUE;
}

// ----------------------------------------------------------------

/**
* Module Updater
*
* @return 	boolean 	TRUE
*/
public function update($current = '')
{
$this->_alter_tables($current);

return TRUE;
}

}
/* End of file upd.learning_tools_integration.php */
/* Location: /system/expressionengine/third_party/learning_tools_integration/upd.learning_tools_integration.php */