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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/grouplib.php');

/*
 * Definitions for setting notification types.
 */

// Utility definitions.
define('BKF_MDL_F2F_ICAL',   1);
define('BKF_MDL_F2F_TEXT',   2);
define('BKF_MDL_F2F_BOTH',   3);
define('BKF_MDL_F2F_INVITE', 4);
define('BKF_MDL_F2F_CANCEL', 8);

// Definitions for use in forms.
define('BKF_BKF_MDL_F2F_INVITE_BOTH', 7);     // Send a copy of both 4+1+2.
define('BKF_BKF_MDL_F2F_INVITE_TEXT', 6);     // Send just a plain email 4+2.
define('BKF_BKF_MDL_F2F_INVITE_ICAL', 5);     // Send just a combined text/ical message 4+1.
define('BKF_BKF_MDL_F2F_CANCEL_BOTH', 11);    // Send a copy of both 8+2+1.
define('BKF_BKF_MDL_F2F_CANCEL_TEXT', 10);    // Send just a plan email 8+2.
define('BKF_BKF_MDL_F2F_CANCEL_ICAL', 9);     // Send just a combined text/ical message 8+1.

// Name of the custom field where the manager's email address is stored.
define('BKF_MDL_MANAGERSEMAIL_FIELD', 'managersemail');

// Custom field related constants.
define('BKF_CUSTOMFIELD_DELIMITER', '##SEPARATOR##');
define('BKF_CUSTOMFIELD_TYPE_TEXT',        0);
define('BKF_CUSTOMFIELD_TYPE_SELECT',      1);
define('BKF_CUSTOMFIELD_TYPE_MULTISELECT', 2);

// Calendar-related constants.
define('BKF_CALENDAR_MAX_NAME_LENGTH', 15);
define('BKF_F2F_CAL_NONE',   0);
define('BKF_F2F_CAL_COURSE', 1);
define('BKF_F2F_CAL_SITE',   2);
define('BKF_F2F_CAL_GROUP',  3);

// Signup status codes (remember to update bookingform_statuses()).
define('BKF_MDL_F2F_STATUS_USER_CANCELLED', 10);

// SESSION_CANCELLED is not yet implemented.
define('BKF_MDL_F2F_STATUS_SESSION_CANCELLED',  20);
define('BKF_MDL_F2F_STATUS_DECLINED',           30);
define('BKF_MDL_F2F_STATUS_REQUESTED',          40);
define('BKF_MDL_F2F_STATUS_APPROVED',           50);
define('BKF_MDL_F2F_STATUS_WAITLISTED',         60);
define('BKF_MDL_F2F_STATUS_BOOKED',             70);
define('BKF_MDL_F2F_STATUS_NO_SHOW',            80);
define('BKF_MDL_F2F_STATUS_PARTIALLY_ATTENDED', 90);
define('BKF_MDL_F2F_STATUS_FULLY_ATTENDED',     100);

/**
 * Returns the list of possible bookingform status.
 *
 * @param int $statuscode One of the MDL_F2F_STATUS* constants
 * @return string $string Human readable code
 */
function bookingform_statuses() {
    // This array must match the status codes above, and the values
    // must equal the end of the constant name but in lower case.

    return array(
        BKF_MDL_F2F_STATUS_USER_CANCELLED      => 'user_cancelled',
        // BKF_MDL_F2F_STATUS_SESSION_CANCELLED   => 'session_cancelled', // Not yet implemented.
        BKF_MDL_F2F_STATUS_DECLINED            => 'declined',
        BKF_MDL_F2F_STATUS_REQUESTED           => 'requested',
        BKF_MDL_F2F_STATUS_APPROVED            => 'approved',
        BKF_MDL_F2F_STATUS_WAITLISTED          => 'waitlisted',
        BKF_MDL_F2F_STATUS_BOOKED              => 'booked',
        BKF_MDL_F2F_STATUS_NO_SHOW             => 'no_show',
        BKF_MDL_F2F_STATUS_PARTIALLY_ATTENDED  => 'partially_attended',
        BKF_MDL_F2F_STATUS_FULLY_ATTENDED      => 'fully_attended',
    );
}

/**
 * Returns the human readable code for a face-to-face status
 *
 * @param int $statuscode One of the MDL_F2F_STATUS* constants
 * @return string $string Human readable code
 */
function bookingform_get_status($statuscode) {
    $statuses = bookingform_statuses();

    // Check code exists.
    if (!isset($statuses[$statuscode])) {
        print_error('F2F status code does not exist: ' . $statuscode);
    }

    // Get code.
    $string = $statuses[$statuscode];

    // Check to make sure the status array looks to be up-to-date.
    if (constant('BKF_MDL_F2F_STATUS_' . strtoupper($string)) != $statuscode) {
        print_error('F2F status code array does not appear to be up-to-date: ' . $statuscode);
    }

    return $string;
}

/**
 * Prints the cost amount along with the appropriate currency symbol.
 *
 * To set your currency symbol, set the appropriate 'locale' in
 * lang/en_utf8/langconfig.php (or the equivalent file for your
 * language).
 *
 * @param int  $amount     Numerical amount without currency symbol
 * @param bool $htmloutput Whether the output is in HTML or not
 */
function bookingform_format_cost($amount, $htmloutput=true) {
    setlocale(LC_MONETARY, get_string('locale', 'langconfig'));
    $localeinfo = localeconv();

    $symbol = $localeinfo['currency_symbol'];
    if (empty($symbol)) {

        // Cannot get the locale information, default to en_US.UTF-8.
        return '$' . $amount;
    }

    // Character between the currency symbol and the amount.
    $separator = '';
    if ($localeinfo['p_sep_by_space']) {
        $separator = $htmloutput ? '&nbsp;' : ' ';
    }

    // The symbol can come before or after the amount.
    if ($localeinfo['p_cs_precedes']) {
        return $symbol . $separator . $amount;
    } else {
        return $amount . $separator . $symbol;
    }
}

/**
 * Returns the effective cost of a session depending on the presence
 * or absence of a discount code.
 *
 * @param class $sessiondata contains the discountcost and normalcost
 */
function bookingform_cost($userid, $sessionid, $sessiondata, $htmloutput=true) {
    global $CFG, $DB;

    $count = $DB->count_records_sql("SELECT COUNT(*)
                               FROM {bookingform_signups} su,
                                    {bookingform_sessions} se
                              WHERE su.sessionid = ?
                                AND su.userid = ?
                                AND su.discountcode IS NOT NULL
                                AND su.sessionid = se.id", array($sessionid, $userid));
    if ($count > 0) {
        return bookingform_format_cost($sessiondata->discountcost, $htmloutput);
    } else {
        return bookingform_format_cost($sessiondata->normalcost, $htmloutput);
    }
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param  int $duration duration in hours
 * @return string
 */
function bookingform_format_duration($duration) {
    $components = explode(':', $duration);

    // Default response.
    $string = '';

    // Check for bad characters.
    if (trim(preg_match('/[^0-9:\.\s]/', $duration))) {
        return $string;
    }

    if ($components and count($components) > 1) {

        // E.g. "1:30" => "1 hour and 30 minutes".
        $hours = round($components[0]);
        $minutes = round($components[1]);
    } else {

        // E.g. "1.5" => "1 hour and 30 minutes".
        $hours = floor($duration);
        $minutes = round(($duration - floor($duration)) * 60);
    }

    // Check if either minutes is out of bounds.
    if ($minutes >= 60) {
        return $string;
    }

    if (1 == $hours) {
        $string = get_string('onehour', 'bookingform');
    } else if ($hours > 1) {
        $string = get_string('xhours', 'bookingform', $hours);
    }

    // Insert separator between hours and minutes.
    if ($string != '') {
        $string .= ' ';
    }

    if (1 == $minutes) {
        $string .= get_string('oneminute', 'bookingform');
    } else if ($minutes > 0) {
        $string .= get_string('xminutes', 'bookingform', $minutes);
    }

    return $string;
}

/**
 * Converts minutes to hours
 */
function bookingform_minutes_to_hours($minutes) {
    if (!intval($minutes)) {
        return 0;
    }

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    } else {
        return $minutes;
    }
}

/**
 * Converts hours to minutes
 */
function bookingform_hours_to_minutes($hours) {
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {

        // E.g. "1:45" => 105 minutes.
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    } else {
        // E.g. "1.75" => 105 minutes.
        return round($hours * 60.0);
    }
}

/**
 * Turn undefined manager messages into empty strings and deal with checkboxes
 */
function bookingform_fix_settings($bookingform) {

    if (empty($bookingform->emailmanagerconfirmation)) {
        $bookingform->confirmationinstrmngr = null;
    }
    if (empty($bookingform->emailmanagerreminder)) {
        $bookingform->reminderinstrmngr = null;
    }
    if (empty($bookingform->emailmanagercancellation)) {
        $bookingform->cancellationinstrmngr = null;
    }
    if (empty($bookingform->usercalentry)) {
        $bookingform->usercalentry = 0;
    }
    if (empty($bookingform->thirdpartywaitlist)) {
        $bookingform->thirdpartywaitlist = 0;
    }
    if (empty($bookingform->approvalreqd)) {
        $bookingform->approvalreqd = 0;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will create a new instance and
 * return the id number of the new instance.
 */
function bookingform_add_instance($bookingform) {
    global $DB, $USER;

    $bookingform->timemodified = time();
    bookingform_fix_settings($bookingform);
    if ($bookingform->id = $DB->insert_record('bookingform', $bookingform)) {
        bookingform_grade_item_update($bookingform);
    }

    // Update any calendar entries.
    if ($sessions = bookingform_get_sessions($bookingform->id)) {
        foreach ($sessions as $session) {
            bookingform_update_calendar_entries($session, $bookingform);
        }
    }

    return $bookingform->id;
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will update an existing instance
 * with new data.
 */
function bookingform_update_instance($bookingform, $instanceflag = true) {
    global $DB;

    if ($instanceflag) {
        $bookingform->id = $bookingform->instance;
    }

    bookingform_fix_settings($bookingform);
    if ($return = $DB->update_record('bookingform', $bookingform)) {
        bookingform_grade_item_update($bookingform);

        // Update any calendar entries.
        if ($sessions = bookingform_get_sessions($bookingform->id)) {
            foreach ($sessions as $session) {
                bookingform_update_calendar_entries($session, $bookingform);
            }
        }
    }

    return $return;
}

/**
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 */
function bookingform_delete_instance($id) {
    global $CFG, $DB;

    if (!$bookingform = $DB->get_record('bookingform', array('id' => $id))) {
        return false;
    }

    $result = true;
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records_select(
        'bookingform_signups_status',
        "signupid IN
        (
            SELECT
            id
            FROM
    {bookingform_signups}
    WHERE
    sessionid IN
    (
        SELECT
        id
        FROM
    {bookingform_sessions}
    WHERE
    bookingform = ? ))
    ", array($bookingform->id));

    $DB->delete_records_select('bookingform_signups', "sessionid IN (SELECT id FROM {bookingform_sessions} WHERE bookingform = ?)", array($bookingform->id));
    $DB->delete_records_select('bookingform_sessions_dates', "sessionid in (SELECT id FROM {bookingform_sessions} WHERE bookingform = ?)", array($bookingform->id));
    $DB->delete_records('bookingform_sessions', array('bookingform' => $bookingform->id));
    $DB->delete_records('bookingform', array('id' => $bookingform->id));
    $DB->delete_records('event', array('modulename' => 'bookingform', 'instance' => $bookingform->id)); // Course events.
    $DB->delete_records('event', array('modulename' => '0', 'eventtype' => 'bookingformsession', 'instance' => $bookingform->id)); // User events and Site events.
    bookingform_grade_item_delete($bookingform);
    $transaction->allow_commit();

    return $result;
}

/**
 * Prepare the user data to go into the database.
 */
function bookingform_cleanup_session_data($session) {

    // Convert hours (expressed like "1.75" or "2" or "3.5") to minutes.
    $session->duration = bookingform_hours_to_minutes($session->duration);

    // Only numbers allowed here.
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $maxcap = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    } else if ($session->capacity > $maxcap) {
        $session->capacity = $maxcap;
    }

    // Get the decimal point separator.
    setlocale(LC_MONETARY, get_string('locale', 'langconfig'));
    $localeinfo = localeconv();
    $symbol = $localeinfo['decimal_point'];
    if (empty($symbol)) {

        // Cannot get the locale information, default to en_US.UTF-8.
        $symbol = '.';
    }

    // Only numbers or decimal separators allowed here.
    $session->normalcost = round(preg_replace("/[^\d$symbol]/", '', $session->normalcost));
    $session->discountcost = round(preg_replace("/[^\d$symbol]/", '', $session->discountcost));

    return $session;
}

/**
 * Create a new entry in the bookingform_sessions table
 */
function bookingform_add_session($session, $sessiondates) {
    global $USER, $DB;

    $session->timecreated = time();
    $session = bookingform_cleanup_session_data($session);

    $eventname = $DB->get_field('bookingform', 'name,id', array('id' => $session->bookingform));
    $session->id = $DB->insert_record('bookingform_sessions', $session);

    if (empty($sessiondates)) {

        // Insert a dummy date record.
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;

        $DB->insert_record('bookingform_sessions_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('bookingform_sessions_dates', $date);
        }
    }

    // Create any calendar entries.
    $session->sessiondates = $sessiondates;
    bookingform_update_calendar_entries($session);

    return $session->id;
}

/**
 * Modify an entry in the bookingform_sessions table
 */
function bookingform_update_session($session, $sessiondates) {
    global $DB;

    $session->timemodified = time();
    $session = bookingform_cleanup_session_data($session);

    $transaction = $DB->start_delegated_transaction();
    $DB->update_record('bookingform_sessions', $session);
    $DB->delete_records('bookingform_sessions_dates', array('sessionid' => $session->id));

    if (empty($sessiondates)) {

        // Insert a dummy date record.
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        $DB->insert_record('bookingform_sessions_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('bookingform_sessions_dates', $date);
        }
    }

    // Update any calendar entries.
    $session->sessiondates = $sessiondates;
    bookingform_update_calendar_entries($session);
    $transaction->allow_commit();

    return bookingform_update_attendees($session);
}

/**
 * Update calendar entries for a given session
 *
 * @param int $session ID of session to update event for
 * @param int $bookingform ID of bookingform activity (optional)
 */
function bookingform_update_calendar_entries($session, $bookingform=null) {
    global $USER, $DB;

    if (empty($bookingform)) {
        $bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform));
    }

    // Remove from all calendars.
    bookingform_delete_user_calendar_events($session, 'booking');
    bookingform_delete_user_calendar_events($session, 'session');
    bookingform_remove_session_from_calendar($session, 0); // Session user event for session creator.
    bookingform_remove_session_from_calendar($session, $bookingform->course); // Session course event.
    bookingform_remove_session_from_calendar($session, SITEID); // Session site event.

    if (empty($bookingform->showoncalendar) && empty($bookingform->usercalentry)) {
        return true;
    }

    // Add to NEW calendartype.
    if ($bookingform->usercalentry) {

        // Get ALL enrolled/booked users.
        $users = bookingform_get_attendees($session->id);
        // If session creator is not enrolled in the course, add the session to his/her events user calendar.
        if (!in_array($USER->id, $users)) {
            bookingform_add_session_to_calendar($session, $bookingform, 'user', $USER->id, 'session');
        }

        foreach ($users as $user) {
            $eventtype = $user->statuscode == BKF_MDL_F2F_STATUS_BOOKED ? 'booking' : 'session';
            bookingform_add_session_to_calendar($session, $bookingform, 'user', $user->id, $eventtype);
        }
    }

    if ($bookingform->showoncalendar == BKF_F2F_CAL_COURSE) {

        /**
         * We can't allow the event to be added at the course level, because it
         * would display for all students enrolled. We need to display to those
         * belonging to the teacher group.
         *
         * @author   Andres Ramos <andres.ramos@lmsdoctor.com>
         */

        $mygroupid = bookingform_get_my_groupid($bookingform->course, $USER->id);
        if (!empty($mygroupid)) {
            $bookingform->groupid = $mygroupid->id;
            bookingform_add_session_to_calendar($session, $bookingform, 'group', $USER->id);
        } else {
            bookingform_add_session_to_calendar($session, $bookingform, 'course', $USER->id);
        }

    } else if ($bookingform->showoncalendar == BKF_F2F_CAL_SITE) {
        bookingform_add_session_to_calendar($session, $bookingform, 'site', $USER->id);
    }

    return true;
}

/**
 * Update attendee list status' on booking size change
 */
function bookingform_update_attendees($session) {
    global $USER, $DB;

    // Get bookingform.
    $bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform));

    // Get course.
    $course = $DB->get_record('course', array('id' => $bookingform->course));

    // Update user status'.
    $users = bookingform_get_attendees($session->id);

    if ($users) {

        // No/deleted session dates.
        if (empty($session->datetimeknown)) {

            // Convert any bookings to waitlists.
            foreach ($users as $user) {
                if ($user->statuscode == BKF_MDL_F2F_STATUS_BOOKED) {

                    if (!bookingform_user_signup($session, $bookingform, $course, $user->discountcode, $user->notificationtype, BKF_MDL_F2F_STATUS_WAITLISTED, $user->id)) {
                        return false;
                    }
                }
            }
        } else {

            // Session dates exist.
            // Convert earliest signed up users to booked, and make the rest waitlisted.
            $capacity = $session->capacity;

            // Count number of booked users.
            $booked = 0;
            foreach ($users as $user) {
                if ($user->statuscode == BKF_MDL_F2F_STATUS_BOOKED) {
                    $booked++;
                }
            }

            // If booked less than capacity, book some new users.
            if ($booked < $capacity) {
                foreach ($users as $user) {
                    if ($booked >= $capacity) {
                        break;
                    }

                    if ($user->statuscode == BKF_MDL_F2F_STATUS_WAITLISTED) {

                        if (!bookingform_user_signup($session, $bookingform, $course, $user->discountcode, $user->notificationtype, BKF_MDL_F2F_STATUS_BOOKED, $user->id)) {
                            return false;
                        }
                        $booked++;
                    }
                }
            }
        }
    }

    return $session->id;
}

