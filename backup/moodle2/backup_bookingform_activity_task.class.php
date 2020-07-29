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

require_once($CFG->dirroot . '/mod/bookingform/backup/moodle2/backup_bookingform_stepslib.php'); // Because it exists (must).

/**
 * Bookingform backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_bookingform_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {

        // Bookingform only has one structure step.
        $this->add_step(new backup_bookingform_activity_structure_step('bookingform_structure', 'bookingform.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of bookingforms.
        $search = "/(" . $base . "\/mod\/bookingform\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@BOOKINGFORMINDEX*$2@$', $content);

        // Link to bookingform view by moduleid.
        $search = "/(" . $base . "\/mod\/bookingform\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@BOOKINGFORMVIEWBYID*$2@$', $content);

        return $content;
    }
}
