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

$s = required_param('s', PARAM_INT); // Bookingform session ID.
$confirm = optional_param('confirm', false, PARAM_BOOL);
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

if (!$session = bookingform_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'bookingform');
}
if (!$session->allowcancellations) {
    print_error('error:cancellationsnotallowed', 'bookingform');
}
if (!$bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform))) {
    print_error('error:incorrectbookingformid', 'bookingform');
}
if (!$course = $DB->get_record('course', array('id' => $bookingform->course))) {
    print_error('error:coursemisconfigured', 'bookingform');
}
if (!$cm = get_coursemodule_from_instance("bookingform", $bookingform->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'bookingform');
}

require_course_login($course);
$context = context_course::instance($course->id);
$contextmodule = context_module::instance($cm->id);
require_capability('mod/bookingform:view', $context);

$returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
if ($backtoallsessions) {
    $returnurl = "$CFG->wwwroot/mod/bookingform/view.php?f=$backtoallsessions";
}

$mform = new mod_bookingform_cancelsignup_form(null, compact('s', 'backtoallsessions'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted.

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'bookingform', $returnurl);
    }

    $timemessage = 4;

    $errorstr = '';
    if (bookingform_user_cancel($session, false, false, $errorstr, $fromform->cancelreason)) {

        // Logging and events trigger.
        $params = array(
            'context'  => $contextmodule,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\cancel_booking::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();

        $message = get_string('bookingcancelled', 'bookingform');

        if ($session->datetimeknown) {
            $error = bookingform_send_cancellation_notice($bookingform, $session, $USER->id);
            if (empty($error)) {
                if ($session->datetimeknown && $bookingform->cancellationinstrmngr) {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsentmgr', 'bookingform');
                } else {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsent', 'bookingform');
                }
            } else {
                print_error($error, 'bookingform');
            }
        }

        redirect($returnurl, $message, $timemessage);
    } else {

        // Logging and events trigger.
        $params = array(
            'context'  => $contextmodule,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\cancel_booking_failed::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();

        redirect($returnurl, $errorstr, $timemessage);
    }

    redirect($returnurl);
}

$pagetitle = format_string($bookingform->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/bookingform/cancelsignup.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions, 'confirm' => $confirm));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$heading = get_string('cancelbookingfor', 'bookingform', $bookingform->name);

$viewattendees = has_capability('mod/bookingform:viewattendees', $context);
$signedup = bookingform_check_signup($bookingform->id);

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($signedup) {
    bookingform_print_session($session, $viewattendees);
    $mform->display();
} else {
    print_error('notsignedup', 'bookingform', $returnurl);
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