/**
 * Return an array of all bookingform activities in the current course
 */
function bookingform_get_bookingform_menu() {
    global $CFG, $DB;

    if ($bookingforms = $DB->get_records_sql("SELECT f.id, c.shortname, f.name
                                            FROM {course} c, {bookingform} f
                                            WHERE c.id = f.course
                                            ORDER BY c.shortname, f.name")) {
        $i = 1;
        foreach ($bookingforms as $bookingform) {
            $f = $bookingform->id;
            $bookingformmenu[$f] = $bookingform->shortname . ' --- ' . $bookingform->name;
            $i++;
        }

        return $bookingformmenu;

    } else {
        return '';
    }
}

/**
 * Delete entry from the bookingform_sessions table along with all
 * related details in other tables
 *
 * @param object $session Record from bookingform_sessions
 */
function bookingform_delete_session($session) {
    global $CFG, $DB;

    $bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform));

    // Cancel user signups (and notify users).
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {bookingform_signups} s
            LEFT JOIN
                {bookingform_signups_status} ss
             ON ss.signupid = s.id
            WHERE
                s.sessionid = ?
            AND ss.superceded = 0
            AND ss.statuscode >= ?
        ", array($session->id, BKF_MDL_F2F_STATUS_REQUESTED));

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (bookingform_user_cancel($session, $user->userid, true)) {
                bookingform_send_cancellation_notice($bookingform, $session, $user->userid);
            } else {
                return false; // Cannot rollback since we notified users already.
            }
        }
    }

    $transaction = $DB->start_delegated_transaction();

    // Remove entries from user calendars.
    $DB->delete_records_select('event', "modulename = '0' AND
                                         eventtype like 'bookingform%' AND
                                         courseid = 0 AND instance = ?",
                                         array($bookingform->id));

    // Remove entry from course calendar.
    bookingform_remove_session_from_calendar($session, $bookingform->course);

    // Remove entry from site-wide calendar.
    bookingform_remove_session_from_calendar($session, SITEID);

    // Delete session details.
    $DB->delete_records('bookingform_sessions', array('id' => $session->id));
    $DB->delete_records('bookingform_sessions_dates', array('sessionid' => $session->id));
    $DB->delete_records_select(
        'bookingform_signups_status',
        "signupid IN
        (
            SELECT
                id
            FROM
                {bookingform_signups}
            WHERE
                sessionid = {$session->id}
        )
        ");
    $DB->delete_records('bookingform_signups', array('sessionid' => $session->id));
    $transaction->allow_commit();

    return true;
}

/**
 * Substitute the placeholders in email templates for the actual data
 *
 * Expects the following parameters in the $data object:
 * - datetimeknown
 * - details
 * - discountcost
 * - duration
 * - normalcost
 * - sessiondates
 *
 * @access  public
 * @param   string  $msg            Email message
 * @param   string  $bookingformname F2F name
 * @param   int     $reminderperiod Num business days before event to send reminder
 * @param   obj     $user           The subject of the message
 * @param   obj     $data           Session data
 * @param   int     $sessionid      Session ID
 * @return  string
 */
function bookingform_email_substitutions($msg, $bookingformname, $reminderperiod, $user, $data, $sessionid) {
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }

    if ($data->datetimeknown) {

        // Scheduled session.
        $sessiondate = userdate($data->sessiondates[0]->timestart, get_string('strftimedate'));
        $starttime = userdate($data->sessiondates[0]->timestart, get_string('strftimetime'));
        $finishtime = userdate($data->sessiondates[0]->timefinish, get_string('strftimetime'));

        $alldates = '';
        foreach ($data->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $alldates .= userdate($date->timestart, get_string('strftimedate')).', ';
            $alldates .= userdate($date->timestart, get_string('strftimetime')).
                ' to '.userdate($date->timefinish, get_string('strftimetime'));
        }
    } else {

        // Wait-listed session.
        $sessiondate = get_string('unknowndate', 'bookingform');
        $alldates    = get_string('unknowndate', 'bookingform');
        $starttime   = get_string('unknowntime', 'bookingform');
        $finishtime  = get_string('unknowntime', 'bookingform');
    }

    $msg = str_replace(get_string('placeholder:bookingformname', 'bookingform'), $bookingformname, $msg);
    $msg = str_replace(get_string('placeholder:firstname', 'bookingform'), $user->firstname, $msg);
    $msg = str_replace(get_string('placeholder:lastname', 'bookingform'), $user->lastname, $msg);
    $msg = str_replace(get_string('placeholder:cost', 'bookingform'), bookingform_cost($user->id, $sessionid, $data, false), $msg);
    $msg = str_replace(get_string('placeholder:alldates', 'bookingform'), $alldates, $msg);
    $msg = str_replace(get_string('placeholder:sessiondate', 'bookingform'), $sessiondate, $msg);
    $msg = str_replace(get_string('placeholder:starttime', 'bookingform'), $starttime, $msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'bookingform'), $finishtime, $msg);
    $msg = str_replace(get_string('placeholder:duration', 'bookingform'), bookingform_format_duration($data->duration), $msg);
    if (empty($data->details)) {
        $msg = str_replace(get_string('placeholder:details', 'bookingform'), '', $msg);
    } else {
        $msg = str_replace(get_string('placeholder:details', 'bookingform'), html_to_text($data->details), $msg);
    }
    $msg = str_replace(get_string('placeholder:reminderperiod', 'bookingform'), $reminderperiod, $msg);

    // Replace more meta data.
    $msg = str_replace(get_string('placeholder:attendeeslink', 'bookingform'), $CFG->wwwroot . '/mod/bookingform/attendees.php?s=' . $sessionid, $msg);

    // Custom session fields (they look like "session:shortname" in the templates).
    $customfields = bookingform_get_session_customfields();
    $customdata = $DB->get_records('bookingform_session_data', array('sessionid' => $sessionid), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $placeholder = "[session:{$field->shortname}]";
        $value = '';
        if (!empty($customdata[$field->id])) {
            if (BKF_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $value = str_replace(BKF_CUSTOMFIELD_DELIMITER, ', ', $customdata[$field->id]->data);
            } else {
                $value = $customdata[$field->id]->data;
            }
        }

        $msg = str_replace($placeholder, $value, $msg);
    }

    return $msg;
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all bookingform notifications that have yet to be mailed out, and mails them.
 */
