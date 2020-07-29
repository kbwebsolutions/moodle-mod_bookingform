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

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$f = optional_param('f', 0, PARAM_INT); // bookingform Module ID.
$s = optional_param('s', 0, PARAM_INT); // bookingform session ID.
$c = optional_param('c', 0, PARAM_INT); // Copy session.
$d = optional_param('d', 0, PARAM_INT); // Delete session.
$confirm = optional_param('confirm', false, PARAM_BOOL); // Delete confirmation.

$nbdays = 1; // Default number to show.

$session = null;
if ($id && !$s) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'bookingform');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'bookingform');
    }
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'bookingform');
    }
} else if ($s) {
    if (!$session = bookingform_get_session($s)) {
        print_error('error:incorrectcoursemodulesession', 'bookingform');
    }
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform))) {
        print_error('error:incorrectbookingformid', 'bookingform');
    }
    if (!$course = $DB->get_record('course', array('id' => $bookingform->course))) {
        print_error('error:coursemisconfigured', 'bookingform');
    }
    if (!$cm = get_coursemodule_from_instance('bookingform', $bookingform->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'bookingform');
    }

    $nbdays = count($session->sessiondates);
} else {
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $f))) {
        print_error('error:incorrectbookingformid', 'bookingform');
    }
    if (!$course = $DB->get_record('course', array('id' => $bookingform->course))) {
        print_error('error:coursemisconfigured', 'bookingform');
    }
    if (!$cm = get_coursemodule_from_instance('bookingform', $bookingform->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'bookingform');
    }
}

require_course_login($course);
$errorstr = '';
$context = context_course::instance($course->id);
$modulecontext = context_module::instance($cm->id);
require_capability('mod/bookingform:editsessions', $context);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/bookingform/sessions.php', array('f' => $f));

$returnurl = "view.php?f=$bookingform->id";

$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $course->maxbytes,
    'context'  => $modulecontext,
);


