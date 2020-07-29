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

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/bookingform/lib.php');

class mod_bookingform_session_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;

        $mform =& $this->_form;
        $context = context_course::instance($this->_customdata['course']->id);

        // Course Module ID.
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        // Bookingform Instance ID.
        $mform->addElement('hidden', 'f', $this->_customdata['f']);
        $mform->setType('f', PARAM_INT);

        // Bookingform Session ID.
        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);

        // Copy Session Flag.
        $mform->addElement('hidden', 'c', $this->_customdata['c']);
        $mform->setType('c', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $editoroptions = $this->_customdata['editoroptions'];

        // Show all custom fields.
        $customfields = $this->_customdata['customfields'];
        bookingform_add_customfields_to_form($mform, $customfields);

        // Hack to put help files on these custom fields.
        // TODO: add to the admin page a feature to put help text on custom fields.
        if ($mform->elementExists('custom_location')) {
            $mform->addHelpButton('custom_location', 'location', 'bookingform');
        }
        if ($mform->elementExists('custom_venue')) {
            $mform->addHelpButton('custom_venue', 'venue', 'bookingform');
        }
        if ($mform->elementExists('custom_room')) {
            $mform->addHelpButton('custom_room', 'room', 'bookingform');
        }

        $formarray  = array();
        $formarray[] = $mform->createElement('selectyesno', 'datetimeknown', get_string('sessiondatetimeknown', 'bookingform'));
        $formarray[] = $mform->createElement('static', 'datetimeknownhint', '',
            html_writer::tag('span', get_string('datetimeknownhinttext', 'bookingform'), array('class' => 'hint-text')));
        $mform->addGroup($formarray, 'datetimeknown_group', get_string('sessiondatetimeknown', 'bookingform'), array(' '), false);
        $mform->addGroupRule('datetimeknown_group', null, 'required', null, 'client');
        $mform->setDefault('datetimeknown', false);
        $mform->addHelpButton('datetimeknown_group', 'sessiondatetimeknown', 'bookingform');

        $repeatarray = array();
        $repeatarray[] = &$mform->createElement('hidden', 'sessiondateid', 0);
        $mform->setType('sessiondateid', PARAM_INT);
        $repeatarray[] = &$mform->createElement('date_time_selector', 'timestart', get_string('timestart', 'bookingform'));
        $repeatarray[] = &$mform->createElement('date_time_selector', 'timefinish', get_string('timefinish', 'bookingform'));
        $checkboxelement = &$mform->createElement('checkbox', 'datedelete', '', get_string('dateremove', 'bookingform'));
        unset($checkboxelement->_attributes['id']); // Necessary until MDL-20441 is fixed.
        $repeatarray[] = $checkboxelement;
        $repeatarray[] = &$mform->createElement('html', html_writer::empty_tag('br')); // Spacer.

        $repeatcount = $this->_customdata['nbdays'];

        $repeatoptions = array();
        $repeatoptions['timestart']['disabledif'] = array('datetimeknown', 'eq', 0);
        $repeatoptions['timefinish']['disabledif'] = array('datetimeknown', 'eq', 0);
        $mform->setType('timestart', PARAM_INT);
        $mform->setType('timefinish', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatcount, $repeatoptions, 'date_repeats', 'date_add_fields',
                               1, get_string('dateadd', 'bookingform'), true);

        if (has_capability('mod/bookingform:configurecancellation', $context)) {
            $mform->addElement('advcheckbox', 'allowcancellations', get_string('allowcancellations', 'bookingform'));
            $mform->setDefault('allowcancellations', $this->_customdata['bookingform']->allowcancellationsdefault);
            $mform->addHelpButton('allowcancellations', 'allowcancellations', 'bookingform');
        }

        $mform->addElement('text', 'capacity', get_string('capacity', 'bookingform'), 'size="5"');
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 1);
        $mform->addHelpButton('capacity', 'capacity', 'bookingform');

        $mform->addElement('checkbox', 'allowoverbook', get_string('allowoverbook', 'bookingform'));
        $mform->addHelpButton('allowoverbook', 'allowoverbook', 'bookingform');

        $mform->addElement('text', 'duration', get_string('duration', 'bookingform'), 'size="5"');
        $mform->setType('duration', PARAM_TEXT);
        $mform->addHelpButton('duration', 'duration', 'bookingform');

        if (!get_config(null, 'bookingform_hidecost')) {
            $formarray  = array();
            $formarray[] = $mform->createElement('text', 'normalcost', get_string('normalcost', 'bookingform'), 'size="5"');
            $formarray[] = $mform->createElement('static', 'normalcosthint', '', html_writer::tag('span',
                get_string('normalcosthinttext', 'bookingform'), array('class' => 'hint-text')));
            $mform->addGroup($formarray, 'normalcost_group', get_string('normalcost', 'bookingform'), array(' '), false);
            $mform->setType('normalcost', PARAM_TEXT);
            $mform->addHelpButton('normalcost_group', 'normalcost', 'bookingform');

            if (!get_config(null, 'bookingform_hidediscount')) {
                $formarray  = array();
                $formarray[] = $mform->createElement('text', 'discountcost', get_string('discountcost', 'bookingform'), 'size="5"');
                $formarray[] = $mform->createElement('static', 'discountcosthint', '', html_writer::tag('span',
                    get_string('discountcosthinttext', 'bookingform'), array('class' => 'hint-text')));
                $mform->addGroup($formarray, 'discountcost_group', get_string('discountcost', 'bookingform'), array(' '), false);
                $mform->setType('discountcost', PARAM_TEXT);
                $mform->addHelpButton('discountcost_group', 'discountcost', 'bookingform');
            }
        }

        $mform->addElement('editor', 'details_editor', get_string('details', 'bookingform'), null, $editoroptions);
        $mform->setType('details_editor', PARAM_RAW);
        $mform->addHelpButton('details_editor', 'details', 'bookingform');

        // Choose users for trainer roles.
        $rolenames = bookingform_get_trainer_roles();

        if ($rolenames) {

            // Get current trainers.
            $currenttrainers = bookingform_get_trainers($this->_customdata['s']);

            // Loop through all selected roles.
            $headershown = false;
            foreach ($rolenames as $role => $rolename) {
                $rolename = $rolename->name;

                // Attempt to load users with this role in this course.
                $usernamefields = get_all_user_name_fields(true);
                $rs = $DB->get_recordset_sql("
                    SELECT
                        u.id,
                        {$usernamefields}
                    FROM
                        {role_assignments} ra
                    LEFT JOIN
                        {user} u
                      ON ra.userid = u.id
                    WHERE
                        contextid = {$context->id}
                    AND roleid = {$role}
                ");

                if (!$rs) {
                    continue;
                }

                $choices = array();
                foreach ($rs as $roleuser) {
                    $choices[$roleuser->id] = fullname($roleuser);
                }
                $rs->close();

                // Show header (if haven't already).
                if ($choices && !$headershown) {
                    $mform->addElement('header', 'trainerroles', get_string('sessionroles', 'bookingform'));
                    $headershown = true;
                }

                /**
                 * Move the coach/teacher who is creating the session
                 * to the top.
                 *
                 * @author  Andres Ramos <andres.ramos@lmsdoctor.com>
                 */
                bookingform_move_to_top($choices, $USER->id);

                // If only a few, use checkboxes.
                if (count($choices) < 4) {
                    $roleshown = false;
                    foreach ($choices as $cid => $choice) {

                        // Only display the role title for the first checkbox for each role.
                        if (!$roleshown) {
                            $roledisplay = $rolename;
                            $roleshown = true;
                        } else {
                            $roledisplay = '';
                        }

                        $mform->addElement('advcheckbox', 'trainerrole[' . $role . '][' . $cid . ']', $roledisplay, $choice,
                            null, array('', $cid));
                        $mform->setType('trainerrole[' . $role . '][' . $cid . ']', PARAM_INT);
                    }
                } else {
                    $mform->addElement('select', 'trainerrole[' . $role . ']', $rolename, $choices,
                        array('multiple' => 'multiple'));
                    $mform->setType('trainerrole[' . $role . ']', PARAM_SEQUENCE);
                }

                // Select current trainers.
                if ($currenttrainers) {
                    foreach ($currenttrainers as $role => $trainers) {
                        $t = array();
                        foreach ($trainers as $trainer) {
                            $t[] = $trainer->id;
                            $mform->setDefault('trainerrole[' . $role . '][' . $trainer->id . ']', $trainer->id);
                        }

                        $mform->setDefault('trainerrole[' . $role . ']', implode(',', $t));
                    }
                }
            }
        }

        $mform->addElement('html', '<hr />');

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = $mform->createElement('submit', 'submitbutton_add_new', get_string('save_add_new', 'mod_bookingform'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'page_actions', '', array(' '), false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $dateids = $data['sessiondateid'];
        $dates = count($dateids);
        for ($i = 0; $i < $dates; $i++) {
            $starttime = $data["timestart"][$i];
            $endtime = $data["timefinish"][$i];
            $removecheckbox = empty($data["datedelete"]) ? array() : $data["datedelete"];
            if ($starttime > $endtime && !isset($removecheckbox[$i])) {
                $errstr = get_string('error:sessionstartafterend', 'bookingform');
                $errors['timestart'][$i] = $errstr;
                $errors['timefinish'][$i] = $errstr;
                unset($errstr);
            }
        }

        if (!empty($data['datetimeknown'])) {
            $datefound = false;
            for ($i = 0; $i < $data['date_repeats']; $i++) {
                if (empty($data['datedelete'][$i])) {
                    $datefound = true;
                    break;
                }
            }

            if (!$datefound) {
                $errors['datetimeknown'] = get_string('validation:needatleastonedate', 'bookingform');
            }
        }

        return $errors;
    }
}