function bookingform_cron() {
    global $CFG, $USER, $DB;

    $signupsdata = bookingform_get_unmailed_reminders();
    if (!$signupsdata) {
        echo "\n" . get_string('noremindersneedtobesent', 'bookingform') . "\n";
        return true;
    }

    $timenow = time();
    foreach ($signupsdata as $signupdata) {
        if (bookingform_has_session_started($signupdata, $timenow)) {

            // Too late, the session already started.
            // Mark the reminder as being sent already.
            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = 1; // Magic number to show that it was not actually sent.
            if (!$DB->update_record('bookingform_signups', $newsubmission)) {
                echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
            }
            continue;
        }

        $earlieststarttime = $signupdata->sessiondates[0]->timestart;
        foreach ($signupdata->sessiondates as $date) {
            if ($date->timestart < $earlieststarttime) {
                $earlieststarttime = $date->timestart;
            }
        }

        $reminderperiod = $signupdata->reminderperiod;

        // Convert the period from business days (no weekends) to calendar days.
        for ($reminderday = 0; $reminderday < $reminderperiod + 1; $reminderday++) {
            $reminderdaytime = $earlieststarttime - ($reminderday * 24 * 3600);

            // Use %w instead of %u for Windows compatability.
            $reminderdaycheck = userdate($reminderdaytime, '%w');

            // Note w runs from Sun=0 to Sat=6.
            if ($reminderdaycheck == 0 || $reminderdaycheck == 6) {

                /*
                 * Saturdays and Sundays are not included in the
                 * reminder period as entered by the user, extend
                 * that period by 1
                */
                $reminderperiod++;
            }
        }

        $remindertime = $earlieststarttime - ($reminderperiod * 24 * 3600);
        if ($timenow < $remindertime) {

            // Too early to send reminder.
            continue;
        }

        if (!$user = $DB->get_record('user', array('id' => $signupdata->userid))) {
            continue;
        }

        // Hack to make sure that the timezone and languages are set properly in emails.
        // (i.e. it uses the language and timezone of the recipient of the email).
        $USER->lang = $user->lang;
        $USER->timezone = $user->timezone;
        if (!$course = $DB->get_record('course', array('id' => $signupdata->course))) {
            continue;
        }
        if (!$bookingform = $DB->get_record('bookingform', array('id' => $signupdata->bookingformid))) {
            continue;
        }

        $postsubject = '';
        $posttext = '';
        $posttextmgrheading = '';
        if (empty($signupdata->mailedreminder)) {
            $postsubject = $bookingform->remindersubject;
            $posttext = $bookingform->remindermessage;
            $posttextmgrheading = $bookingform->reminderinstrmngr;
        }

        if (empty($posttext)) {

            // The reminder message is not set, don't send anything.
            continue;
        }

        $postsubject = bookingform_email_substitutions($postsubject, $signupdata->bookingformname, $signupdata->reminderperiod,
                                                      $user, $signupdata, $signupdata->sessionid);
        $posttext = bookingform_email_substitutions($posttext, $signupdata->bookingformname, $signupdata->reminderperiod,
                                                   $user, $signupdata, $signupdata->sessionid);
        $posttextmgrheading = bookingform_email_substitutions($posttextmgrheading, $signupdata->bookingformname, $signupdata->reminderperiod,
                                                             $user, $signupdata, $signupdata->sessionid);

        $posthtml = ''; // FIXME.
        if ($fromaddress = get_config(null, 'bookingform_fromaddress')) {
            $from = new stdClass();
            $from->maildisplay = true;
            $from->email = $fromaddress;
        } else {
            $from = null;
        }

        if (email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            echo "\n" . get_string('sentreminderuser', 'bookingform') . ": $user->firstname $user->lastname $user->email";

            $newsubmission = new stdClass();
            $newsubmission->id = $signupdata->id;
            $newsubmission->mailedreminder = $timenow;
            if (!$DB->update_record('bookingform_signups', $newsubmission)) {
                echo "ERROR: could not update mailedreminder for submission ID $signupdata->id";
            }

            if (empty($posttextmgrheading)) {
                continue; // No manager message set.
            }

            $managertext = $posttextmgrheading.$posttext;
            $manager = $user;
            $manager->email = bookingform_get_manageremail($user->id);

            if (empty($manager->email)) {
                continue; // Don't know who the manager is.
            }

            // Send email to mamager.
            if (email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
                echo "\n".get_string('sentremindermanager', 'bookingform').": $user->firstname $user->lastname $manager->email";
            } else {
                $errormsg = array();
                $errormsg['submissionid'] = $signupdata->id;
                $errormsg['userid'] = $user->id;
                $errormsg['manageremail'] = $manager->email;
                echo get_string('error:cronprefix', 'bookingform').' '.get_string('error:cannotemailmanager', 'bookingform', $errormsg)."\n";
            }
        } else {
            $errormsg = array();
            $errormsg['submissionid'] = $signupdata->id;
            $errormsg['userid'] = $user->id;
            $errormsg['useremail'] = $user->email;
            echo get_string('error:cronprefix', 'bookingform').' '.get_string('error:cannotemailuser', 'bookingform', $errormsg)."\n";
        }
    }

    print "\n";
    return true;
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param class $session record from the bookingform_sessions table
 * @param integer $timenow current time
 */
function bookingform_has_session_started($session, $timenow) {

    if (!$session->datetimeknown) {
        return false; // No date set.
    }

    foreach ($session->sessiondates as $date) {
        if ($date->timestart < $timenow) {
            return true;
        }
    }

    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param class $session record from the bookingform_sessions table
 * @param integer $timenow current time
 */
function bookingform_is_session_in_progress($session, $timenow) {
    if (!$session->datetimeknown) {
        return false;
    }
    foreach ($session->sessiondates as $date) {
        if ($date->timefinish > $timenow && $date->timestart < $timenow) {
            return true;
        }
    }

    return false;
}

/**
 * Get all of the dates for a given session
 */
function bookingform_get_session_dates($sessionid) {
    global $DB;

    $ret = array();
    if ($dates = $DB->get_records('bookingform_sessions_dates', array('sessionid' => $sessionid), 'timestart')) {
        $i = 0;
        foreach ($dates as $date) {
            $ret[$i++] = $date;
        }
    }

    return $ret;
}

/**
 * Get a record from the bookingform_sessions table
 *
 * @param integer $sessionid ID of the session
 */
function bookingform_get_session($sessionid) {
    global $DB;

    $session = $DB->get_record('bookingform_sessions', array('id' => $sessionid));
    if ($session) {
        $session->sessiondates = bookingform_get_session_dates($sessionid);
        $session->duration = bookingform_minutes_to_hours($session->duration);
    }

    return $session;
}

/**
 * Get all records from bookingform_sessions for a given bookingform activity and location
 *
 * @param integer $bookingformid ID of the activity
 * @param string $location location filter (optional)
 */
function bookingform_get_sessions($bookingformid, $location='') {
    global $CFG, $DB;

    $fromclause = "FROM {bookingform_sessions} s";
    $locationwhere = '';
    $locationparams = array();
    if (!empty($location)) {
        $fromclause = "FROM {bookingform_session_data} d
                       JOIN {bookingform_sessions} s ON s.id = d.sessionid";
        $locationwhere .= " AND d.data = ?";
        $locationparams[] = $location;
    }
    $sessions = $DB->get_records_sql("SELECT s.*
                                   $fromclause
                        LEFT OUTER JOIN (SELECT sessionid, min(timestart) AS mintimestart
                                           FROM {bookingform_sessions_dates} GROUP BY sessionid) m ON m.sessionid = s.id
                                  WHERE s.bookingform = ?
                                        $locationwhere
                               ORDER BY s.datetimeknown, m.mintimestart", array_merge(array($bookingformid), $locationparams));

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            $sessions[$key]->duration = bookingform_minutes_to_hours($sessions[$key]->duration);
            $sessions[$key]->sessiondates = bookingform_get_session_dates($value->id);
        }
    }

    return $sessions;
}

/**
 * Get a grade for the given user from the gradebook.
 *
 * @param integer $userid       ID of the user
 * @param integer $courseid     ID of the course
 * @param integer $bookingformid ID of the Face-to-face activity
 *
 * @returns object String grade and the time that it was graded
 */
function bookingform_get_grade($userid, $courseid, $bookingformid) {

    $ret = new stdClass();
    $ret->grade = 0;
    $ret->dategraded = 0;

    $gradinginfo = grade_get_grades($courseid, 'mod', 'bookingform', $bookingformid, $userid);
    if (!empty($gradinginfo->items)) {
        $ret->grade = $gradinginfo->items[0]->grades[$userid]->str_grade;
        $ret->dategraded = $gradinginfo->items[0]->grades[$userid]->dategraded;
    }

    return $ret;
}

/**
 * Get list of users attending a given session
 *
 * @access public
 * @param integer Session ID
 * @return array
 */
function bookingform_get_attendees($sessionid) {
    global $CFG, $DB;

    $usernamefields = get_all_user_name_fields(true, 'u');
    $records = $DB->get_records_sql("
        SELECT u.id, {$usernamefields},
            u.email,
            su.id AS submissionid,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS bookingformid,
            f.course,
            ss.grade,
            ss.statuscode,
            sign.timecreated
        FROM
            {bookingform} f
        JOIN
            {bookingform_sessions} s
         ON s.bookingform = f.id
        JOIN
            {bookingform_signups} su
         ON s.id = su.sessionid
        JOIN
            {bookingform_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {bookingform_signups_status} ss
            INNER JOIN
                {bookingform_signups} s
             ON s.id = ss.signupid
            AND s.sessionid = ?
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            sign.timecreated ASC,
            ss.timecreated ASC
    ", array ($sessionid, BKF_MDL_F2F_STATUS_BOOKED, BKF_MDL_F2F_STATUS_WAITLISTED, $sessionid, BKF_MDL_F2F_STATUS_APPROVED));

    return $records;
}

/**
 * Get a single attendee of a session
 *
 * @access public
 * @param integer Session ID
 * @param integer User ID
 * @return false|object
 */
function bookingform_get_attendee($sessionid, $userid) {
    global $CFG, $DB;

    $record = $DB->get_record_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            u.firstname,
            u.lastname,
            u.email,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS bookingformid,
            f.course,
            ss.grade,
            ss.statuscode
        FROM
            {bookingform} f
        JOIN
            {bookingform_sessions} s
         ON s.bookingform = f.id
        JOIN
            {bookingform_signups} su
         ON s.id = su.sessionid
        JOIN
            {bookingform_signups_status} ss
         ON su.id = ss.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND u.id = ?
    ", array($sessionid, $userid));

    if (!$record) {
        return false;
    }

    return $record;
}

/**
 * Return all user fields to include in exports
 */
function bookingform_get_userfields() {
    global $CFG;

    static $userfields = null;
    if (null == $userfields) {
        $userfields = array();

        if (function_exists('grade_export_user_fields')) {
            $fieldnames = grade_export_user_fields();
            foreach ($fieldnames as $key => $obj) {
                $userfields[$obj->shortname] = $obj->fullname;
            }
        } else {
            // Set default fields if the grade export patch is not detected (see MDL-17346).
            $fieldnames = array('firstname', 'lastname', 'email', 'city',
                                'idnumber', 'institution', 'department', 'address');
            foreach ($fieldnames as $shortname) {
                $userfields[$shortname] = get_string($shortname);
            }
            $userfields['managersemail'] = get_string('manageremail', 'bookingform');
        }
    }

    return $userfields;
}

/**
 * Download the list of users attending at least one of the sessions
 * for a given bookingform activity
 */
function bookingform_download_attendance($bookingformname, $bookingformid, $location, $format) {
    global $CFG;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate', 'langconfig'));
    $downloadfilename = clean_filename($bookingformname.'_'.userdate($timenow, $timeformat));

    $dateformat = 0;
    if ('ods' === $format) {

        // OpenDocument format (ISO/IEC 26300).
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    } else {

        // Excel format.
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
        $dateformat =& $workbook->add_format();
        $dateformat->set_num_format('d mmm yy'); // TODO: use format specified in language pack.
    }

    $workbook->send($downloadfilename);
    $worksheet =& $workbook->add_worksheet('attendance');
    bookingform_write_worksheet_header($worksheet);
    bookingform_write_activity_attendance($worksheet, 1, $bookingformid, $location, '', '', $dateformat);
    $workbook->close();
    exit;
}

/**
 * Add the appropriate column headers to the given worksheet
 *
 * @param object $worksheet  The worksheet to modify (passed by reference)
 * @returns integer The index of the next column
 */
function bookingform_write_worksheet_header(&$worksheet) {
    $pos = 0;
    $customfields = bookingform_get_session_customfields();
    foreach ($customfields as $field) {
        if (!empty($field->showinsummary)) {
            $worksheet->write_string(0, $pos++, $field->name);
        }
    }
    $worksheet->write_string(0, $pos++, get_string('date', 'bookingform'));
    $worksheet->write_string(0, $pos++, get_string('timestart', 'bookingform'));
    $worksheet->write_string(0, $pos++, get_string('timefinish', 'bookingform'));
    $worksheet->write_string(0, $pos++, get_string('duration', 'bookingform'));
    $worksheet->write_string(0, $pos++, get_string('status', 'bookingform'));

    if ($trainerroles = bookingform_get_trainer_roles()) {
        foreach ($trainerroles as $role) {
            $worksheet->write_string(0, $pos++, get_string('role').': '.$role->name);
        }
    }

    $userfields = bookingform_get_userfields();
    foreach ($userfields as $shortname => $fullname) {
        $worksheet->write_string(0, $pos++, $fullname);
    }

    $worksheet->write_string(0, $pos++, get_string('attendance', 'bookingform'));
    $worksheet->write_string(0, $pos++, get_string('datesignedup', 'bookingform'));

    return $pos;
}

/**
 * Write in the worksheet the given bookingform attendance information
 * filtered by location.
 *
 * This function includes lots of custom SQL because it's otherwise
 * way too slow.
 *
 * @param object  $worksheet    Currently open worksheet
 * @param integer $startingrow  Index of the starting row (usually 1)
 * @param integer $bookingformid ID of the bookingform activity
 * @param string  $location     Location to filter by
 * @param string  $coursename   Name of the course (optional)
 * @param string  $activityname Name of the bookingform activity (optional)
 * @param object  $dateformat   Use to write out dates in the spreadsheet
 * @returns integer Index of the last row written
 */
function bookingform_write_activity_attendance(&$worksheet, $startingrow, $bookingformid, $location,
                                              $coursename, $activityname, $dateformat) {
    global $CFG, $DB;

    $trainerroles = bookingform_get_trainer_roles();
    $userfields = bookingform_get_userfields();
    $customsessionfields = bookingform_get_session_customfields();
    $timenow = time();
    $i = $startingrow;

    $locationcondition = '';
    $locationparam = array();
    if (!empty($location)) {
        $locationcondition = "AND s.location = ?";
        $locationparam = array($location);
    }

    // Fast version of "bookingform_get_attendees()" for all sessions.
    $sessionsignups = array();
    $signups = $DB->get_records_sql("
        SELECT
            su.id AS submissionid,
            s.id AS sessionid,
            u.*,
            f.course AS courseid,
            ss.grade,
            sign.timecreated
        FROM
            {bookingform} f
        JOIN
            {bookingform_sessions} s
         ON s.bookingform = f.id
        JOIN
            {bookingform_signups} su
         ON s.id = su.sessionid
        JOIN
            {bookingform_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {bookingform_signups_status} ss
            INNER JOIN
                {bookingform_signups} s
             ON s.id = ss.signupid
            INNER JOIN
                {bookingform_sessions} se
             ON s.sessionid = se.id
            AND se.bookingform = $bookingformid
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            f.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            s.id, u.firstname, u.lastname
    ", array(BKF_MDL_F2F_STATUS_BOOKED, BKF_MDL_F2F_STATUS_WAITLISTED, $bookingformid, BKF_MDL_F2F_STATUS_APPROVED));

    if ($signups) {

        // Get all grades at once.
        $userids = array();
        foreach ($signups as $signup) {
            if ($signup->id > 0) {
                $userids[] = $signup->id;
            }
        }
        $gradinginfo = grade_get_grades(reset($signups)->courseid, 'mod', 'bookingform',
                                         $bookingformid, $userids);

        foreach ($signups as $signup) {
            $userid = $signup->id;
            if ($customuserfields = bookingform_get_user_customfields($userid, $userfields)) {
                foreach ($customuserfields as $fieldname => $value) {
                    if (!isset($signup->$fieldname)) {
                        $signup->$fieldname = $value;
                    }
                }
            }

            // Set grade.
            if (!empty($gradinginfo->items) and !empty($gradinginfo->items[0]->grades[$userid])) {
                $signup->grade = $gradinginfo->items[0]->grades[$userid]->str_grade;
            }

            $sessionsignups[$signup->sessionid][$signup->id] = $signup;
        }
    }

    // Fast version of "bookingform_get_sessions($bookingformid, $location)".
    $sql = "SELECT d.id as dateid, s.id, s.datetimeknown, s.capacity,
                   s.duration, d.timestart, d.timefinish
              FROM {bookingform_sessions} s
              JOIN {bookingform_sessions_dates} d ON s.id = d.sessionid
              WHERE
                s.bookingform = ?
              AND d.sessionid = s.id
                   $locationcondition
                   ORDER BY s.datetimeknown, d.timestart";

    $sessions = $DB->get_records_sql($sql, array_merge(array($bookingformid), $locationparam));

    $i = $i - 1; // Will be incremented BEFORE each row is written.
    foreach ($sessions as $session) {
        $customdata = $DB->get_records('bookingform_session_data', array('sessionid' => $session->id), '', 'fieldid, data');

        $sessiondate = false;
        $starttime   = get_string('wait-listed', 'bookingform');
        $finishtime  = get_string('wait-listed', 'bookingform');
        $status      = get_string('wait-listed', 'bookingform');

        $sessiontrainers = bookingform_get_trainers($session->id);

        if ($session->datetimeknown) {

            // Display only the first date.
            if (method_exists($worksheet, 'write_date')) {

                // Needs the patch in MDL-20781.
                $sessiondate = (int)$session->timestart;
            } else {
                $sessiondate = userdate($session->timestart, get_string('strftimedate', 'langconfig'));
            }
            $starttime   = userdate($session->timestart, get_string('strftimetime', 'langconfig'));
            $finishtime  = userdate($session->timefinish, get_string('strftimetime', 'langconfig'));

            if ($session->timestart < $timenow) {
                $status = get_string('sessionover', 'bookingform');
            } else {
                $signupcount = 0;
                if (!empty($sessionsignups[$session->id])) {
                    $signupcount = count($sessionsignups[$session->id]);
                }

                if ($signupcount >= $session->capacity) {
                    $status = get_string('bookingfull', 'bookingform');
                } else {
                    $status = get_string('bookingopen', 'bookingform');
                }
            }
        }

        if (!empty($sessionsignups[$session->id])) {
            foreach ($sessionsignups[$session->id] as $attendee) {
                $i++;
                $j = bookingform_write_activity_attendance_helper($worksheet, $i, $session, $customsessionfields, $status, $dateformat, $starttime, $finishtime);
                if ($trainerroles) {
                    foreach (array_keys($trainerroles) as $roleid) {
                        if (!empty($sessiontrainers[$roleid])) {
                            $trainers = array();
                            foreach ($sessiontrainers[$roleid] as $trainer) {
                                $trainers[] = fullname($trainer);
                            }

                            $trainers = implode(', ', $trainers);
                        } else {
                            $trainers = '-';
                        }

                        $worksheet->write_string($i, $j++, $trainers);
                    }
                }

                foreach ($userfields as $shortname => $fullname) {
                    $value = '-';
                    if (!empty($attendee->$shortname)) {
                        $value = $attendee->$shortname;
                    }

                    if ('firstaccess' == $shortname || 'lastaccess' == $shortname ||
                        'lastlogin' == $shortname || 'currentlogin' == $shortname) {

                        if (method_exists($worksheet, 'write_date')) {
                            $worksheet->write_date($i, $j++, (int)$value, $dateformat);
                        } else {
                            $worksheet->write_string($i, $j++, userdate($value, get_string('strftimedate', 'langconfig')));
                        }
                    } else {
                        $worksheet->write_string($i, $j++, $value);
                    }
                }
                $worksheet->write_string($i, $j++, $attendee->grade);

                if (method_exists($worksheet, 'write_date')) {
                    $worksheet->write_date($i, $j++, (int)$attendee->timecreated, $dateformat);
                } else {
                    $signupdate = userdate($attendee->timecreated, get_string('strftimedatetime', 'langconfig'));
                    if (empty($signupdate)) {
                        $signupdate = '-';
                    }
                    $worksheet->write_string($i, $j++, $signupdate);
                }

                if (!empty($coursename)) {
                    $worksheet->write_string($i, $j++, $coursename);
                }
                if (!empty($activityname)) {
                    $worksheet->write_string($i, $j++, $activityname);
                }
            }
        } else {
            // No one is sign-up, so let's just print the basic info.
            $i++;
            // helper
            $j = bookingform_write_activity_attendance_helper($worksheet, $i, $session, $customsessionfields, $status, $dateformat, $starttime, $finishtime);

            foreach ($userfields as $unused) {
                $worksheet->write_string($i, $j++, '-');
            }
            $worksheet->write_string($i, $j++, '-');

            if (!empty($coursename)) {
                $worksheet->write_string($i, $j++, $coursename);
            }
            if (!empty($activityname)) {
                $worksheet->write_string($i, $j++, $activityname);
            }
        }
    }

    return $i;
}

/**
 * Helper function for write_activity_attendance.
 * Could do with further tidying.
 *
 * @param object $worksheet  The worksheet to modify (passed by reference)
 * @param int $i The current row being used.
 * @param object $session
 * @return int The next Column in the sheet.
 */

function bookingform_write_activity_attendance_helper(&$worksheet, $i, $session, $customsessionfields, $status, $dateformat, $starttime, $finishtime) {
    $j = 0;

    // Custom session fields.
    foreach ($customsessionfields as $field) {
        if (empty($field->showinsummary)) {
            continue; // Skip.
        }

        $data = '-';
        if (!empty($customdata[$field->id])) {
            if (BKF_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $data = str_replace(BKF_CUSTOMFIELD_DELIMITER, "\n", $customdata[$field->id]->data);
            } else {
                $data = $customdata[$field->id]->data;
            }
        }
        $worksheet->write_string($i, $j++, $data);
    }

    if (empty($sessiondate)) {
        $worksheet->write_string($i, $j++, $status); // Session date.
    } else {
        if (method_exists($worksheet, 'write_date')) {
            $worksheet->write_date($i, $j++, $sessiondate, $dateformat);
        } else {
            $worksheet->write_string($i, $j++, $sessiondate);
        }
    }
    $worksheet->write_string($i, $j++, $starttime);
    $worksheet->write_string($i, $j++, $finishtime);
    $worksheet->write_number($i, $j++, (int)$session->duration);
    $worksheet->write_string($i, $j++, $status);

    return $j;
}

/**
 * Return an object with all values for a user's custom fields.
 *
 * This is about 15 times faster than the custom field API.
 *
 * @param array $fieldstoinclude Limit the fields returned/cached to these ones (optional)
 */
function bookingform_get_user_customfields($userid, $fieldstoinclude=false) {
    global $CFG, $DB;

    // Cache all lookup.
    static $customfields = null;
    if (null == $customfields) {
        $customfields = array();
    }

    if (!empty($customfields[$userid])) {
        return $customfields[$userid];
    }

    $ret = new stdClass();
    $sql = "SELECT uif.shortname, id.data
              FROM {user_info_field} uif
              JOIN {user_info_data} id ON id.fieldid = uif.id
              WHERE id.userid = ?";

    $customfields = $DB->get_records_sql($sql, array($userid));
    foreach ($customfields as $field) {
        $fieldname = $field->shortname;
        if (false === $fieldstoinclude or !empty($fieldstoinclude[$fieldname])) {
            $ret->$fieldname = $field->data;
        }
    }

    $customfields[$userid] = $ret;
    return $ret;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 */
function bookingform_get_unmailed_reminders() {
    global $CFG, $DB;

    $submissions = $DB->get_records_sql("
        SELECT
            su.*,
            f.course,
            f.id as bookingformid,
            f.name as bookingformname,
            f.reminderperiod,
            se.duration,
            se.normalcost,
            se.discountcost,
            se.details,
            se.datetimeknown
        FROM
            {bookingform_signups} su
        INNER JOIN
            {bookingform_signups_status} sus
         ON su.id = sus.signupid
        AND sus.superceded = 0
        AND sus.statuscode = ?
        JOIN
            {bookingform_sessions} se
         ON su.sessionid = se.id
        JOIN
            {bookingform} f
         ON se.bookingform = f.id
        WHERE
            su.mailedreminder = 0
        AND se.datetimeknown = 1
    ", array(BKF_MDL_F2F_STATUS_BOOKED));

    if ($submissions) {
        foreach ($submissions as $key => $value) {
            $submissions[$key]->duration = bookingform_minutes_to_hours($submissions[$key]->duration);
            $submissions[$key]->sessiondates = bookingform_get_session_dates($value->sessionid);
        }
    }

    return $submissions;
}

/**
 * Add a record to the bookingform submissions table and sends out an
 * email confirmation
 *
 * @param class $session record from the bookingform_sessions table
 * @param class $bookingform record from the bookingform table
 * @param class $course record from the course table
 * @param string $discountcode code entered by the user
 * @param integer $notificationtype type of notifications to send to user
 * @see {{BKF_MDL_F2F_INVITE}}
 * @param integer $statuscode Status code to set
 * @param integer $userid user to signup
 * @param bool $notifyuser whether or not to send an email confirmation
 * @param bool $displayerrors whether or not to return an error page on errors
 */
function bookingform_user_signup($session, $bookingform, $course, $discountcode,
                                $notificationtype, $statuscode, $userid = false,
                                $notifyuser = true) {

    global $CFG, $DB, $COURSE, $USER;

    // Get user ID.
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    $return = false;
    $timenow = time();

    // Check to see if a signup already exists.
    if ($existingsignup = $DB->get_record('bookingform_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {

        // Otherwise, prepare a signup object.
        $usersignup = new stdclass;
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->mailedreminder = 0;
    $usersignup->notificationtype = $notificationtype;

    $usersignup->discountcode = trim(strtoupper($discountcode));
    if (empty($usersignup->discountcode)) {
        $usersignup->discountcode = null;
    }

    // Update/insert the signup record.
    if (!empty($usersignup->id)) {
        $success = $DB->update_record('bookingform_signups', $usersignup);
    } else {
        $usersignup->id = $DB->insert_record('bookingform_signups', $usersignup);
        $success = (bool)$usersignup->id;
    }

    if (!$success) {
        print_error('error:couldnotupdatef2frecord', 'bookingform');
        return false;
    }

    // Work out which status to use.

    // If approval not required.
    if (!$bookingform->approvalreqd) {
        $newstatus = $statuscode;
    } else {

        // If approval required.
        // Get current status (if any).
        $currentstatus = $DB->get_field('bookingform_signups_status', 'statuscode', array('signupid' => $usersignup->id, 'superceded' => 0));

        // If approved, then no problem.
        if ($currentstatus == BKF_MDL_F2F_STATUS_APPROVED) {
            $newstatus = $statuscode;
        } else if ($session->datetimeknown) {

            // Otherwise, send manager request.
            $newstatus = BKF_MDL_F2F_STATUS_REQUESTED;
        } else {
            $newstatus = BKF_MDL_F2F_STATUS_WAITLISTED;
        }
    }

    // Update status.
    if (!bookingform_update_signup_status($usersignup->id, $newstatus, $userid, '', null, $session->id)) {
        print_error('error:f2ffailedupdatestatus', 'bookingform');
        return false;
    }

    // Add to user calendar -- if bookingform usercalentry is set to true.
    if ($bookingform->usercalentry) {
        if (in_array($newstatus, array(BKF_MDL_F2F_STATUS_BOOKED, BKF_MDL_F2F_STATUS_WAITLISTED))) {
            bookingform_add_session_to_calendar($session, $bookingform, 'user', $userid, 'booking');

            /**
             * If the user is a teacher, add the extra description.
             *
             * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
             * @since   02.18.2019
             */

            if (has_capability('mod/bookingform:editsessions', context_course::instance($COURSE->id), $USER)) {
                $user = $DB->get_record('user', array('id' => $userid));
                $bookingform->extradescription = ' Attendee: ' . fullname($user);
                bookingform_add_session_to_calendar($session, $bookingform, 'user', $USER->id, 'booking');
            }
        }
    }

    // Course completion.
    if (in_array($newstatus, array(BKF_MDL_F2F_STATUS_BOOKED, BKF_MDL_F2F_STATUS_WAITLISTED))) {
        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            $ccdetails = array(
                'course' => $course->id,
                'userid' => $userid,
            );

            $cc = new completion_completion($ccdetails);
            $cc->mark_inprogress($timenow);
        }
    }

    // If session has already started, do not send a notification.
    if (bookingform_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification.
    if ($notifyuser) {

        // If booked/waitlisted.
        switch ($newstatus) {
            case BKF_MDL_F2F_STATUS_BOOKED:
                $error = bookingform_send_confirmation_notice($bookingform, $session, $userid, $notificationtype, false);
                break;

            case BKF_MDL_F2F_STATUS_WAITLISTED:
                $error = bookingform_send_confirmation_notice($bookingform, $session, $userid, $notificationtype, true);
                break;

            case BKF_MDL_F2F_STATUS_REQUESTED:
                $error = bookingform_send_request_notice($bookingform, $session, $userid);
                break;
        }

        if (!empty($error)) {
            print_error($error, 'bookingform');
            return false;
        }

        if (!$DB->update_record('bookingform_signups', $usersignup)) {
            print_error('error:couldnotupdatef2frecord', 'bookingform');
            return false;
        }
    }

    return true;
}

/**
 * Send booking request notice to user and their manager
 *
 * @param  object $bookingform Bookingform instance
 * @param  object $session    Session instance
 * @param  int    $userid     ID of user requesting booking
 * @return string Error string, empty on success
 */
function bookingform_send_request_notice($bookingform, $session, $userid) {
    global $DB;

    if (!$manageremail = bookingform_get_manageremail($userid)) {
        return 'error:nomanagersemailset';
    }

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if ($fromaddress = get_config(null, 'bookingform_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }

    $postsubject = bookingform_email_substitutions(
            $bookingform->requestsubject,
            $bookingform->name,
            $bookingform->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttext = bookingform_email_substitutions(
            $bookingform->requestmessage,
            $bookingform->name,
            $bookingform->reminderperiod,
            $user,
            $session,
            $session->id
    );

    $posttextmgrheading = bookingform_email_substitutions(
            $bookingform->requestinstrmngr,
            $bookingform->name,
            $bookingform->reminderperiod,
            $user,
            $session,
            $session->id
    );

    // Send to user.
    if (!email_to_user($user, $from, $postsubject, $posttext)) {
        return 'error:cannotsendrequestuser';
    }

    // Send to manager.
    $user->email = $manageremail;

    if (!email_to_user($user, $from, $postsubject, $posttextmgrheading.$posttext)) {
        return 'error:cannotsendrequestmanager';
    }

    return '';
}


/**
 * Update the signup status of a particular signup
 *
 * @param integer $signupid ID of the signup to be updated
 * @param integer $statuscode Status code to be updated to
 * @param integer $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 * @param bool $usetransaction Set to true if database transactions are to be used
 *
 * @returns integer ID of newly created signup status, or false
 *
 */
function bookingform_update_signup_status($signupid, $statuscode, $createdby, $note='', $grade=null, $sessionid = null) {
    global $DB;
    $timenow = time();

    $signupstatus = new stdclass;
    $signupstatus->signupid = $signupid;
    $signupstatus->statuscode = $statuscode;
    $signupstatus->createdby = $createdby;
    $signupstatus->timecreated = $timenow;
    $signupstatus->note = $note;
    $signupstatus->grade = $grade;
    $signupstatus->superceded = 0;
    $signupstatus->mailed = 0;

    $transaction = $DB->start_delegated_transaction();

    if ($statusid = $DB->insert_record('bookingform_signups_status', $signupstatus)) {

        // Mark any previous signup_statuses as superceded.
        $where = "signupid = ? AND ( superceded = 0 OR superceded IS NULL ) AND id != ?";
        $whereparams = array($signupid, $statusid);
        $DB->set_field_select('bookingform_signups_status', 'superceded', 1, $where, $whereparams);
        $transaction->allow_commit();

        /**
         * Send email to the user with available slots if has no show status.
         *
         * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
         */
        if ($statuscode == BKF_MDL_F2F_STATUS_NO_SHOW && !empty($sessionid)) {
            trigger_no_show_email($signupid, $sessionid);
        }

        return $statusid;
    } else {
        return false;
    }
}

function bookingform_move_to_top(&$array, $key) {
    $temp = array($key => $array[$key]);
    unset($array[$key]);
    $array = $temp + $array;
}

/**
 * Sends email to a user with upcoming sessions upon now show.
 *
 * @param  int   $signupid
 * @param  int   $sessionid
 * @return void
 */
function trigger_no_show_email($signupid, $sessionid) {
    global $DB, $COURSE;

    if (empty($signupid) || empty($sessionid)) {
        return;
    }

    // Identify the user, then send the available sessions that belong to his group.
    $bksignup   = $DB->get_record('bookingform_signups', array('id' => $signupid), $fields = 'userid');
    $user       = $DB->get_record('user', array('id' => $bksignup->userid));

    $mygroupid      = bookingform_get_my_groupid($COURSE->id, $bksignup->userid);
    $groupmembers   = groups_get_groups_members($mygroupid);

    // Get the trainers for this session.
    $trainers = bookingform_get_trainers($sessionid, 3);
    if (is_array($trainers) || is_object($trainers)) {

        // See if the teachers for this sessions are part of my group.
        foreach ($trainers as $trainer) {
            if (bookingform_find_id_in_array($groupmembers, $trainer->id)) {
                $teacherid = $trainer->id;
                break;
            }
        }

    }

    // Get session from the teacher.
    $sql = "SELECT da.id, da.timestart, da.timefinish
              FROM {bookingform_session_roles} se
              JOIN {bookingform_sessions_dates} da ON da.sessionid = se.sessionid
             WHERE se.userid = ? AND da.timestart > (SELECT timestart FROM {bookingform_sessions_dates} d WHERE d.sessionid = ?)";
    $sessions = $DB->get_records_sql($sql, array($teacherid, $sessionid));

    $bookingmessage = get_string('emailnoshow_start', 'mod_bookingform');
    foreach ($sessions as $session) {
        $bookingmessage .= date('d.M.Y', $session->timestart) . ", " . date('H:i', $session->timestart) . " - " . date('H:i', $session->timefinish) . "<br>";
    }
    $bookingmessage .= get_string('emailnoshow_end', 'mod_bookingform');

    if ($fromaddress = get_config(null, 'bookingform_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }

    if (!email_to_user($user, $from, get_string('emailnoshow_subject', 'mod_bookingform'), '', $bookingmessage)) {
        return 'error:cannotsendconfirmationuser';
    }

}

/**
 * Cancel a user who signed up earlier
 *
 * @param class $session       Record from the bookingform_sessions table
 * @param integer $userid      ID of the user to remove from the session
 * @param bool $forcecancel    Forces cancellation of sessions that have already occurred
 * @param string $errorstr     Passed by reference. For setting error string in calling function
 * @param string $cancelreason Optional justification for cancelling the signup
 */
function bookingform_user_cancel($session, $userid=false, $forcecancel=false, &$errorstr=null, $cancelreason='') {
    global $USER;

    if (!$userid) {
        $userid = $USER->id;
    }

    // If $forcecancel is set, cancel session even if already occurred used by facetotoface_delete_session().
    if (!$forcecancel) {
        $timenow = time();

        // Don't allow user to cancel a session that has already occurred.
        if (bookingform_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'bookingform');
            return false;
        }
    }

    if (bookingform_user_cancel_submission($session->id, $userid, $cancelreason)) {
        // Remove entry from user's calendar.
        bookingform_remove_session_from_calendar($session, 0, $userid);
        bookingform_update_attendees($session);
        return true;
    }

    // Todo: is this necessary?
    $errorstr = get_string('error:cancelbooking', 'bookingform');

    return false;
}

/**
 * Common code for sending confirmation and cancellation notices
 *
 * @param string $postsubject Subject of the email
 * @param string $posttext Plain text contents of the email
 * @param string $posttextmgrheading Header to prepend to $posttext in manager email
 * @param string $notificationtype The type of notification to send
 * @see {{BKF_MDL_F2F_INVITE}}
 * @param class $bookingform record from the bookingform table
 * @param class $session record from the bookingform_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function bookingform_send_notice($postsubject, $posttext, $posttextmgrheading,
                                $notificationtype, $bookingform, $session, $userid) {
    global $CFG, $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if (empty($postsubject) || empty($posttext)) {
        return '';
    }

    // If no notice type is defined (TEXT or ICAL).
    if (!($notificationtype & BKF_MDL_F2F_BOTH)) {

        // If none, make sure they at least get a text email.
        $notificationtype |= BKF_MDL_F2F_TEXT;
    }

    // If we are cancelling, check if ical cancellations are disabled.
    if (($notificationtype & BKF_MDL_F2F_CANCEL) &&
        get_config(null, 'bookingform_disableicalcancel')) {
        $notificationtype |= BKF_MDL_F2F_TEXT; // Add a text notification.
        $notificationtype &= ~BKF_MDL_F2F_ICAL; // Remove the iCalendar notification.
    }

    // If we are sending an ical attachment, set file name.
    if ($notificationtype & BKF_MDL_F2F_ICAL) {
        if ($notificationtype & BKF_MDL_F2F_INVITE) {
            $attachmentfilename = 'invite.ics';
        } else if ($notificationtype & BKF_MDL_F2F_CANCEL) {
            $attachmentfilename = 'cancel.ics';
        }
    }

    // Do iCal attachement stuff.
    $icalattachments = array();
    if ($notificationtype & BKF_MDL_F2F_ICAL) {
        if (get_config(null, 'bookingform_oneemailperday')) {

            // Keep track of all sessiondates.
            $sessiondates = $session->sessiondates;

            foreach ($sessiondates as $sessiondate) {
                $session->sessiondates = array($sessiondate); // One day at a time.

                $filename = bookingform_get_ical_attachment($notificationtype, $bookingform, $session, $user);
                $subject = bookingform_email_substitutions($postsubject, $bookingform->name, $bookingform->reminderperiod,
                                                          $user, $session, $session->id);
                $body = bookingform_email_substitutions($posttext, $bookingform->name, $bookingform->reminderperiod,
                                                       $user, $session, $session->id);
                $htmlbody = ''; // TODO.
                $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                           'body' => $body, 'htmlbody' => $htmlbody);
            }

            // Restore session dates.
            $session->sessiondates = $sessiondates;
        } else {
            $filename = bookingform_get_ical_attachment($notificationtype, $bookingform, $session, $user);
            $subject = bookingform_email_substitutions($postsubject, $bookingform->name, $bookingform->reminderperiod,
                                                      $user, $session, $session->id);
            $body = bookingform_email_substitutions($posttext, $bookingform->name, $bookingform->reminderperiod,
                                                   $user, $session, $session->id);
            $htmlbody = ''; // FIXME.
            $icalattachments[] = array('filename' => $filename, 'subject' => $subject,
                                       'body' => $body, 'htmlbody' => $htmlbody);
        }
    }

    // Fill-in the email placeholders.
    $postsubject = bookingform_email_substitutions($postsubject, $bookingform->name, $bookingform->reminderperiod,
                                                  $user, $session, $session->id);
    $posttext = bookingform_email_substitutions($posttext, $bookingform->name, $bookingform->reminderperiod,
                                               $user, $session, $session->id);

    $posttextmgrheading = bookingform_email_substitutions($posttextmgrheading, $bookingform->name, $bookingform->reminderperiod,
                                                         $user, $session, $session->id);

    $posthtml = ''; // FIXME.
    if ($fromaddress = get_config(null, 'bookingform_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }

    $usercheck = $DB->get_record('user', array('id' => $userid));

    // Send email with iCal attachment.
    if ($notificationtype & BKF_MDL_F2F_ICAL) {
        foreach ($icalattachments as $attachment) {
            if (!email_to_user($user, $from, $attachment['subject'], $attachment['body'],
                    $attachment['htmlbody'], $attachment['filename'], $attachmentfilename)) {

                return 'error:cannotsendconfirmationuser';
            }
            unlink($CFG->dataroot . '/' . $attachment['filename']);
        }
    }

    // Send plain text email.
    if ($notificationtype & BKF_MDL_F2F_TEXT) {
        if (!email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            return 'error:cannotsendconfirmationuser';
        }
    }

    // Manager notification.
    $manageremail = bookingform_get_manageremail($userid);
    if (!empty($posttextmgrheading) and !empty($manageremail) and $session->datetimeknown) {
        $managertext = $posttextmgrheading.$posttext;
        $manager = $user;
        $manager->email = $manageremail;

        // Leave out the ical attachments in the managers notification.
        if (!email_to_user($manager, $from, $postsubject, $managertext, $posthtml)) {
            return 'error:cannotsendconfirmationmanager';
        }
    }

    // Third-party notification.
    if (!empty($bookingform->thirdparty) &&
        ($session->datetimeknown || !empty($bookingform->thirdpartywaitlist))) {

        $thirdparty = $user;
        $recipients = explode(',', $bookingform->thirdparty);
        foreach ($recipients as $recipient) {
            $thirdparty->email = trim($recipient);

            // Leave out the ical attachments in the 3rd parties notification.
            if (!email_to_user($thirdparty, $from, $postsubject, $posttext, $posthtml)) {
                return 'error:cannotsendconfirmationthirdparty';
            }
        }
    }

    return '';
}

/**
 * Send a confirmation email to the user and manager
 *
 * @param class $bookingform record from the bookingform table
 * @param class $session record from the bookingform_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{BKF_MDL_F2F_INVITE}}
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @returns string Error message (or empty string if successful)
 */
function bookingform_send_confirmation_notice($bookingform, $session, $userid, $notificationtype, $iswaitlisted) {

    $posttextmgrheading = $bookingform->confirmationinstrmngr;

    if (!$iswaitlisted) {
        $postsubject = $bookingform->confirmationsubject;
        $posttext = $bookingform->confirmationmessage;
    } else {
        $postsubject = $bookingform->waitlistedsubject;
        $posttext = $bookingform->waitlistedmessage;

        // Don't send an iCal attachement when we don't know the date!
        $notificationtype |= BKF_MDL_F2F_TEXT; // Add a text notification.
        $notificationtype &= ~BKF_MDL_F2F_ICAL; // Remove the iCalendar notification.
    }

    // Set invite bit.
    $notificationtype |= BKF_MDL_F2F_INVITE;

    return bookingform_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $bookingform, $session, $userid);
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class $bookingform record from the bookingform table
 * @param class $session record from the bookingform_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function bookingform_send_cancellation_notice($bookingform, $session, $userid) {
    global $DB;

    $postsubject = $bookingform->cancellationsubject;
    $posttext = $bookingform->cancellationmessage;
    $posttextmgrheading = $bookingform->cancellationinstrmngr;

    // Lookup what type of notification to send.
    $notificationtype = $DB->get_field('bookingform_signups', 'notificationtype',
                                  array('sessionid' => $session->id, 'userid' => $userid));

    // Set cancellation bit.
    $notificationtype |= BKF_MDL_F2F_CANCEL;

    return bookingform_send_notice($postsubject, $posttext, $posttextmgrheading,
                                  $notificationtype, $bookingform, $session, $userid);
}

/**
 * Returns true if the user has registered for a session in the given
 * bookingform activity
 *
 * @global class $USER used to get the current userid
 * @returns integer The session id that we signed up for, false otherwise
 */
function bookingform_check_signup($bookingformid) {
    global $USER;

    if ($submissions = bookingform_get_user_submissions($bookingformid, $USER->id)) {
        return reset($submissions)->sessionid;
    } else {
        return false;
    }
}

/**
 * Return the email address of the user's manager if it is
 * defined. Otherwise return an empty string.
 *
 * @param integer $userid User ID of the staff member
 */
function bookingform_get_manageremail($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => BKF_MDL_MANAGERSEMAIL_FIELD));
    if ($fieldid) {
        return $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $fieldid));
    } else {
        return ''; // No custom field => no manager's email.
    }
}

/**
 * Human-readable version of the format of the manager's email address
 */
function bookingform_get_manageremailformat() {
    $addressformat = get_config(null, 'bookingform_manageraddressformat');
    if (!empty($addressformat)) {
        $readableformat = get_config(null, 'bookingform_manageraddressformatreadable');
        return get_string('manageremailformat', 'bookingform', $readableformat);
    }

    return '';
}

/**
 * Returns true if the given email address follows the format
 * prescribed by the site administrator
 *
 * @param string $manageremail email address as entered by the user
 */
function bookingform_check_manageremail($manageremail) {
    $addressformat = get_config(null, 'bookingform_manageraddressformat');
    if (empty($addressformat) || strpos($manageremail, $addressformat)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Mark the fact that the user attended the bookingform session by
 * giving that user a grade of 100
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and every submission ID to mark as attended
 *                    under the 'submissionid_XXXX' keys where XXXX is
 *                     the ID of the signup
 */
function bookingform_take_attendance($data) {
    global $USER;

    $sessionid = $data->s;

    // Load session.
    if (!$session = bookingform_get_session($sessionid)) {
        // error_log('F2F: Could not load bookingform session');
        return false;
    }

    // Check bookingform has finished.
    if ($session->datetimeknown && !bookingform_has_session_started($session, time())) {
        // error_log('F2F: Can not take attendance for a session that has not yet started');
        return false;
    }

    /*
     * Record the selected attendees from the user interface - the other attendees will need their grades set
     * to zero, to indicate non attendance, but only the ticked attendees come through from the web interface.
     * Hence the need for a diff
     */
    $selectedsubmissionids = array();

    /*
     * FIXME: This is not very efficient, we should do the grade
     * query outside of the loop to get all submissions for a
     * given Face-to-face ID, then call
     * bookingform_grade_item_update with an array of grade objects.
     */
    foreach ($data as $key => $value) {
        $submissionidcheck = substr($key, 0, 13);
        if ($submissionidcheck == 'submissionid_') {
            $submissionid = substr($key, 13);
            $selectedsubmissionids[$submissionid] = $submissionid;

            // Update status.
            switch ($value) {
                case BKF_MDL_F2F_STATUS_NO_SHOW:
                    $grade = 0;
                    break;
                case BKF_MDL_F2F_STATUS_PARTIALLY_ATTENDED:
                    $grade = 50;
                    break;
                case BKF_MDL_F2F_STATUS_FULLY_ATTENDED:
                    $grade = 100;
                    break;
                default:
                    // This use has not had attendance set: jump to the next item in the foreach loop.
                    continue 2;
            }

            bookingform_update_signup_status($submissionid, $value, $USER->id, '', $grade, $sessionid);
            if (!bookingform_take_individual_attendance($submissionid, $grade)) {
                // error_log("F2F: could not mark '$submissionid' as " . $value);
                return false;
            }
        }
    }

    return true;
}

/**
 * Mark users' booking requests as declined or approved
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and an array of request approval/denies
 */
function bookingform_approve_requests($data) {
    global $USER, $DB;

    // Check request data.
    if (empty($data->requests) || !is_array($data->requests)) {
        // error_log('F2F: No request data supplied');
        return false;
    }

    $sessionid = $data->s;

    // Load session.
    if (!$session = bookingform_get_session($sessionid)) {
        // error_log('F2F: Could not load bookingform session');
        return false;
    }

    // Load bookingform.
    if (!$bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform))) {
        // error_log('F2F: Could not load bookingform instance');
        return false;
    }

    // Load course.
    if (!$course = $DB->get_record('course', array('id' => $bookingform->course))) {
        // error_log('F2F: Could not load course');
        return false;
    }

    // Loop through requests.
    foreach ($data->requests as $key => $value) {

        // Check key/value.
        if (!is_numeric($key) || !is_numeric($value)) {
            continue;
        }

        // Load user submission.
        if (!$attendee = bookingform_get_attendee($sessionid, $key)) {
            // error_log('F2F: User '.$key.' not an attendee of this session');
            continue;
        }

        // Update status.
        switch ($value) {

            // Decline.
            case 1:
                bookingform_update_signup_status(
                        $attendee->submissionid,
                        BKF_MDL_F2F_STATUS_DECLINED,
                        $USER->id
                );

                // Send a cancellation notice to the user.
                bookingform_send_cancellation_notice($bookingform, $session, $attendee->id);

                break;

            // Approve.
            case 2:
                bookingform_update_signup_status(
                        $attendee->submissionid,
                        BKF_MDL_F2F_STATUS_APPROVED,
                        $USER->id
                );

                if (!$cm = get_coursemodule_from_instance('bookingform', $bookingform->id, $course->id)) {
                    print_error('error:incorrectcoursemodule', 'bookingform');
                }

                $contextmodule = context_module::instance($cm->id);

                // Check if there is capacity.
                if (bookingform_session_has_capacity($session, $contextmodule)) {
                    $status = BKF_MDL_F2F_STATUS_BOOKED;
                } else {
                    if ($session->allowoverbook) {
                        $status = BKF_MDL_F2F_STATUS_WAITLISTED;
                    }
                }

                // Signup user.
                if (!bookingform_user_signup(
                        $session,
                        $bookingform,
                        $course,
                        $attendee->discountcode,
                        $attendee->notificationtype,
                        $status,
                        $attendee->id
                    )) {
                    continue;
                }

                break;

            case 0:
            default:
                // Change nothing.
                continue;
        }
    }

    return true;
}

/*
 * Set the grading for an individual submission, to either 0 or 100 to indicate attendance
 *
 * @param $submissionid The id of the submission in the database
 * @param $grading Grade to set
 */
function bookingform_take_individual_attendance($submissionid, $grading) {
    global $USER, $CFG, $DB;

    $timenow = time();
    $record = $DB->get_record_sql("SELECT f.*, s.userid
                                FROM {bookingform_signups} s
                                JOIN {bookingform_sessions} fs ON s.sessionid = fs.id
                                JOIN {bookingform} f ON f.id = fs.bookingform
                                JOIN {course_modules} cm ON cm.instance = f.id
                                JOIN {modules} m ON m.id = cm.module
                                WHERE s.id = ? AND m.name='bookingform'",
                            array($submissionid));

    $grade = new stdclass();
    $grade->userid = $record->userid;
    $grade->rawgrade = $grading;
    $grade->rawgrademin = 0;
    $grade->rawgrademax = 100;
    $grade->timecreated = $timenow;
    $grade->timemodified = $timenow;
    $grade->usermodified = $USER->id;

    return bookingform_grade_item_update($record, $grade);
}
/**
 * Used in many places to obtain properly-formatted session date and time info
 *
 * @param int $start a start time Unix timestamp
 * @param int $end an end time Unix timestamp
 * @param string $tz a session timezone
 * @return object Formatted date, start time, end time and timezone info
 */
function bookingform_format_session_times($start, $end, $tz) {

    $displaytimezones = get_config(null, 'bookingform_displaysessiontimezones');

    $formattedsession = new stdClass();
    if (empty($tz) or empty($displaytimezones)) {
        $targettz = core_date::get_user_timezone();
    } else {
        $targettz = core_date::get_user_timezone($tz);
    }

    $formattedsession->startdate = userdate($start, get_string('strftimedate', 'langconfig'), $targettz);
    $formattedsession->starttime = userdate($start, get_string('strftimetime', 'langconfig'), $targettz);
    $formattedsession->enddate = userdate($end, get_string('strftimedate', 'langconfig'), $targettz);
    $formattedsession->endtime = userdate($end, get_string('strftimetime', 'langconfig'), $targettz);
    if (empty($displaytimezones)) {
        $formattedsession->timezone = '';
    } else {
        $formattedsession->timezone = core_date::get_localised_timezone($targettz);
    }
    return $formattedsession;
}

/**
 * Used by course/lib.php to display a few sessions besides the
 * bookingform activity on the course page
 *
 * @param object $cm the cm_info object for the F2F instance
 * @global class $USER used to get the current userid
 * @global class $CFG used to get the path to the module
 */
function bookingform_cm_info_view(cm_info $coursemodule) {
    global $CFG, $USER, $DB, $COURSE;
    $output = '';
    $content = '';

    if (!($bookingform = $DB->get_record('bookingform', array('id' => $coursemodule->instance)))) {
        return null;
    }

    /**
     * Display description if the option was checked. This validation should be
     * a couple of more times, before the function ends.
     *
     * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
     */
    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $output .= format_module_intro('bookingform', $bookingform, $coursemodule->id, false);
    }

    $coursemodule->set_name($bookingform->name);

    $contextmodule = context_module::instance($coursemodule->id);
    if (!has_capability('mod/bookingform:view', $contextmodule)) {
        return null; // Not allowed to view this activity.
    }
    // Can view attendees.
    $viewattendees = has_capability('mod/bookingform:viewattendees', $contextmodule);
    // Can see "view all sessions" link even if activity is hidden/currently unavailable.
    $iseditor = has_any_capability(array('mod/bookingform:viewattendees', 'mod/bookingform:editsessions',
        'mod/bookingform:addattendees', 'mod/bookingform:addattendees',
        'mod/bookingform:takeattendance'), $contextmodule);

    $timenow = time();

    $strviewallsessions = get_string('viewallsessions', 'bookingform');
    $sessionsurl = new moodle_url('/mod/bookingform/view.php', array('f' => $bookingform->id));
    $htmlviewallsessions = html_writer::link($sessionsurl, $strviewallsessions, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $strviewallsessions));

    if ($submissions = bookingform_get_user_submissions($bookingform->id, $USER->id)) {
        // User has signedup for the instance.

        foreach ($submissions as $submission) {

            if ($session = bookingform_get_session($submission->sessionid)) {
                $userisinwaitlist = bookingform_is_user_on_waitlist($session, $USER->id);
                if ($session->datetimeknown && bookingform_has_session_started($session, $timenow) && bookingform_is_session_in_progress($session, $timenow)) {
                    $status = get_string('sessioninprogress', 'bookingform');
                } else if ($session->datetimeknown && bookingform_has_session_started($session, $timenow)) {
                    $status = get_string('sessionover', 'bookingform');
                } else if ($userisinwaitlist) {
                    $status = get_string('waitliststatus', 'bookingform');
                } else {
                    $status = get_string('bookingstatus', 'bookingform');
                }

                // Add booking information.
                $session->bookedsession = $submission;

                $sessiondates = '';

                if ($session->datetimeknown) {
                    foreach ($session->sessiondates as $date) {
                        if (!empty($sessiondates)) {
                            $sessiondates .= html_writer::empty_tag('br');
                        }
                        $sessionobj = bookingform_format_session_times($date->timestart, $date->timefinish, null);
                        if ($sessionobj->startdate == $sessionobj->enddate) {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartdateandtime' : 'sessionstartdateandtimewithouttimezone';
                            $sessiondates .= get_string($sessiondatelangkey, 'bookingform', $sessionobj);
                        } else {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartfinishdateandtime' : 'sessionstartfinishdateandtimewithouttimezone';
                            $sessiondates .= get_string($sessiondatelangkey, 'bookingform', $sessionobj);
                        }
                    }
                } else {
                    $sessiondates = get_string('wait-listed', 'bookingform');
                }

                $span = html_writer::tag('span', get_string('options', 'bookingform').':', array('class' => 'f2fsessionnotice'));

                // Don't include the link to cancel a session if it has already occurred.
                $moreinfolink = '';
                $cancellink = '';
                if (!bookingform_has_session_started($session, $timenow)) {
                    $strmoreinfo  = get_string('moreinfo', 'bookingform');
                    $signupurl   = new moodle_url('/mod/bookingform/signup.php', array('s' => $session->id));
                    $moreinfolink = html_writer::link($signupurl, $strmoreinfo, array('class' => 'f2fsessionlinks f2fsessioninfolink', 'title' => $strmoreinfo));
                }

                // Don't include the link to view attendees if user is lacking capability.
                $attendeeslink = '';
                if ($viewattendees) {
                    $strseeattendees = get_string('seeattendees', 'bookingform');
                    $attendeesurl = new moodle_url('/mod/bookingform/attendees.php', array('s' => $session->id));
                    $attendeeslink = html_writer::link($attendeesurl, $strseeattendees, array('class' => 'f2fsessionlinks f2fviewattendees', 'title' => $strseeattendees));
                }

                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'))
                    . html_writer::tag('span', $status, array('class' => 'f2fsessionnotice'))
                    . html_writer::start_tag('div', array('class' => 'f2fsession f2fsignedup'))
                    . html_writer::tag('div', $sessiondates, array('class' => 'f2fsessiontime'))
                    . html_writer::tag('div', $span . $moreinfolink . $attendeeslink . $cancellink, array('class' => 'f2foptions'))
                    . html_writer::end_tag('div')
                    . html_writer::end_tag('div');
            }
        }
        // Add "view all sessions" row to table.
        $output .= $htmlviewallsessions;

    } else if ($sessions = bookingform_get_sessions($bookingform->id)) {
        if ($bookingform->display > 0) {
            $j = 1;

            $sessionsinprogress = array();
            $futuresessions = array();

            /**
             * Get the group members which I belong so we can find the teacher/coach.
             *
             * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
             * @since   02.14.2019
             */
            $mygroupid = bookingform_get_my_groupid($COURSE->id, $USER->id);
            if (!empty($mygroupid)) {
                $groupmembers = groups_get_groups_members($mygroupid);
            }

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

                if (!bookingform_session_has_capacity($session, $contextmodule, BKF_MDL_F2F_STATUS_WAITLISTED) && !$session->allowoverbook) {
                    continue;
                }

                if ($session->datetimeknown && bookingform_has_session_started($session, $timenow) && !bookingform_is_session_in_progress($session, $timenow)) {
                    // Finished session, don't display.
                    continue;
                } else {
                    $signupurl   = new moodle_url('/mod/bookingform/signup.php', array('s' => $session->id));
                    $signuptext   = 'signup';
                    $moreinfolink = html_writer::link($signupurl, get_string($signuptext, 'bookingform'), array('class' => 'f2fsessionlinks f2fsessioninfolink'));

                    $span = html_writer::tag('span', get_string('options', 'bookingform').':', array('class' => 'f2fsessionnotice'));
                }

                $multidate = '';
                $sessiondate = '';
                if ($session->datetimeknown) {
                    if (empty($session->sessiondates)) {
                        $sessiondate = get_string('unknowndate', 'bookingform');
                    } else {
                        $sessionobj = bookingform_format_session_times($session->sessiondates[0]->timestart, $session->sessiondates[0]->timefinish, null);
                        if ($sessionobj->startdate == $sessionobj->enddate) {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartdateandtime' : 'sessionstartdateandtimewithouttimezone';
                            $sessiondate = get_string($sessiondatelangkey, 'bookingform', $sessionobj);
                        } else {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartfinishdateandtime' : 'sessionstartfinishdateandtimewithouttimezone';
                            $sessiondate .= get_string($sessiondatelangkey, 'bookingform', $sessionobj);
                        }
                        if (count($session->sessiondates) > 1) {
                            $multidate = html_writer::empty_tag('br') . get_string('multidate', 'bookingform');
                        }
                    }
                } else {
                    $sessiondate = get_string('wait-listed', 'bookingform');
                }

                $sessionobject = new stdClass();
                $sessionobject->date = $sessiondate;
                $sessionobject->multidate = $multidate;

                if ($session->datetimeknown && (bookingform_has_session_started($session, $timenow)) && bookingform_is_session_in_progress($session, $timenow)) {
                    $sessionsinprogress[] = $sessionobject;
                } else {
                    $sessionobject->options = $span;
                    $sessionobject->moreinfolink = $moreinfolink;
                    $futuresessions[] = $sessionobject;
                }

                $j++;
                if ($j > $bookingform->display) {
                    break;
                }
            }

            if (!empty($sessionsinprogress)) {
                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'));
                $output .= html_writer::tag('span', get_string('sessioninprogress', 'bookingform'), array('class' => 'f2fsessionnotice'));

                foreach ($sessionsinprogress as $session) {
                    $output .= html_writer::start_tag('div', array('class' => 'f2fsession f2finprogress'))
                        . html_writer::tag('span', $session->date.$session->multidate, array('class' => 'f2fsessiontime'))
                        . html_writer::end_tag('div');
                }
                $output .= html_writer::end_tag('div');
            }

            if (!empty($futuresessions)) {
                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'));
                $output .= html_writer::tag('span', get_string('signupforsession', 'bookingform'), array('class' => 'f2fsessionnotice'));

                foreach ($futuresessions as $session) {
                    $output .= html_writer::start_tag('div', array('class' => 'f2fsession f2ffuture'))
                        . html_writer::tag('div', $session->date.$session->multidate, array('class' => 'f2fsessiontime'))
                        . html_writer::tag('div', $session->options . $session->moreinfolink, array('class' => 'f2foptions'))
                        . html_writer::end_tag('div');
                }
                $output .= html_writer::end_tag('div');
            }

            $output .= ($iseditor || ($coursemodule->visible && $coursemodule->available)) ? $htmlviewallsessions : $strviewallsessions;

        } else {
            // Show only name if session display is set to zero.
            if ($coursemodule->showdescription) {
                // Convert intro to html. Do not filter cached version, filters run at display time.
                $content = format_module_intro('bookingform', $bookingform, $coursemodule->id, false);
            }
            $content .= html_writer::tag('span', $htmlviewallsessions, array('class' => 'f2fsessionnotice f2factivityname'));
            $coursemodule->set_content($content);
            return;
        }
    } else if (has_capability('mod/bookingform:viewemptyactivities', $contextmodule)) {
        if ($coursemodule->showdescription) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $content = format_module_intro('bookingform', $bookingform, $coursemodule->id, false);
        }
        $content .= html_writer::tag('span', $htmlviewallsessions, array('class' => 'f2fsessionnotice f2factivityname'));
        $coursemodule->set_content($content);
        return;
    } else {
        // Nothing to display to this user.
        $coursemodule->set_content('');
        return;
    }

    $coursemodule->set_content($output);
}

function bookingform_search_in_objarray($search, $arr) {

    $result = array_filter(
        $arr,
        function ($e) use ($search) {
            return $e->id == $search;
        }
    );

    return $result;

}

function bookingform_get_my_groupid($courseid, $userid) {
    global $DB;

    $sql = "SELECT g.id
            FROM {course} AS c
            JOIN {groups} AS g ON g.courseid = c.id
            JOIN {groups_members} AS m ON g.id = m.groupid
            JOIN {user} AS u ON m.userid = u.id
            WHERE c.id = ? AND u.id = ?";

    return $DB->get_record_sql($sql, array($courseid, $userid));
}

function bookingform_find_id_in_array(array $myArray, $word) {
    foreach ($myArray as $element) {
        if ($element->id == $word) {
            return true;
        }
    }
    return false;
}

function bookingform_get_teachers($courseid) {
    global $DB;

    // Get the teachers from the course.
    $query = "SELECT u.id
                FROM {course} ic
                JOIN {context} con ON con.instanceid = ic.id
                JOIN {role_assignments} ra ON con.id = ra.contextid AND con.contextlevel = 50
                JOIN {role} r ON ra.roleid = r.id
                JOIN {user} u ON u.id = ra.userid
               WHERE r.id = 3 AND ic.id = ?";
    return $DB->get_records_sql($query, array($courseid));

}

/**
 * Returns the ICAL data for a bookingform meeting.
 *
 * @param integer $method The method, @see {{BKF_MDL_F2F_INVITE}}
 * @param object $bookingform A face-to-face object containing activity details
 * @param object $session A session object containing session details
 * @return string Filename of the attachment in the temp directory
 */
function bookingform_get_ical_attachment($method, $bookingform, $session, $user) {
    global $CFG, $DB;

    // First, generate all the VEVENT blocks.
    $vevents = '';
    foreach ($session->sessiondates as $date) {

        /*
         * Date that this representation of the calendar information was created -
         * we use the time the session was created
         * http://www.kanzaki.com/docs/ical/dtstamp.html
         */
        $dtstamp = bookingform_ical_generate_timestamp($session->timecreated);

        // UIDs should be globally unique.
        $urlbits = parse_url($CFG->wwwroot);
        $sql = "SELECT COUNT(*)
            FROM {bookingform_signups} su
            INNER JOIN {bookingform_signups_status} sus ON su.id = sus.signupid
            WHERE su.userid = ?
                AND su.sessionid = ?
                AND sus.superceded = 1
                AND sus.statuscode = ? ";
        $params = array($user->id, $session->id, BKF_MDL_F2F_STATUS_USER_CANCELLED);

        $uid = $dtstamp .
            '-' . substr(md5($CFG->siteidentifier . $session->id . $date->id), -8) .   // Unique identifier, salted with site identifier.
            '-' . $DB->count_records_sql($sql, $params) .                              // New UID if this is a re-signup.
            '@' . $urlbits['host'];                                                    // Hostname for this moodle installation.

        $dtstart = bookingform_ical_generate_timestamp($date->timestart);
        $dtend   = bookingform_ical_generate_timestamp($date->timefinish);

        // FIXME: currently we are not sending updates if the times of the session are changed. This is not ideal!
        $sequence = ($method & BKF_MDL_F2F_CANCEL) ? 1 : 0;

        $summary     = bookingform_ical_escape($bookingform->name);
        $description = bookingform_ical_escape($session->details, true);

        // Get the location data from custom fields if they exist.
        $customfielddata = bookingform_get_customfielddata($session->id);
        $locationstring = '';
        if (!empty($customfielddata['room'])) {
            $locationstring .= $customfielddata['room']->data;
        }
        if (!empty($customfielddata['venue'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['venue']->data;
        }
        if (!empty($customfielddata['location'])) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $customfielddata['location']->data;
        }

        /*
         * NOTE: Newlines are meant to be encoded with the literal sequence
         * '\n'. But evolution presents a single line text field for location,
         * and shows the newlines as [0x0A] junk. So we switch it for commas
         * here. Remember commas need to be escaped too.
         */
        $location = str_replace('\n', '\, ', bookingform_ical_escape($locationstring));

        $organiseremail = get_config(null, 'bookingform_fromaddress');

        $role = 'REQ-PARTICIPANT';
        $cancelstatus = '';
        if ($method & BKF_MDL_F2F_CANCEL) {
            $role = 'NON-PARTICIPANT';
            $cancelstatus = "\nSTATUS:CANCELLED";
        }

        $icalmethod = ($method & BKF_MDL_F2F_INVITE) ? 'REQUEST' : 'CANCEL';

        // FIXME: if the user has input their name in another language, we need to set the LANGUAGE property parameter here.
        $username = fullname($user);
        $mailto   = $user->email;

        // The extra newline at the bottom is so multiple events start on their own lines. The very last one is trimmed outside the loop.
        $vevents .= <<<EOF
BEGIN:VEVENT
UID:{$uid}
DTSTAMP:{$dtstamp}
DTSTART:{$dtstart}
DTEND:{$dtend}
SEQUENCE:{$sequence}
SUMMARY:{$summary}
LOCATION:{$location}
DESCRIPTION:{$description}
CLASS:PRIVATE
TRANSP:OPAQUE{$cancelstatus}
ORGANIZER;CN={$organiseremail}:MAILTO:{$organiseremail}
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$role};PARTSTAT=NEEDS-ACTION;
 RSVP=FALSE;CN={$username};LANGUAGE=en:MAILTO:{$mailto}
END:VEVENT

EOF;
    }

    $vevents = trim($vevents);

    // TODO: remove the hard-coded timezone!.
    $template = <<<EOF
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Moodle//NONSGML Bookingform//EN
VERSION:2.0
METHOD:{$icalmethod}
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Tzfile/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZNAME:NZST
DTSTART:19700405T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=4
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:NZDT
DTSTART:19700928T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE
{$vevents}
END:VCALENDAR
EOF;

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    file_put_contents($tempfilepathname, $template);
    return $tempfilename;
}

function bookingform_ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}

/**
 * Escapes data of the text datatype in ICAL documents.
 *
 * See RFC2445 or http://www.kanzaki.com/docs/ical/text.html or a more readable definition
 */
function bookingform_ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ','),
        array('\\\\', '\n', '\;', '\,'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one whitespace after the newline that does the wrapping.
    $text = wordwrap($text, 75, "\n ", true);

    return $text;
}

/**
 * Determine if a user is in the waitlist of a session.
 *
 * @param object $session A session object
 * @param int $userid The user ID
 * @return bool True if the user is on waitlist, false otherwise.
 */
function bookingform_is_user_on_waitlist($session, $userid = null) {
    global $DB, $USER;

    if ($userid === null) {
        $userid = $USER->id;
    }

    $sql = "SELECT 1
            FROM {bookingform_signups} su
            JOIN {bookingform_signups_status} ss ON su.id = ss.signupid
            WHERE su.sessionid = ?
              AND ss.superceded != 1
              AND su.userid = ?
              AND ss.statuscode = ?";

    return $DB->record_exists_sql($sql, array($session->id, $userid, BKF_MDL_F2F_STATUS_WAITLISTED));
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $bookingform null means all bookingform activities
 * @param int $userid specific user only, 0 mean all (not used here)
 */
function bookingform_update_grades($bookingform=null, $userid=0) {
    global $DB;

    if ($bookingform != null) {
            bookingform_grade_item_update($bookingform);
    } else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {bookingform} f
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='bookingform'";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $bookingform) {
                bookingform_grade_item_update($bookingform);
            }
            $rs->close();
        }
    }

    return true;
}

/**
 * Create grade item for given Face-to-face session
 *
 * @param int bookingform  Face-to-face activity (not the session) to grade
 * @param mixed grades    grades objects or 'reset' (means reset grades in gradebook)
 * @return int 0 if ok, error code otherwise
 */
function bookingform_grade_item_update($bookingform, $grades=null) {
    global $CFG, $DB;

    if (!isset($bookingform->cmidnumber)) {

        $sql = "SELECT cm.idnumber as cmidnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='bookingform' AND cm.instance = ?";
        $bookingform->cmidnumber = $DB->get_field_sql($sql, array($bookingform->id));
    }

    $params = array('itemname' => $bookingform->name,
                    'idnumber' => $bookingform->cmidnumber);

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademin']  = 0;
    $params['gradepass'] = 100;
    $params['grademax']  = 100;

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $retcode = grade_update('mod/bookingform', $bookingform->course, 'mod', 'bookingform',
                            $bookingform->id, 0, $grades, $params);
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Delete grade item for given bookingform
 *
 * @param object $bookingform object
 * @return object bookingform
 */
function bookingform_grade_item_delete($bookingform) {
    $retcode = grade_update('mod/bookingform', $bookingform->course, 'mod', 'bookingform',
                            $bookingform->id, 0, null, array('deleted' => 1));
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Return number of attendees signed up to a bookingform session
 *
 * @param integer $sessionid
 * @param integer $status MDL_F2F_STATUS_* constant (optional)
 * @return integer
 */
function bookingform_get_num_attendees($sessionid, $status=BKF_MDL_F2F_STATUS_BOOKED) {
    global $CFG, $DB;

    $sql = 'SELECT count(ss.id)
        FROM
            {bookingform_signups} su
        JOIN
            {bookingform_signups_status} ss
        ON
            su.id = ss.signupid
        WHERE
            sessionid = ?
        AND
            ss.superceded=0
        AND
        ss.statuscode >= ?';

    // For the session, pick signups that haven't been superceded, or cancelled.
    return (int) $DB->count_records_sql($sql, array($sessionid, $status));
}

/**
 * Return all of a users' submissions to a bookingform
 *
 * @param integer $bookingformid
 * @param integer $userid
 * @param boolean $includecancellations
 * @return array submissions | false No submissions
 */
function bookingform_get_user_submissions($bookingformid, $userid, $includecancellations=false) {
    global $CFG, $DB;

    $whereclause = "s.bookingform = ? AND su.userid = ? AND ss.superceded != 1";
    $whereparams = array($bookingformid, $userid);

    // If not show cancelled, only show requested and up status'.
    if (!$includecancellations) {
        $whereclause .= ' AND ss.statuscode >= ? AND ss.statuscode < ?';
        $whereparams = array_merge($whereparams, array(BKF_MDL_F2F_STATUS_REQUESTED, BKF_MDL_F2F_STATUS_NO_SHOW));
    }

    // TODO fix mailedconfirmation, timegraded, timecancelled, etc.
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.bookingform,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            su.discountcode,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            su.notificationtype,
            ss.statuscode
        FROM
            {bookingform_sessions} s
        JOIN
            {bookingform_signups} su
         ON su.sessionid = s.id
        JOIN
            {bookingform_signups_status} ss
         ON su.id = ss.signupid
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ", $whereparams);
}

/**
 * Cancel users' submission to a bookingform session
 *
 * @param integer $sessionid   ID of the bookingform_sessions record
 * @param integer $userid      ID of the user record
 * @param string $cancelreason Short justification for cancelling the signup
 * @return boolean success
 */
function bookingform_user_cancel_submission($sessionid, $userid, $cancelreason='') {
    global $DB;

    $signup = $DB->get_record('bookingform_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    if (!$signup) {
        return true; // Not signed up, nothing to do.
    }

    return bookingform_update_signup_status($signup->id, BKF_MDL_F2F_STATUS_USER_CANCELLED, $userid, $cancelreason);
}

/**
 * A list of actions in the logs that indicate view activity for participants
 */
function bookingform_get_view_actions() {
    return array('view', 'view all');
}

/**
 * A list of actions in the logs that indicate post activity for participants
 */
function bookingform_get_post_actions() {
    return array('cancel booking', 'signup');
}

/**
 * Return a small object with summary information about what a user
 * has done with a given particular instance of this module (for user
 * activity reports.)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 */
function bookingform_user_outline($course, $user, $mod, $bookingform) {

    $result = new stdClass;
    $grade = bookingform_get_grade($user->id, $course->id, $bookingform->id);
    if ($grade->grade > 0) {
        $result = new stdClass;
        $result->info = get_string('grade') . ': ' . $grade->grade;
        $result->time = $grade->dategraded;
    } else if ($submissions = bookingform_get_user_submissions($bookingform->id, $user->id)) {
        $result->info = get_string('usersignedup', 'bookingform');
        $result->time = reset($submissions)->timecreated;
    } else {
        $result->info = get_string('usernotsignedup', 'bookingform');
    }

    return $result;
}

/**
 * Print a detailed representation of what a user has done with a
 * given particular instance of this module (for user activity
 * reports).
 */
function bookingform_user_complete($course, $user, $mod, $bookingform) {
    $grade = bookingform_get_grade($user->id, $course->id, $bookingform->id);
    if ($submissions = bookingform_get_user_submissions($bookingform->id, $user->id, true)) {
        print get_string('grade') . ': ' . $grade->grade . html_writer::empty_tag('br');
        if ($grade->dategraded > 0) {
            $timegraded = trim(userdate($grade->dategraded, get_string('strftimedatetime')));
            print '(' . format_string($timegraded) . ')' . html_writer::empty_tag('br');
        }
        echo html_writer::empty_tag('br');

        foreach ($submissions as $submission) {
            $timesignedup = trim(userdate($submission->timecreated, get_string('strftimedatetime')));
            print get_string('usersignedupon', 'bookingform', format_string($timesignedup)) . html_writer::empty_tag('br');

            if ($submission->timecancelled > 0) {
                $timecancelled = userdate($submission->timecancelled, get_string('strftimedatetime'));
                print get_string('usercancelledon', 'bookingform', format_string($timecancelled)) . html_writer::empty_tag('br');
            }
        }
    } else {
        print get_string('usernotsignedup', 'bookingform');
    }

    return true;
}

/**
 * Add a link to the session to the courses calendar.
 *
 * @param class   $session          Record from the bookingform_sessions table
 * @param class   $eventname        Name to display for this event
 * @param string  $calendartype     Which calendar to add the event to (user, course, site)
 * @param int     $userid           Optional param for user calendars
 * @param string  $eventtype        Optional param for user calendar (booking/session)
 */
function bookingform_add_session_to_calendar($session, $bookingform, $calendartype='none', $userid=0, $eventtype='session') {
    global $CFG, $DB;

    if (empty($session->datetimeknown)) {
        return true; // Date unkown, can't add to calendar.
    }

    if (empty($bookingform->showoncalendar) && empty($bookingform->usercalentry)) {
        return true; // Bookingform calendar settings prevent calendar.
    }

    $description = '';
    $groupid = 0;
    if (!empty($bookingform->description)) {
        $description .= html_writer::tag('p', clean_param($bookingform->description, PARAM_CLEANHTML));
    }
    $description .= bookingform_print_session($session, false, true, true);
    $linkurl = new moodle_url('/mod/bookingform/signup.php', array('s' => $session->id));
    $linktext = get_string('signupforthissession', 'bookingform');

    if ($calendartype == 'site' && $bookingform->showoncalendar == BKF_F2F_CAL_SITE) {
        $courseid = SITEID;
        $modulename = '0';
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'course' && $bookingform->showoncalendar == BKF_F2F_CAL_COURSE) {
        $courseid = $bookingform->course;
        $modulename = 'bookingform';
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'user' && $bookingform->usercalentry) {
        $courseid = 0;
        $modulename = '0';
        $urlvar = ($eventtype == 'session') ? 'attendees' : 'signup';
        $linkurl = $CFG->wwwroot . "/mod/bookingform/" . $urlvar . ".php?s=$session->id";
        $description .= get_string("calendareventdescription{$eventtype}", 'bookingform', $linkurl);
    } else if ($calendartype == 'group' && $bookingform->showoncalendar == BKF_F2F_CAL_COURSE) {
        $courseid = $bookingform->course;
        $groupid = $bookingform->groupid;
        $modulename = 'bookingform';
        $description .= html_writer::link($linkurl, $linktext);
    } else {
        return true;
    }

    $shortname = $bookingform->shortname;
    if (empty($shortname)) {
        $shortname = substr($bookingform->name, 0, BKF_CALENDAR_MAX_NAME_LENGTH);
    }

    if (isset($bookingform->extradescription)) {
        $shortname .= $bookingform->extradescription;
    }

    $result = true;
    foreach ($session->sessiondates as $date) {
        $newevent = new stdClass();
        $newevent->name = $shortname;
        $newevent->description = $description;
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = $courseid;
        $newevent->groupid = $groupid;
        $newevent->userid = $userid;
        $newevent->uuid = "{$session->id}";
        $newevent->instance = $session->bookingform;
        $newevent->modulename = $modulename;
        $newevent->eventtype = "bookingform{$eventtype}";
        $newevent->type = 0; // CALENDAR_EVENT_TYPE_STANDARD: Only display on the calendar, not needed on the block_myoverview.
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        if ($calendartype == 'user' && $eventtype == 'booking') {

            // Check for and Delete the 'created' calendar event to reduce multiple entries for the same event.
            $DB->delete_records_select('event', 'userid = ? AND instance = ? AND '
                . $DB->sql_compare_text('eventtype') . ' = ? AND ' . $DB->sql_compare_text('name') . ' = ?',
                array($userid, $session->bookingform, 'bookingformsession', $shortname));
        }

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}

/**
 * Remove all entries in the course calendar which relate to this session.
 *
 * @param class $session    Record from the bookingform_sessions table
 * @param integer $courseid ID of the course - 0 for user event, SITEID for global event, 2+ for course event.
 * @param string $userid    ID of the user. If not specified, will match any used ID.
 */
function bookingform_remove_session_from_calendar($session, $courseid=0, $userid=0) {
    global $DB;

    $modulename = '0';         // User events and Site events.
    if ($courseid > SITEID) {  // Course event.
        $modulename = 'bookingform';
    }
    if (empty($userid)) { // Match any UserID.
        $params = array($modulename, $session->bookingform, $courseid, $session->id);
        return $DB->delete_records_select('event', "modulename = ? AND
                                                    instance = ? AND
                                                    courseid = ? AND
                                                    uuid = ?", $params);
    } else {
        $params = array($modulename, $session->bookingform, $userid, $courseid, $session->id);
        return $DB->delete_records_select('event', "modulename = ? AND
                                                    instance = ? AND
                                                    userid = ? AND
                                                    courseid = ? AND
                                                    uuid = ?", $params);
    }
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param object $session       Record from the bookingform_sessions table
 * @param string $eventtype     Type of event to update
 */
function bookingform_update_user_calendar_events($session, $eventtype) {
    global $DB, $USER, $COURSE;

    $bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform));
    if (empty($bookingform->usercalentry) || $bookingform->usercalentry == 0) {
        return true;
    }

    $users = bookingform_delete_user_calendar_events($session, $eventtype);

    // Add this session to these users' calendar.
    foreach ($users as $user) {
        bookingform_add_session_to_calendar($session, $bookingform, 'user', $user->userid, $eventtype);
    }

    return true;
}

/**
 * Delete all user level calendar events for a booking form session
 *
 * @param class     $session    Record from the bookingform_sessions table
 * @param string    $eventtype  Type of the event (booking or session)
 * @return array    $users      Array of users who had the event deleted
 */
function bookingform_delete_user_calendar_events($session, $eventtype) {
    global $CFG, $DB;

    $whereclause = "modulename = '0' AND
                    eventtype = 'bookingform$eventtype' AND
                    instance = ?";

    $whereparams = array($session->bookingform);

    if ('session' == $eventtype) {
        $likestr = "%attendees.php?s={$session->id}%";
        $like = $DB->sql_like('description', '?');
        $whereclause .= " AND $like";

        $whereparams[] = $likestr;
    }

    // Users calendar.
    $users = $DB->get_records_sql("SELECT DISTINCT userid
        FROM {event}
        WHERE $whereclause", $whereparams);

    if ($users && count($users) > 0) {

        // Delete the existing events.
        $DB->delete_records_select('event', $whereclause, $whereparams);
    }

    return $users;
}

/**
 * Confirm that a user can be added to a session.
 *
 * @param class  $session Record from the bookingform_sessions table
 * @param object $context (optional) A context object (record from context table)
 * @return bool True if user can be added to session
 **/
function bookingform_session_has_capacity($session, $context=false) {
    if (empty($session)) {
        return false;
    }

    $signupcount = bookingform_get_num_attendees($session->id);
    if ($signupcount >= $session->capacity) {

        // If session is full, check if overbooking is allowed for this user.
        if (!$context || !has_capability('mod/bookingform:overbook', $context)) {
            return false;
        }
    }

    return true;
}

/**
 * Print the details of a session
 *
 * @param object $session         Record from bookingform_sessions
 * @param boolean $showcapacity   Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return         Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup     Hide any messages relating to signing up
 */
function bookingform_print_session($session, $showcapacity, $calendaroutput=false, $return=false, $hidesignup=false) {
    global $CFG, $DB;

    $table = new html_table();
    $table->summary = get_string('sessionsdetailstablesummary', 'bookingform');
    $table->attributes['class'] = 'generaltable f2fsession';
    $table->align = array('right', 'left');
    if ($calendaroutput) {
        $table->tablealign = 'left';
    }

    $customfields = bookingform_get_session_customfields();
    $customdata = $DB->get_records('bookingform_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $data = '';
        if (!empty($customdata[$field->id])) {
            if (BKF_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $values = explode(BKF_CUSTOMFIELD_DELIMITER, format_string($customdata[$field->id]->data));
                $data = implode(html_writer::empty_tag('br'), $values);
            } else {
                $data = format_string($customdata[$field->id]->data);
            }
        }
        $table->data[] = array(str_replace(' ', '&nbsp;', format_string($field->name)), $data);
    }

    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'bookingform'));
    if ($session->datetimeknown) {
        $html = '';
        foreach ($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= html_writer::empty_tag('br');
            }
            $timestart = userdate($date->timestart, get_string('strftimedatetime'));
            $timefinish = userdate($date->timefinish, get_string('strftimedatetime'));
            $html .= "$timestart &ndash; $timefinish";
        }
        $table->data[] = array($strdatetime, $html);
    } else {
        $table->data[] = array($strdatetime, html_writer::tag('i', get_string('wait-listed', 'bookingform')));
    }

    $signupcount = bookingform_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        if ($session->allowoverbook) {
            $table->data[] = array(get_string('capacity', 'bookingform'), $session->capacity . ' ('.strtolower(get_string('allowoverbook', 'bookingform')).')');
        } else {
            $table->data[] = array(get_string('capacity', 'bookingform'), $session->capacity);
        }
    } else if (!$calendaroutput) {
        $table->data[] = array(get_string('seatsavailable', 'bookingform'), max(0, $placesleft));
    }

    // Display requires approval notification.
    $bookingform = $DB->get_record('bookingform', array('id' => $session->bookingform));

    if ($bookingform->approvalreqd) {
        $table->data[] = array('', get_string('sessionrequiresmanagerapproval', 'bookingform'));
    }

    // Display waitlist notification.
    if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
        $table->data[] = array('', get_string('userwillbewaitlisted', 'bookingform'));
    }

    if (!empty($session->duration)) {
        $table->data[] = array(get_string('duration', 'bookingform'), bookingform_format_duration($session->duration));
    }
    if (!empty($session->normalcost)) {
        $table->data[] = array(get_string('normalcost', 'bookingform'), bookingform_format_cost($session->normalcost));
    }
    if (!empty($session->discountcost)) {
        $table->data[] = array(get_string('discountcost', 'bookingform'), bookingform_format_cost($session->discountcost));
    }
    if (!empty($session->details)) {
        $details = clean_text($session->details, FORMAT_HTML);
        $table->data[] = array(get_string('details', 'bookingform'), $details);
    }

    // Display trainers.
    $trainerroles = bookingform_get_trainer_roles();

    if ($trainerroles) {

        // Get trainers.
        $trainers = bookingform_get_trainers($session->id);
        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->name;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainernames = array();
            foreach ($trainers[$role] as $trainer) {
                $trainerurl = new moodle_url('/user/view.php', array('id' => $trainer->id));
                $trainernames[] = html_writer::link($trainerurl, fullname($trainer));
            }

            $table->data[] = array($rolename, implode(', ', $trainernames));
        }
    }

    return html_writer::table($table, $return);
}

