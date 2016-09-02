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
 * Events tests.
 *
 * @package    assignsubmission_admincomments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/base_test.php');

/**
 * Events tests class.
 *
 * @package    assignsubmission_admincomments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignsubmission_admincomments_events_testcase extends mod_assign_base_testcase {

    /**
     * Test admincomment_created event.
     */
    public function test_admincomment_created() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $assign = $this->create_instance();
        $submission = $assign->get_user_submission($this->students[0]->id, true);

        $context = $assign->get_context();
        $options = new stdClass();
        $options->area = 'submission_admincomments';
        $options->course = $assign->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'assignsubmission_admincomments';
        $options->showcount = true;
        $options->displaycancel = true;

        $admincomment = new comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $admincomment->add('New admincomment');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_admincomments\event\admincomment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test admincomment_deleted event.
     */
    public function test_admincomment_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $assign = $this->create_instance();
        $submission = $assign->get_user_submission($this->students[0]->id, true);

        $context = $assign->get_context();
        $options = new stdClass();
        $options->area    = 'submission_admincomments';
        $options->course    = $assign->get_course();
        $options->context = $context;
        $options->itemid  = $submission->id;
        $options->component = 'assignsubmission_admincomments';
        $options->showcount = true;
        $options->displaycancel = true;
        $admincomment = new comment($options);
        $newadmincomment = $admincomment->add('New admincomment 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $admincomment->delete($newadmincomment->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\assignsubmission_admincomments\event\admincomment_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/assign/view.php', array('id' => $assign->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }
}
