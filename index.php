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

$id = required_param('id', PARAM_INT); // Course Module ID.

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('error:coursemisconfigured', 'bookingform');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/bookingform:view', $context);

// Logging and events trigger.
$params = array(
    'context'  => $context,
    'objectid' => $course->id
);
$event = \mod_bookingform\event\course_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strbookingforms = get_string('modulenameplural', 'bookingform');
$strbookingform = get_string('modulename', 'bookingform');
$strbookingformname = get_string('bookingformname', 'bookingform');
$strweek = get_string('week');
$strtopic = get_string('topic');
$strcourse = get_string('course');
$strname = get_string('name');

$pagetitle = format_string($strbookingforms);

$PAGE->set_url('/mod/bookingform/index.php', array('id' => $id));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!$bookingforms = get_all_instances_in_course('bookingform', $course)) {
    notice(get_string('nobookingforms', 'bookingform'), "../../course/view.php?id=$course->id");
    die;
}

$timenow = time();

$table = new html_table();
$table->width = '100%';

if ($course->format == 'weeks' && has_capability('mod/bookingform:viewattendees', $context)) {
    $table->head  = array ($strweek, $strbookingformname, get_string('sign-ups', 'bookingform'));
    $table->align = array ('center', 'left', 'center');
} else if ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strbookingformname);
    $table->align = array ('center', 'left', 'center', 'center');
} else if ($course->format == 'topics' && has_capability('mod/bookingform:viewattendees', $context)) {
    $table->head  = array ($strcourse, $strbookingformname, get_string('sign-ups', 'bookingform'));
    $table->align = array ('center', 'left', 'center');
} else if ($course->format == 'topics') {
    $table->head  = array ($strcourse, $strbookingformname);
    $table->align = array ('center', 'left', 'center', 'center');
} else {
    $table->head  = array ($strbookingformname);
    $table->align = array ('left', 'left');
}

$currentsection = '';

foreach ($bookingforms as $bookingform) {

    $submitted = get_string('no');

    if (!$bookingform->visible) {
        // Show dimmed if the mod is hidden.
        $link = html_writer::link("view.php?f=$bookingform->id", $bookingform->name, array('class' => 'dimmed'));
    } else {
        // Show normal if the mod is visible.
        $link = html_writer::link("view.php?f=$bookingform->id", $bookingform->name);
    }

    $printsection = '';
    if ($bookingform->section !== $currentsection) {
        if ($bookingform->section) {
            $printsection = $bookingform->section;
        }
        $currentsection = $bookingform->section;
    }

    $totalsignupcount = 0;
    if ($sessions = bookingform_get_sessions($bookingform->id)) {
        foreach ($sessions as $session) {
            if (!bookingform_has_session_started($session, $timenow)) {
                $signupcount = bookingform_get_num_attendees($session->id);
                $totalsignupcount += $signupcount;
            }
        }
    }
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $courselink = html_writer::link($url, $course->shortname, array('title' => $course->shortname));
    if ($course->format == 'weeks' or $course->format == 'topics') {
        if (has_capability('mod/bookingform:viewattendees', $context)) {
            $table->data[] = array ($courselink, $link, $totalsignupcount);
        } else {
            $table->data[] = array ($courselink, $link);
        }
    } else {
        $table->data[] = array ($link, $submitted);
    }
}

echo html_writer::empty_tag('br');

echo html_writer::table($table);
echo $OUTPUT->footer($course);