/**
 * Update the value of a customfield for the given session/notice.
 *
 * @param integer $fieldid    ID of a record from the bookingform_session_field table
 * @param string  $data       Value for that custom field
 * @param integer $otherid    ID of a record from the bookingform_(sessions|notice) table
 * @param string  $table      'session' or 'notice' (part of the table name)
 * @returns true if it succeeded, false otherwise
 */
function bookingform_save_customfield_value($fieldid, $data, $otherid, $table) {
    global $DB;

    $dbdata = null;
    if (is_array($data)) {
        $dbdata = trim(implode(BKF_CUSTOMFIELD_DELIMITER, $data), ';');
    } else {
        $dbdata = trim($data);
    }

    $newrecord = new stdClass();
    $newrecord->data = $dbdata;

    $fieldname = "{$table}id";
    if ($record = $DB->get_record("bookingform_{$table}_data", array('fieldid' => $fieldid, $fieldname => $otherid))) {
        if (empty($dbdata)) {

            // Clear out the existing value.
            return $DB->delete_records("bookingform_{$table}_data", array('id' => $record->id));
        }

        $newrecord->id = $record->id;
        return $DB->update_record("bookingform_{$table}_data", $newrecord);
    } else {
        if (empty($dbdata)) {
            return true; // No need to store empty values.
        }

        $newrecord->fieldid = $fieldid;
        $newrecord->$fieldname = $otherid;

        return $DB->insert_record("bookingform_{$table}_data", $newrecord);
    }
}

