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

namespace gradingform_matrix\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Replaces the grading form with a proposal from the AI assistant, saved as a draft.
 *
 * Goes through the same path as importing a file, so it is refused once the form has been used for grading.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_apply extends ai_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'areaid' => new external_value(PARAM_INT, 'Grading area id'),
            'proposal' => new external_value(PARAM_RAW, 'The proposed form as JSON'),
        ]);
    }

    /**
     * Applies the proposal.
     *
     * @param int $areaid
     * @param string $proposal
     * @return array
     */
    public static function execute(int $areaid, string $proposal): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('areaid', 'proposal'));
        [$controller] = self::setup($params['areaid']);
        $form = $controller->ai_reply_parser()->validate($params['proposal']);
        $controller->import_definition([
            'name' => $form['name'],
            'description' => $form['description'],
            'descriptionformat' => $form['description'] === null ? null : FORMAT_MARKDOWN,
            'options' => null,
            'criteria' => $form['criteria'],
            'files' => [],
        ], get_string('aiformname', 'gradingform_matrix'));
        return [
            'criteria' => count($form['criteria']),
            'editurl' => (new \moodle_url('/grade/grading/form/matrix/edit.php', ['areaid' => $params['areaid']]))->out(false),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'criteria' => new external_value(PARAM_INT, 'Number of criteria applied'),
            'editurl' => new external_value(PARAM_URL, 'Where to continue editing'),
        ]);
    }
}
