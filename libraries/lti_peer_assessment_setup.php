<?php
use LTI\ExtensionHooks\ResourceModel;
use LTI\ExtensionHooks\Settings;

$groups_inserted = FALSE;
$groups_updated = FALSE;

if(!isset($form)) {
	$form = '';
}

$allow_self_assessment_setting = FALSE;

$allow_self_assessment_setting = Settings::get_plugin_setting('lti_peer_assessment', 'allow_self_assessment') == 1;


// users without a group.
$lonely_users = array();

 foreach ($file_rows as $group_name => $group) {
    foreach ($group as $assessing_key => $assessing_member) {
    foreach ($group as $group_key => $group_member) {

        $allow_assessment = $allow_self_assessment_setting ? TRUE : $assessing_key != $group_key;

        if (is_numeric($group_key) && is_numeric($assessing_key) && $allow_assessment) {

        		$pares =   ee() -> db -> get_where('lti_group_contexts', array('member_id' => $group_member['member_id'], 'internal_context_id' => $group_member['internal_context_id']));

                $group_data = '';
                if ($pares -> num_rows() == 1) {
                    $group_data = $pares -> row();
                } else {
                    if(! in_array($group_member['member_id'], $lonely_users)) {

                        $errors .= "<p><span style='font-weight: 100; font-family: monospace'>Peer Assessment:<br> User <span class='text-warning'>".
                            $group_member[$this->col_header_indexes['first_name']]." ".
                            $group_member[ $this->col_header_indexes['last_name']].
                            " (".$group_member[$this->col_header_indexes['user_name']].
                            ")</span> has no group</span></br></p>";

                        $lonely_users[] = $group_member['member_id'];
                    }

                    break;
                }

                $peer_data = array('group_context_id' => $group_data -> id, 'assessor_member_id' => $assessing_member['member_id'], 'member_id' => $group_member['member_id'], 'resource_link_id' => $this->parent_object->resource_link_id);
                $sanity =  ee() -> db -> get_where('lti_peer_assessments', $peer_data);

                if ($sanity -> num_rows() == 0) {
                    $peer_data['group_id'] = $group['group_id'];
                    ee() -> db -> insert('lti_peer_assessments', $peer_data);
                    $groups_inserted = true;
                } else {
                    ee()->db->where($peer_data);
                    $peer_data['group_id'] = $group['group_id'];
                    ee()->db->update('lti_peer_assessments', $peer_data);
                       $groups_updated = true;
                }

            }
      //  }
    }
}
}
if($groups_inserted === true) {
    $form .= "<p style='color: darkblue'>All groups were setup for peer assessment.</p>";
}

if($groups_updated === true) {
    $form .= "<p style='color: darkblue'>All groups re-registered for peer assessment.</p>";
}

/* clean-up orphaned peer assessments (without group context)
   group contexts are removed for users in the lti_peer_assessments module
   using the '__no_group__' key word in the group name column when calling the import_data function
*/

$single_table = ee()->db->dbprefix("lti_peer_assessments");

$group_contexts_table = ee()->db->dbprefix("lti_group_contexts");

$drop_sql = "DROP TEMPORARY TABLE IF EXISTS `flag_for_delete`";
$delete_single = "DELETE FROM `$single_table` WHERE `id` IN (SELECT `id` FROM `flag_for_delete`)";

ee()->db->query($drop_sql);

// normal peer assessment clean-up
$create_single_sql = "CREATE TEMPORARY TABLE IF NOT EXISTS `flag_for_delete` AS
(SELECT DISTINCT `A`.`id` FROM `$single_table` `A`
    LEFT JOIN `$group_contexts_table` `B` ON `A`.`member_id`
    LEFT JOIN `$group_contexts_table` `C` ON `A`.`assessor_member_id`
    WHERE `A`.`resource_link_id` = `" . $this->parent_object->resource_link_id . "` AND `A`.`group_context_id` NOT IN
(SELECT id FROM `$group_contexts_table`))";

ee()->db->query($create_single_sql);

ee()->db->query($delete_single);

ee()->db->query($drop_sql);