/**
 * Return the value of a customfield for the given session/notice.
 *
 * @param object  $field    A record from the bookingform_session_field table
 * @param integer $otherid  ID of a record from the bookingform_(sessions|notice) table
 * @param string  $table    'session' or 'notice' (part of the table name)
 * @returns string The data contained in this custom field (empty string if it doesn't exist)
 */
function bookingform_get_customfield_value($field, $otherid, $table) {
    global $DB;

    if ($record = $DB->get_record("bookingform_{$table}_data", array('fieldid' => $field->id, "{$table}id" => $otherid))) {
        if (!empty($record->data)) {
            if (BKF_CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                return explode(BKF_CUSTOMFIELD_DELIMITER, $record->data);
            }
            return $record->data;
        }
    }

    return '';
}

/**
 * Return the values stored for all custom fields in the given session.
 *
 * @param integer $sessionid  ID of bookingform_sessions record
 * @returns array Indexed by field shortnames
 */
function bookingform_get_customfielddata($sessionid) {
    global $CFG, $DB;

    $sql = "SELECT f.shortname, d.data
              FROM {bookingform_session_field} f
              JOIN {bookingform_session_data} d ON f.id = d.fieldid
              WHERE d.sessionid = ?";

    $records = $DB->get_records_sql($sql, array($sessionid));

    return $records;
}

