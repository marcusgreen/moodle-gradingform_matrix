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

namespace gradingform_matrix\local\framework;

use core_external\external_format_value;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service structures of a level-based definition and of its fillings.
 *
 * Core's grading web services ask the controller for these, see
 * {@see \gradingform_controller::get_external_definition_details()}.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_structures {
    /**
     * The definition details returned by core_grading_external::get_definitions().
     *
     * @param string $method name of the grading method
     * @return array a single key, {method}_criteria, with the criteria structure
     */
    public static function definition(string $method): array {
        $criteria = new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'criterion id', VALUE_OPTIONAL),
                'sortorder' => new external_value(PARAM_INT, 'sortorder', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, 'description', VALUE_OPTIONAL),
                'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                'levels' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                        'score' => new external_value(PARAM_FLOAT, 'score', VALUE_OPTIONAL),
                        'definition' => new external_value(PARAM_RAW, 'definition', VALUE_OPTIONAL),
                        'definitionformat' => new external_format_value('definition', VALUE_OPTIONAL),
                    ]),
                    'levels',
                    VALUE_OPTIONAL
                ),
            ]),
            'definition details',
            VALUE_OPTIONAL
        );
        return [$method . '_criteria' => $criteria];
    }

    /**
     * The structure of a filling, returned by core_grading_external::get_gradingform_instances().
     *
     * @return array a single key, criteria, with the filling structure
     */
    public static function filling(): array {
        $criteria = new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'filling id'),
                    'criterionid' => new external_value(PARAM_INT, 'criterion id'),
                    'levelid' => new external_value(PARAM_INT, 'level id', VALUE_OPTIONAL),
                    'remark' => new external_value(PARAM_RAW, 'remark', VALUE_OPTIONAL),
                    'remarkformat' => new external_format_value('remark', VALUE_OPTIONAL),
                ]
            ),
            'filling',
            VALUE_OPTIONAL
        );
        return ['criteria' => $criteria];
    }
}
