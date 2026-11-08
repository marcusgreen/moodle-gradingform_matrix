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
 * Site settings for the Matrix grading method.
 *
 * Moodle core does not load settings.php for grading methods (core\plugininfo\gradingform has no
 * load_settings()), so this file is loaded by {@see \gradingform_matrix\local\plugin_settings} and shown on
 * admin.php. It is written the standard way, so it will also work if core starts loading it.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'gradingform_matrix/enableai',
        new lang_string('enableai', 'gradingform_matrix'),
        new lang_string('enableai_desc', 'gradingform_matrix'),
        0
    ));
}