/**
 * Return a cached copy of all records in bookingform_session_field
 */
function bookingform_get_session_customfields() {
    global $DB;

    static $customfields = null;
    if (null == $customfields) {
        if (!$customfields = $DB->get_records('bookingform_session_field')) {
            $customfields = array();
        }
    }
    return $customfields;
}

/**
 * Display the list of custom fields in the site-wide settings page
 */
function bookingform_list_of_customfields() {
    global $CFG, $USER, $DB, $OUTPUT;

    if ($fields = $DB->get_records('bookingform_session_field', array(), 'name', 'id, name')) {
        $table = new html_table();
        $table->attributes['class'] = 'halfwidthtable';
        foreach ($fields as $field) {
            $fieldname = format_string($field->name);
            $editurl = new moodle_url('/mod/bookingform/customfield.php', array('id' => $field->id));
            $editlink = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')));
            $deleteurl = new moodle_url('/mod/bookingform/customfield.php', array('id' => $field->id, 'd' => '1', 'sesskey' => $USER->sesskey));
            $deletelink = $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));
            $table->data[] = array($fieldname, $editlink, $deletelink);
        }
        return html_writer::table($table, true);
    }

    return get_string('nocustomfields', 'bookingform');
}

function bookingform_update_trainers($sessionid, $form) {
    global $DB;

    // If we recieved bad data.
    if (!is_array($form)) {
        return false;
    }

    // Load current trainers.
    $oldtrainers = bookingform_get_trainers($sessionid);

    $transaction = $DB->start_delegated_transaction();

    // Loop through form data and add any new trainers.
    foreach ($form as $roleid => $trainers) {

        // Loop through trainers in this role.
        foreach ($trainers as $trainer) {

            if (!$trainer) {
                continue;
            }

            // If the trainer doesn't exist already, create it.
            if (!isset($oldtrainers[$roleid][$trainer])) {

                $newtrainer = new stdClass();
                $newtrainer->userid = $trainer;
                $newtrainer->roleid = $roleid;
                $newtrainer->sessionid = $sessionid;

                if (!$DB->insert_record('bookingform_session_roles', $newtrainer)) {
                    print_error('error:couldnotaddtrainer', 'bookingform');
                    $transaction->force_transaction_rollback();

                    return false;
                }
            } else {
                unset($oldtrainers[$roleid][$trainer]);
            }
        }
    }

    // Loop through what is left of old trainers, and remove (as they have been deselected).
    if ($oldtrainers) {
        foreach ($oldtrainers as $roleid => $trainers) {

            // If no trainers left.
            if (empty($trainers)) {
                continue;
            }

            // Delete any remaining trainers.
            foreach ($trainers as $trainer) {
                if (!$DB->delete_records('bookingform_session_roles', array('sessionid' => $sessionid, 'roleid' => $roleid, 'userid' => $trainer->id))) {
                    print_error('error:couldnotdeletetrainer', 'bookingform');
                    $transaction->force_transaction_rollback();
                    return false;
                }
            }
        }
    }

    $transaction->allow_commit();

    return true;
}


