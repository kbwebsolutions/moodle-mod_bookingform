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

/**
 * Structure step to restore one bookingform activity
 */
class restore_bookingform_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('bookingform', '/activity/bookingform');
        $paths[] = new restore_path_element('bookingform_session', '/activity/bookingform/sessions/session');
        $paths[] = new restore_path_element('bookingform_sessions_dates', '/activity/bookingform/sessions/session/sessions_dates/sessions_date');
        $paths[] = new restore_path_element('bookingform_session_data', '/activity/bookingform/sessions/session/session_data/session_data_element');
        $paths[] = new restore_path_element('bookingform_session_field', '/activity/bookingform/sessions/session/session_field/session_field_element');
        if ($userinfo) {
            $paths[] = new restore_path_element('bookingform_signup', '/activity/bookingform/sessions/session/signups/signup');
            $paths[] = new restore_path_element('bookingform_signups_status', '/activity/bookingform/sessions/session/signups/signup/signups_status/signup_status');
            $paths[] = new restore_path_element('bookingform_session_roles', '/activity/bookingform/sessions/session/session_roles/session_role');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_bookingform($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the bookingform record.
        $newitemid = $DB->insert_record('bookingform', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_bookingform_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->bookingform = $this->get_new_parentid('bookingform');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_sessions', $data);
        $this->set_mapping('bookingform_session', $oldid, $newitemid, true); // Childs and files by itemname.
    }

    protected function process_bookingform_signup($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('bookingform_session');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_signups', $data);
        $this->set_mapping('bookingform_signup', $oldid, $newitemid, true); // Childs and files by itemname.
    }

    protected function process_bookingform_signups_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->signupid = $this->get_new_parentid('bookingform_signup');

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_signups_status', $data);
    }

    protected function process_bookingform_session_roles($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('bookingform_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->roleid = $this->get_mappingid('role', $data->roleid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_session_roles', $data);
    }

    protected function process_bookingform_session_data($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('bookingform_session');
        $data->fieldid = $this->get_mappingid('bookingform_session_field');

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_session_data', $data);
        $this->set_mapping('bookingform_session_data', $oldid, $newitemid, true); // Childs and files by itemname.
    }

    protected function process_bookingform_session_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_session_field', $data);
    }

    protected function process_bookingform_sessions_dates($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('bookingform_session');

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);

        // Insert the entry record.
        $newitemid = $DB->insert_record('bookingform_sessions_dates', $data);
    }

    protected function after_execute() {
        // Face-to-face doesn't have any related files.
        // Add bookingform related files, no need to match by itemname (just internally handled context).
    }
}
