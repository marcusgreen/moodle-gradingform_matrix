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
 * Site settings for how the grading form is laid out.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_settings {
    /**
     * Whether the form is stacked to fit narrow spaces, such as the assignment grader's side panel.
     *
     * On unless an administrator turns it off. When off the form keeps its grid and scrolls sideways,
     * except when it is popped out.
     *
     * @return bool
     */
    public static function stack_narrow(): bool {
        $value = get_config('gradingform_matrix', 'stacknarrow');
        // Not set yet, on a site that installed the plugin before the setting existed: use the default.
        return $value === false || !empty($value);
    }
}