/**
 * Return array of trainer roles configured for face-to-face
 *
 * @return array
 */
function bookingform_get_trainer_roles() {
    global $CFG, $DB;

    // Check that roles have been selected.
    if (empty($CFG->bookingform_session_roles)) {
        return false;
    }

    // Parse roles.
    $cleanroles = clean_param($CFG->bookingform_session_roles, PARAM_SEQUENCE);
    $roles = explode(',', $cleanroles);
    list($rolesql, $params) = $DB->get_in_or_equal($roles);

    // Load role names.
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {role} r
        WHERE
            r.id {$rolesql}
        AND r.id <> 0
    ", $params);

    // Return roles and names.
    if (!$rolenames) {
        return array();
    }

    return $rolenames;
}


/**
 * Get all trainers associated with a session, optionally
 * restricted to a certain roleid
 *
 * If a roleid is not specified, will return a multi-dimensional
 * array keyed by roleids, with an array of the chosen roles
 * for each role
 *
 * @param  integer $sessionid
 * @param  integer $roleid (optional)
 * @return array
 */
function bookingform_get_trainers($sessionid, $roleid = null) {
    global $CFG, $DB;

    $usernamefields = get_all_user_name_fields(true, 'u');
    $sql = "
        SELECT
            u.id,
            r.roleid,
            {$usernamefields}
        FROM
            {bookingform_session_roles} r
        LEFT JOIN
            {user} u
         ON u.id = r.userid
        WHERE
            r.sessionid = ?
        ";
    $params = array($sessionid);

    if ($roleid) {
        $sql .= "AND r.roleid = ?";
        $params[] = $roleid;
    }

    $rs = $DB->get_recordset_sql($sql , $params);
    $return = array();
    foreach ($rs as $record) {

        // Create new array for this role.
        if (!isset($return[$record->roleid])) {
            $return[$record->roleid] = array();
        }
        $return[$record->roleid][$record->id] = $record;
    }
    $rs->close();

    // If we are only after one roleid.
    if ($roleid) {
        if (empty($return[$roleid])) {
            return false;
        }
        return $return[$roleid];
    }

    // If we are after all roles.
    if (empty($return)) {
        return false;
    }

    return $return;
}

