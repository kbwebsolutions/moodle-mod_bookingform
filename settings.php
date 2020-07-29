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

require_once($CFG->dirroot . '/mod/bookingform/lib.php');

$settings->add(new admin_setting_configtext(
    'bookingform_fromaddress',
    get_string('setting:fromaddress_caption', 'bookingform'),
    get_string('setting:fromaddress', 'bookingform'),
    get_string('setting:fromaddressdefault', 'bookingform'),
    "/^((?:[\w\.\-])+\@(?:(?:[a-zA-Z\d\-])+\.)+(?:[a-zA-Z\d]{2,4}))$/",
    30
));

// Load roles.
$choices = array();
if ($roles = role_fix_names(get_all_roles(), context_system::instance())) {
    foreach ($roles as $role) {
        $choices[$role->id] = format_string($role->localname);
    }
}

$settings->add(new admin_setting_configmultiselect(
    'bookingform_session_roles',
    get_string('setting:sessionroles_caption', 'bookingform'),
    get_string('setting:sessionroles', 'bookingform'),
    array(),
    $choices
));


$settings->add(new admin_setting_heading(
    'bookingform_manageremail_header',
    get_string('manageremailheading', 'bookingform'),
    ''
));

$settings->add(new admin_setting_configcheckbox(
    'bookingform_addchangemanageremail',
    get_string('setting:addchangemanageremail_caption', 'bookingform'),
    get_string('setting:addchangemanageremail', 'bookingform'),
    0
));

$settings->add(new admin_setting_configtext(
    'bookingform_manageraddressformat',
    get_string('setting:manageraddressformat_caption', 'bookingform'),
    get_string('setting:manageraddressformat', 'bookingform'),
    get_string('setting:manageraddressformatdefault', 'bookingform'),
    PARAM_TEXT
));

$settings->add(new admin_setting_configtext(
    'bookingform_manageraddressformatreadable',
    get_string('setting:manageraddressformatreadable_caption', 'bookingform'),
    get_string('setting:manageraddressformatreadable', 'bookingform'),
    get_string('setting:manageraddressformatreadabledefault', 'bookingform'),
    PARAM_NOTAGS
));

$settings->add(new admin_setting_heading('bookingform_cost_header', get_string('costheading', 'bookingform'), ''));

$settings->add(new admin_setting_configcheckbox(
    'bookingform_hidecost',
    get_string('setting:hidecost_caption', 'bookingform'),
    get_string('setting:hidecost', 'bookingform'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'bookingform_hidediscount',
    get_string('setting:hidediscount_caption', 'bookingform'),
    get_string('setting:hidediscount', 'bookingform'),
    0
));

$settings->add(new admin_setting_heading('bookingform_icalendar_header', get_string('icalendarheading', 'bookingform'), ''));

$settings->add(new admin_setting_configcheckbox(
    'bookingform_oneemailperday',
    get_string('setting:oneemailperday_caption', 'bookingform'),
    get_string('setting:oneemailperday', 'bookingform'),
    0
));

$settings->add(new admin_setting_configcheckbox(
    'bookingform_disableicalcancel',
    get_string('setting:disableicalcancel_caption', 'bookingform'),
    get_string('setting:disableicalcancel', 'bookingform'),
    0
));

// List of existing custom fields.
$html  = bookingform_list_of_customfields();
$html .= html_writer::start_tag('p');
$url   = new moodle_url('/mod/bookingform/customfield.php', array('id' => 0));
$html .= html_writer::link($url, get_string('addnewfieldlink', 'bookingform'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('bookingform_customfields_header', get_string('customfieldsheading', 'bookingform'), $html));

// List of existing site notices.
$html  = bookingform_list_of_sitenotices();
$html .= html_writer::start_tag('p');
$url  = new moodle_url('/mod/bookingform/sitenotice.php', array('id' => 0));
$html .= html_writer::link($url, get_string('addnewnoticelink', 'bookingform'));
$html .= html_writer::end_tag('p');

$settings->add(new admin_setting_heading('bookingform_sitenotices_header', get_string('sitenoticesheading', 'bookingform'), $html));
