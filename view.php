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
 * Copyright (C) 2007-2011 Catalyst IT (http://www.catalyst.net.nz)
 * Copyright (C) 2011-2013 Totara LMS (http://www.totaralms.com)
 * Copyright (C) 2014 onwards Catalyst IT (http://www.catalyst-eu.net)
 *
 * @package    mod
 * @subpackage bookingform
 * @copyright  2014 onwards Catalyst IT <http://www.catalyst-eu.net>
 * @author     Stacey Walker <stacey@catalyst-eu.net>
 * @author     Alastair Munro <alastair.munro@totaralms.com>
 * @author     Aaron Barnes <aaron.barnes@totaralms.com>
 * @author     Francois Marier <francois@catalyst.net.nz>
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');
require_once('renderer.php');

global $DB, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$f = optional_param('f', 0, PARAM_INT); // Bookingform ID.
$location = optional_param('location', '', PARAM_TEXT); // Location.
$download = optional_param('download', '', PARAM_ALPHA); // Download attendance.

if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'bookingform');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'bookingform');
    }
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'bookingform');
    }
} else if ($f) {
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $f))) {
        print_error('error:incorrectbookingformid', 'bookingform');
    }
    if (!$course = $DB->get_record('course', array('id' => $bookingform->course))) {
        print_error('error:coursemisconfigured', 'bookingform');
    }
    if (!$cm = get_coursemodule_from_instance('bookingform', $bookingform->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'bookingform');
    }
} else {
    print_error('error:mustspecifycoursemodulebookingform', 'bookingform');
}

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/bookingform/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('standard');

if (!empty($download)) {
    require_capability('mod/bookingform:viewattendees', $context);
    bookingform_download_attendance($bookingform->name, $bookingform->id, $location, $download);
    exit();
}

require_course_login($course, true, $cm);
require_capability('mod/bookingform:view', $context);

// Logging and events trigger.
$params = array(
    'context'  => $context,
    'objectid' => $bookingform->id
);
$event = \mod_bookingform\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('bookingform', $bookingform);
$event->trigger();

$title = $course->shortname . ': ' . format_string($bookingform->name);

$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$pagetitle = format_string($bookingform->name);

$f2frenderer = $PAGE->get_renderer('mod_bookingform');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

if (empty($cm->visible) and !has_capability('mod/bookingform:viewemptyactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}
echo $OUTPUT->box_start();
echo $OUTPUT->heading(get_string('allsessionsin', 'bookingform', $bookingform->name), 2);

if ($bookingform->intro) {
    echo $OUTPUT->box_start('generalbox', 'description');
    echo format_module_intro('bookingform', $bookingform, $cm->id);
    echo $OUTPUT->box_end();
} else {
    echo html_writer::empty_tag('br');
}
$locations = get_locations($bookingform->id);
if (count($locations) > 2) {
    echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get', 'class' => 'formlocation'));
    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $bookingform->id));
    echo html_writer::select($locations, 'location', $location, '', array('onchange' => 'this.form.submit();'));
    echo html_writer::end_tag('div'). html_writer::end_tag('form');
}

print_session_list($course->id, $bookingform->id, $location);

if (has_capability('mod/bookingform:exportattendance', $context)) {
    echo $OUTPUT->heading(get_string('exportattendance', 'bookingform'));
    echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get'));
    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $bookingform->id));
    echo get_string('format', 'bookingform') . '&nbsp;';
    $formats = array('excel' => get_string('excelformat', 'bookingform'),
                     'ods' => get_string('odsformat', 'bookingform'));
    echo html_writer::select($formats, 'download', 'excel', '');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('exporttofile', 'bookingform')));
    echo html_writer::end_tag('div'). html_writer::end_tag('form');
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