/**
 * Determines whether an activity requires the user to have a manager (either for
 * manager approval or to send notices to the manager)
 *
 * @param  object $bookingform A database fieldset object for the bookingform activity
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function bookingform_manager_needed($bookingform) {
    return $bookingform->approvalreqd
        || $bookingform->confirmationinstrmngr
        || $bookingform->reminderinstrmngr
        || $bookingform->cancellationinstrmngr;
}

/**
 * Display the list of site notices in the site-wide settings page
 */
function bookingform_list_of_sitenotices() {
    global $CFG, $USER, $DB, $OUTPUT;

    if ($notices = $DB->get_records('bookingform_notice', array(), 'name', 'id, name')) {
        $table = new html_table();
        $table->width = '50%';
        $table->tablealign = 'left';
        $table->data = array();
        $table->size = array('100%');
        foreach ($notices as $notice) {
            $noticename = format_string($notice->name);
            $editurl = new moodle_url('/mod/bookingform/sitenotice.php', array('id' => $notice->id));
            $editlink = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')));
            $deleteurl = new moodle_url('/mod/bookingform/sitenotice.php', array('id' => $notice->id, 'd' => '1', 'sesskey' => $USER->sesskey));
            $deletelink = $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));
            $table->data[] = array($noticename, $editlink, $deletelink);
        }
        return html_writer::table($table, true);
    }

    return get_string('nositenotices', 'bookingform');
}

/**
 * Add formslib fields for all custom fields defined site-wide.
 * (used by the session add/edit page and the site notices)
 */
function bookingform_add_customfields_to_form(&$mform, $customfields, $alloptional=false) {
    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";

        $options = array();
        if (!$field->required) {
            $options[''] = get_string('none');
        }
        foreach (explode(BKF_CUSTOMFIELD_DELIMITER, $field->possiblevalues) as $value) {
            $v = trim($value);
            if (!empty($v)) {
                $options[$v] = $v;
            }
        }

        switch ($field->type) {
            case BKF_CUSTOMFIELD_TYPE_TEXT:
                $mform->addElement('text', $fieldname, $field->name);
                break;
            case BKF_CUSTOMFIELD_TYPE_SELECT:
                $mform->addElement('select', $fieldname, $field->name, $options);
                break;
            case BKF_CUSTOMFIELD_TYPE_MULTISELECT:
                $select = &$mform->addElement('select', $fieldname, $field->name, $options);
                $select->setMultiple(true);
                break;
            default:
                // error_log("bookingform: invalid field type for custom field ID $field->id");
                continue;
        }

        $mform->setType($fieldname, PARAM_TEXT);
        $mform->setDefault($fieldname, $field->defaultvalue);
        if ($field->required and !$alloptional) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }
}

/**
 * Get session cancellations
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function bookingform_get_cancellations($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = get_all_user_name_fields(true, 'u');
    $instatus = array(BKF_MDL_F2F_STATUS_BOOKED, BKF_MDL_F2F_STATUS_WAITLISTED, BKF_MDL_F2F_STATUS_REQUESTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);

    // Nasty SQL follows:
    // Load currently cancelled users, include most recent booked/waitlisted time also.
    $sql = "
            SELECT
                u.id,
                {$usernamefields},
                su.id AS signupid,
                MAX(ss.timecreated) AS timesignedup,
                c.timecreated AS timecancelled,
                " . $DB->sql_compare_text('c.note', 250) . " AS cancelreason
            FROM
                {bookingform_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            JOIN
                {bookingform_signups_status} c
             ON su.id = c.signupid
            AND c.statuscode = ?
            AND c.superceded = 0
            LEFT JOIN
                {bookingform_signups_status} ss
             ON su.id = ss.signupid
             AND ss.statuscode $insql
            AND ss.superceded = 1
            WHERE
                su.sessionid = ?
            GROUP BY
                u.id, su.id,
                {$usernamefields},
                c.timecreated,
                " . $DB->sql_compare_text('c.note', 250) . "
            ORDER BY
                {$fullname},
                c.timecreated
    ";
    $params = array_merge(array(BKF_MDL_F2F_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session unapproved requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function bookingform_get_requests($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = get_all_user_name_fields(true);

    $params = array($sessionid, BKF_MDL_F2F_STATUS_REQUESTED);

    $sql = "SELECT u.id, su.id AS signupid, {$usernamefields},
                   ss.timecreated AS timerequested
              FROM {bookingform_signups} su
              JOIN {bookingform_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";

    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session declined requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function bookingform_get_declines($sessionid) {
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $usernamefields = get_all_user_name_fields(true);

    $params = array($sessionid, BKF_MDL_F2F_STATUS_DECLINED);

    $sql = "SELECT u.id, su.id AS signupid, {$usernamefields},
                   ss.timecreated AS timerequested
              FROM {bookingform_signups} su
              JOIN {bookingform_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";
    return $DB->get_records_sql($sql, $params);
}


/**
 * Returns all other caps used in module
 * @return array
 */
function bookingform_get_extra_capabilities() {
    return array('moodle/site:viewfullnames');
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function bookingform_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/*
 * bookingform assignment candidates
 */
class bookingform_candidate_selector extends user_selector_base {
    protected $sessionid;

    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /*
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search) {
        global $DB, $COURSE, $USER;

        // All non-signed up system user.
        list($wherecondition, $params) = $this->search_sql($search, 'u');

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(u.id)';
        $sql = "
                  FROM {user} u
                 WHERE $wherecondition
                   AND u.id NOT IN
                       (
                       SELECT u2.id
                         FROM {bookingform_signups} s
                         JOIN {bookingform_signups_status} ss ON s.id = ss.signupid
                         JOIN {user} u2 ON u2.id = s.userid
                        WHERE s.sessionid = :sessid
                          AND ss.statuscode >= :statuswaitlisted
                          AND ss.superceded = 0
                       )
               ";
        $order = " ORDER BY u.lastname ASC, u.firstname ASC";
        $params = array_merge($params,
            array(
                'sessid' => $this->sessionid,
                'statuswaitlisted' => BKF_MDL_F2F_STATUS_WAITLISTED
            ));

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        /**
         * When assigning users/attendees to the session,
         * display only those that belong to the teacher/coach group.
         *
         * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
         * @since   02.17.2019
         */

        $mygroupid = bookingform_get_my_groupid($COURSE->id, $USER->id);
        if (!empty($mygroupid)) {
            $groupmembers   = groups_get_groups_members($mygroupid);
            $availableusers = array_uintersect($availableusers, $groupmembers,
                function ($obj_a, $obj_b) {
                    return $obj_a->id - $obj_b->id;
                }
            );
        }

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('potentialusers', 'role', count($availableusers));

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/bookingform/lib.php';
        return $options;
    }
}

/**
 * Bookingform assignment candidates
 */
class bookingform_existing_selector extends user_selector_base {
    protected $sessionid;

    public function __construct($name, $options) {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $whereparams) = $this->search_sql($search, 'u');

        $fields  = 'SELECT ' . $this->required_fields_sql('u');
        $fields .= ', su.id AS submissionid, s.discountcost, su.discountcode, su.notificationtype, f.id AS bookingformid,
            f.course, ss.grade, ss.statuscode, sign.timecreated';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
            FROM
                {bookingform} f
            JOIN
                {bookingform_sessions} s
             ON s.bookingform = f.id
            JOIN
                {bookingform_signups} su
             ON s.id = su.sessionid
            JOIN
                {bookingform_signups_status} ss
             ON su.id = ss.signupid
            LEFT JOIN
                (
                SELECT
                    ss.signupid,
                    MAX(ss.timecreated) AS timecreated
                FROM
                    {bookingform_signups_status} ss
                INNER JOIN
                    {bookingform_signups} s
                 ON s.id = ss.signupid
                AND s.sessionid = :sessid1
                WHERE
                    ss.statuscode IN (:statusbooked, :statuswaitlisted)
                GROUP BY
                    ss.signupid
                ) sign
             ON su.id = sign.signupid
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                $wherecondition
            AND s.id = :sessid2
            AND ss.superceded != 1
            AND ss.statuscode >= :statusapproved
        ";
        $order = " ORDER BY sign.timecreated ASC, ss.timecreated ASC";
        $params = array ('sessid1' => $this->sessionid, 'statusbooked' => BKF_MDL_F2F_STATUS_BOOKED, 'statuswaitlisted' => BKF_MDL_F2F_STATUS_WAITLISTED);
        $params = array_merge($params, $whereparams);
        $params['sessid2'] = $this->sessionid;
        $params['statusapproved'] = BKF_MDL_F2F_STATUS_APPROVED;
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);
        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('existingusers', 'role', count($availableusers));
        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'mod/bookingform/lib.php';
        return $options;
    }
}