// Handle deletions.
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (bookingform_delete_session($session)) {

        // Logging and events trigger.
        $params = array(
            'context'  => $modulecontext,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\delete_session::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();
    } else {

        // Logging and events trigger.
        $params = array(
            'context'  => $modulecontext,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\delete_session_failed::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();
        print_error('error:couldnotdeletesession', 'bookingform', $returnurl);
    }
    redirect($returnurl);
}

$customfields = bookingform_get_session_customfields();

$sessionid = isset($session->id) ? $session->id : 0;

$details = new stdClass();
$details->id = isset($session) ? $session->id : 0;
$details->details = isset($session->details) ? $session->details : '';
$details->detailsformat = FORMAT_HTML;
$details = file_prepare_standard_editor($details, 'details', $editoroptions, $modulecontext, 'mod_bookingform', 'session', $sessionid);

$mform = new mod_bookingform_session_form(null, compact('id', 'bookingform', 'f', 's', 'c', 'nbdays', 'customfields', 'course', 'editoroptions'));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted.

    if (empty($fromform->submitbutton) && empty($fromform->submitbutton_add_new)) {
        print_error('error:unknownbuttonclicked', 'bookingform', $returnurl);
    }

    // Pre-process fields.
    if (empty($fromform->allowoverbook)) {
        $fromform->allowoverbook = 0;
    }
    if (empty($fromform->duration)) {
        $fromform->duration = 0;
    }
    if (empty($fromform->normalcost)) {
        $fromform->normalcost = 0;
    }
    if (empty($fromform->discountcost)) {
        $fromform->discountcost = 0;
    }

    $sessiondates = array();
    for ($i = 0; $i < $fromform->date_repeats; $i++) {
        if (!empty($fromform->datedelete[$i])) {
            continue; // Skip this date.
        }

        if (!empty($fromform->timestart[$i]) and !empty($fromform->timefinish[$i])) {
            $date = new stdClass();
            $date->timestart = $fromform->timestart[$i];
            $date->timefinish = $fromform->timefinish[$i];
            $sessiondates[] = $date;
        }
    }

    $todb = new stdClass();
    $todb->bookingform = $bookingform->id;
    $todb->datetimeknown = $fromform->datetimeknown;
    $todb->capacity = $fromform->capacity;
    $todb->allowoverbook = $fromform->allowoverbook;
    $todb->duration = $fromform->duration;
    $todb->normalcost = $fromform->normalcost;
    $todb->discountcost = $fromform->discountcost;
    if (has_capability('mod/bookingform:configurecancellation', $context)) {
        $todb->allowcancellations = $fromform->allowcancellations;
    }

    $sessionid = null;
    $transaction = $DB->start_delegated_transaction();

    $update = false;
    if (!$c and $session != null) {
        $update = true;
        $sessionid = $session->id;

        $todb->id = $session->id;
        if (!bookingform_update_session($todb, $sessiondates)) {
            $transaction->force_transaction_rollback();

            // Logging and events trigger.
            $params = array(
                'context'  => $modulecontext,
                'objectid' => $session->id
            );
            $event = \mod_bookingform\event\update_session_failed::create($params);
            $event->add_record_snapshot('bookingform_sessions', $session);
            $event->add_record_snapshot('bookingform', $bookingform);
            $event->trigger();
            print_error('error:couldnotupdatesession', 'bookingform', $returnurl);
        }

        // Remove old site-wide calendar entry.
        if (!bookingform_remove_session_from_calendar($session, SITEID)) {
            $transaction->force_transaction_rollback();
            print_error('error:couldnotupdatecalendar', 'bookingform', $returnurl);
        }
    } else {
        if (!$sessionid = bookingform_add_session($todb, $sessiondates)) {
            $transaction->force_transaction_rollback();

            // Logging and events trigger.
            $params = array(
                'context'  => $modulecontext,
                'objectid' => $bookingform->id
            );
            $event = \mod_bookingform\event\add_session_failed::create($params);
            $event->add_record_snapshot('bookingform', $bookingform);
            $event->trigger();
            print_error('error:couldnotaddsession', 'bookingform', $returnurl);
        }
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        if (!isset($fromform->$fieldname)) {
            $fromform->$fieldname = ''; // Need to be able to clear fields.
        }

        if (!bookingform_save_customfield_value($field->id, $fromform->$fieldname, $sessionid, 'session')) {
            $transaction->force_transaction_rollback();
            print_error('error:couldnotsavecustomfield', 'bookingform', $returnurl);
        }
    }

    // Save trainer roles.
    if (isset($fromform->trainerrole)) {
        bookingform_update_trainers($sessionid, $fromform->trainerrole);
    }

    // Retrieve record that was just inserted/updated.
    if (!$session = bookingform_get_session($sessionid)) {
        $transaction->force_transaction_rollback();
        print_error('error:couldnotfindsession', 'bookingform', $returnurl);
    }

    // Update calendar entries.
    bookingform_update_calendar_entries($session, $bookingform);
    if ($update) {

        // Logging and events trigger.
        $params = array(
            'context'  => $modulecontext,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\update_session::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();
    } else {

        // Logging and events trigger.
        $params = array(
            'context'  => $modulecontext,
            'objectid' => $session->id
        );
        $event = \mod_bookingform\event\add_session::create($params);
        $event->add_record_snapshot('bookingform_sessions', $session);
        $event->add_record_snapshot('bookingform', $bookingform);
        $event->trigger();
    }

    $transaction->allow_commit();

    $data = file_postupdate_standard_editor($fromform, 'details', $editoroptions, $modulecontext, 'mod_bookingform', 'session', $session->id);
    $DB->set_field('bookingform_sessions', 'details', $data->details, array('id' => $session->id));

    if (!empty($fromform->submitbutton_add_new)) {
        redirect(new moodle_url('/mod/bookingform/sessions.php', array('f' => $bookingform->id)));
    }

    redirect($returnurl);
} else if ($session != null) { // Edit mode.

    // Set values for the form.
    $toform = new stdClass();
    $toform = file_prepare_standard_editor($details, 'details', $editoroptions, $modulecontext, 'mod_bookingform', 'session', $session->id);

    $toform->datetimeknown = (1 == $session->datetimeknown);
    $toform->capacity = $session->capacity;
    $toform->allowoverbook = $session->allowoverbook;
    $toform->duration = $session->duration;
    $toform->normalcost = $session->normalcost;
    $toform->discountcost = $session->discountcost;
    if (has_capability('mod/bookingform:configurecancellation', $context)) {
        $toform->allowcancellations = $session->allowcancellations;
    }

    if ($session->sessiondates) {
        $i = 0;
        foreach ($session->sessiondates as $date) {
            $idfield = "sessiondateid[$i]";
            $timestartfield = "timestart[$i]";
            $timefinishfield = "timefinish[$i]";
            $toform->$idfield = $date->id;
            $toform->$timestartfield = $date->timestart;
            $toform->$timefinishfield = $date->timefinish;
            $i++;
        }
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        $toform->$fieldname = bookingform_get_customfield_value($field, $session->id, 'session');
    }

    $mform->set_data($toform);
}

if ($c) {
    $heading = get_string('copyingsession', 'bookingform', $bookingform->name);
} else if ($d) {
    $heading = get_string('deletingsession', 'bookingform', $bookingform->name);
} else if ($id || $f) {
    $heading = get_string('addingsession', 'bookingform', $bookingform->name);
} else {
    $heading = get_string('editingsession', 'bookingform', $bookingform->name);
}

$pagetitle = format_string($bookingform->name);


$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if (!empty($errorstr)) {
    echo $OUTPUT->container(html_writer::tag('span', $errorstr, array('class' => 'errorstring')), array('class' => 'notifyproblem'));
}

if ($d) {
    $viewattendees = has_capability('mod/bookingform:viewattendees', $context);
    bookingform_print_session($session, $viewattendees);
    $optionsyes = array('sesskey' => sesskey(), 's' => $session->id, 'd' => 1, 'confirm' => 1);
    echo $OUTPUT->confirm(get_string('deletesessionconfirm', 'bookingform', format_string($bookingform->name)),
        new moodle_url('sessions.php', $optionsyes),
        new moodle_url($returnurl));
} else {
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
