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

namespace gradingform_matrix\local;

/**
 * Loads, saves and applies the defaults of the settings in this plugin's settings.php.
 *
 * Moodle core does not load settings.php for grading methods, so the plugin does it itself and shows the
 * settings on admin.php. settings.php may use $settings and $ADMIN->fulltree, as in any plugin, but must not
 * add pages to the admin tree.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_settings {
    /** @var string name of the settings page, as core would name it */
    public const SECTION = 'gradingformsettingmatrix';

    /**
     * The settings page, built from settings.php.
     *
     * @return \admin_settingpage
     */
    public static function page(): \admin_settingpage {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $settings = new \admin_settingpage(
            self::SECTION,
            new \lang_string('pluginsettings', 'gradingform_matrix'),
            'moodle/site:config'
        );
        // The only part of the admin tree that settings.php may use.
        $ADMIN = (object)['fulltree' => true];
        $hassiteconfig = true;
        include($CFG->dirroot . '/grade/grading/form/matrix/settings.php');
        return $settings;
    }

    /**
     * Saves submitted values, as the admin settings page would.
     *
     * Settings without a submitted value are left unchanged.
     *
     * @param array $data submitted form data, keyed by each setting's full name (s_gradingform_matrix_...)
     * @return array errors keyed by full name; empty when everything was saved
     */
    public static function save(array $data): array {
        $errors = [];
        foreach (self::page()->settings as $setting) {
            $name = $setting->get_full_name();
            if (!array_key_exists($name, $data)) {
                continue;
            }
            $error = $setting->write_setting($data[$name]);
            if ($error !== '') {
                $errors[$name] = $error;
            }
        }
        return $errors;
    }

    /**
     * Stores the default of every setting that has no value yet.
     *
     * Core does this for settings in the admin tree during install and upgrade; these settings are not in it.
     */
    public static function apply_defaults(): void {
        foreach (self::page()->settings as $setting) {
            if ($setting->get_setting() === null) {
                $setting->write_setting($setting->get_defaultsetting());
            }
        }
    }

    /**
     * The settings page.
     *
     * @param \moodle_url|null $returnurl where to go after saving
     * @return \moodle_url
     */
    public static function url(?\moodle_url $returnurl = null): \moodle_url {
        return new \moodle_url(
            '/grade/grading/form/matrix/admin.php',
            $returnurl ? ['returnurl' => $returnurl->out_as_local_url(false)] : []
        );
    }
}
