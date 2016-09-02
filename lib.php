<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the moodle hooks for the submission admincomments plugin
 *
 * @package   assignsubmission_admincomments
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 *
 * Callback method for data validation---- required method for AJAXmoodle based admincomment API
 *
 * @param stdClass $options
 * @return bool
 */
function assignsubmission_admincomments_comment_validate(stdClass $options) {
    global $USER, $CFG, $DB;

    if ($options->commentarea != 'submission_admincomments' &&
            $options->commentarea != 'submission_admincomments_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidadmincommentitemid');
    }
    $context = $options->context;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assignment = new assign($context, null, null);

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }
    $canview = false;
    if ($submission->userid) {
        $canview = $assignment->can_view_submission($submission->userid);
    } else {
        $canview = $assignment->can_view_group_submission($submission->groupid);
    }
    if (!$canview) {
        throw new comment_exception('nopermissiontoadmincomment');
    }

    return true;
}

/**
 * Permission control method for submission plugin ---- required method for AJAXmoodle based admincomment API
 *
 * @param stdClass $options
 * @return array
 */
function assignsubmission_admincomments_comment_permissions(stdClass $options) {
    global $USER, $CFG, $DB;

    if ($options->commentarea != 'submission_admincomments' &&
            $options->commentarea != 'submission_admincomments_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidadmincommentitemid');
    }
    $context = $options->context;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assignment = new assign($context, null, null);

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }

    if ($assignment->get_instance()->teamsubmission &&
        !$assignment->can_view_group_submission($submission->groupid)) {
        return array('post' => false, 'view' => false);
    }

    if (!$assignment->get_instance()->teamsubmission &&
        !$assignment->can_view_submission($submission->userid)) {
        return array('post' => false, 'view' => false);
    }

    return array('post' => true, 'view' => true);
}

/**
 * Callback called by admincomment::get_admincomments() and admincomment::add(). Gives an opportunity to enforce blind-marking.
 *
 * @param array $admincomments
 * @param stdClass $options
 * @return array
 * @throws comment_exception
 */
function assignsubmission_admincomments_comment_display($admincomments, $options) {
    global $CFG, $DB, $USER, $COURSE;

    if ($options->commentarea != 'submission_admincomments' &&
        $options->commentarea != 'submission_admincomments_upgrade') {
        throw new comment_exception('invalidcommentarea');
    }
    if (!$submission = $DB->get_record('assign_submission', array('id' => $options->itemid))) {
        throw new comment_exception('invalidadmincommentitemid');
    }
    $context = $options->context;
    $cm = $options->cm;
    $course = $options->courseid;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    $assignment = new assign($context, $cm, $course);

    if ($assignment->get_instance()->id != $submission->assignment) {
        throw new comment_exception('invalidcontext');
    }

    if ($assignment->is_blind_marking() && !empty($admincomments)) {
        // Blind marking is being used, may need to map unique anonymous ids to the admincomments.
        $usermappings = array();
        $hiddenuserstr = trim(get_string('hiddenuser', 'assign'));
        $guestuser = guest_user();

        foreach ($admincomments as $admincomment) {
            // Anonymize the admincomments.
            if (empty($usermappings[$admincomment->userid])) {
                // The blind-marking information for this admincommenter has not been generated; do so now.
                $anonid = $assignment->get_uniqueid_for_user($admincomment->userid);
                $admincommenter = new stdClass();
                $admincommenter->firstname = $hiddenuserstr;
                $admincommenter->lastname = $anonid;
                $admincommenter->picture = 0;
                $admincommenter->id = $guestuser->id;
                $admincommenter->email = $guestuser->email;
                $admincommenter->imagealt = $guestuser->imagealt;

                // Temporarily store blind-marking information for use in later admincomments if necessary.
                $usermappings[$admincomment->userid]->fullname = fullname($admincommenter);
                $usermappings[$admincomment->userid]->avatar = $assignment->get_renderer()->user_picture($admincommenter,
                        array('size' => 18, 'link' => false));
            }

            // Set blind-marking information for this admincomment.
            $admincomment->fullname = $usermappings[$admincomment->userid]->fullname;
            $admincomment->avatar = $usermappings[$admincomment->userid]->avatar;
            $admincomment->profileurl = null;
        }
    }

    // Do not display delete option if the user is not the creator.
    foreach ($admincomments as &$admincomment) {
        if ($admincomment->userid != $USER->id) {
            // Check if the user is manager.
            if (!has_capability('moodle/site:caneditadmincomment', context_user::instance($USER->id)) &&
                !has_capability('moodle/site:caneditadmincomment', context_course::instance($COURSE->id))) {
                $admincomment->delete = 0;
            }
        }
    }

    return $admincomments;
}

/**
 * Callback to force the userid for all admincomments to be the userid of the submission and NOT the global $USER->id. This
 * is required by the upgrade code. Note the admincomment area is used to identify upgrades.
 *
 * @param stdClass $admincomment
 * @param stdClass $param
 */
function assignsubmission_admincomments_comment_add(stdClass $admincomment, stdClass $param) {

    global $DB;
    if ($admincomment->commentarea == 'submission_admincomments_upgrade') {
        $submissionid = $admincomment->itemid;
        $submission = $DB->get_record('assign_submission', array('id' => $submissionid));

        $admincomment->userid = $submission->userid;
        $admincomment->commentarea = 'submission_admincomments';
    }
}