function print_session_list($courseid, $bookingformid, $location) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;

    $f2frenderer = $PAGE->get_renderer('mod_bookingform');

    $timenow = time();

    $context = context_course::instance($courseid);
    $viewattendees = has_capability('mod/bookingform:viewattendees', $context);
    $editsessions = has_capability('mod/bookingform:editsessions', $context);

    $bookedsession = null;
    if ($submissions = bookingform_get_user_submissions($bookingformid, $USER->id)) {
        $submission = array_shift($submissions);
        $bookedsession = $submission;
    }

    $customfields = bookingform_get_session_customfields();

    $upcomingarray = array();
    $previousarray = array();
    $upcomingtbdarray = array();

    /**
     * Get the group members which I belong so we can find the teacher/coach.
     *
     * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
     * @since   02.14.2019
     */
    $mygroupid = bookingform_get_my_groupid($courseid, $USER->id);
    if (!empty($mygroupid)) {
        $groupmembers = groups_get_groups_members($mygroupid);
    }

    if ($sessions = bookingform_get_sessions($bookingformid, $location) ) {
        foreach ($sessions as $session) {

            /**
             * Do not display sessions for the student/coach if does not belong to it
             * or did not create it.
             *
             * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
             * @since   02.13.2019
             */
            if (!empty($mygroupid)) {
                if(!empty($cfg->bookingform_session_roles)) {
                    $roleid = $cfg->bookingform_session_roles;
                } else {
                    $roleid = 4;
                }
                // Get the trainers for this session.
                $trainers = bookingform_get_trainers($session->id, $roleid);
                if (is_array($trainers) || is_object($trainers)) {

                    // See if the teachers for this sessions are part of my group.
                    $skipsession = false;
                    foreach ($trainers as $trainer) {
                        if (!bookingform_find_id_in_array($groupmembers, $trainer->id)) {
                            $skipsession = true;
                        }
                    }

                    // If our trainer didn't create this session, skip it.
                    if ($skipsession) {
                        continue;
                    }

                }
            }

            $sessionstarted = false;
            $sessionfull = false;
            $sessionwaitlisted = false;
            $isbookedsession = false;

            $sessiondata = $session;
            $sessiondata->bookedsession = $bookedsession;

            // Add custom fields to sessiondata.
            $customdata = $DB->get_records('bookingform_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
            $sessiondata->customfielddata = $customdata;

            // Is session waitlisted.
            if (!$session->datetimeknown) {
                $sessionwaitlisted = true;
            }

            // Check if session is started.
            $sessionstarted = bookingform_has_session_started($session, $timenow);
            if ($session->datetimeknown && $sessionstarted && bookingform_is_session_in_progress($session, $timenow)) {
                $sessionstarted = true;
            } else if ($session->datetimeknown && $sessionstarted) {
                $sessionstarted = true;
            }

            // Put the row in the right table.
            if ($sessionstarted) {
                $previousarray[] = $sessiondata;
            } else if ($sessionwaitlisted) {
                $upcomingtbdarray[] = $sessiondata;
            } else { // Normal scheduled session.
                $upcomingarray[] = $sessiondata;
            }
        }
    }

    // Upcoming sessions.
    echo $OUTPUT->heading(get_string('upcomingsessions', 'bookingform'));
    if (empty($upcomingarray) && empty($upcomingtbdarray)) {
        print_string('noupcoming', 'bookingform');
    } else {
        $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        echo $f2frenderer->print_session_list_table($customfields, $upcomingarray, $viewattendees, $editsessions);
    }

    if ($editsessions) {
        $addsessionlink = html_writer::link(
            new moodle_url('sessions.php', array('f' => $bookingformid)),
            get_string('addsession', 'bookingform')
        );
        echo html_writer::tag('p', $addsessionlink);
    }

    // Previous sessions.
    if (!empty($previousarray)) {
        echo $OUTPUT->heading(get_string('previoussessions', 'bookingform'));
        echo $f2frenderer->print_session_list_table($customfields, $previousarray, $viewattendees, $editsessions);
    }
}

/**
 * Get bookingform locations
 *
 * @param   interger    $bookingformid
 * @return  array
 */
function get_locations($bookingformid) {
    global $CFG, $DB;

    $locationfieldid = $DB->get_field('bookingform_session_field', 'id', array('shortname' => 'location'));
    if (!$locationfieldid) {
        return array();
    }

    $sql = "SELECT DISTINCT d.data AS location
              FROM {bookingform} f
              JOIN {bookingform_sessions} s ON s.bookingform = f.id
              JOIN {bookingform_session_data} d ON d.sessionid = s.id
             WHERE f.id = ? AND d.fieldid = ?";

    if ($records = $DB->get_records_sql($sql, array($bookingformid, $locationfieldid))) {
        $locationmenu[''] = get_string('alllocations', 'bookingform');

        $i = 1;
        foreach ($records as $record) {
            $locationmenu[$record->location] = $record->location;
            $i++;
        }

        return $locationmenu;
    }

    return array();
}
