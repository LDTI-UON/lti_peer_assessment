<?php

// takes $row as input

$vars['students'][$row['member_id']]['lti_peer_assessment_unlock'] = "<button class='$this->button_class' id='lti_peer_assessment_unlock' data-id='$row[member_id]'>Unlock Peer Assessment</button>";
$vars['students'][$row['member_id']]['lti_peer_assessment_clear'] = "<button class='$this->button_class' id='lti_peer_assessment_clear' data-id='$row[member_id]'>Clear Peer Assessment</button>";
