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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use gradingform_matrix\local\framework\ai\prompt_builder;

/**
 * Lists the course content the AI assistant can read, and whether it can be used.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_sources extends ai_base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'areaid' => new external_value(PARAM_INT, 'Grading area id'),
        ]);
    }

    /**
     * Lists the sources.
     *
     * @param int $areaid
     * @return array
     */
    public static function execute(int $areaid): array {
        ['areaid' => $areaid] = self::validate_parameters(self::execute_parameters(), ['areaid' => $areaid]);
        [$controller, $context] = self::setup($areaid);
        $backend = self::backend($context);
        $source = self::content_source($context);
        $modinfo = get_fast_modinfo($context->get_course_context()->instanceid);

        $sources = [];
        foreach ($source->list() as $item) {
            $item['length'] = \core_text::strlen($source->extract($modinfo->get_cm($item['cmid'])));
            $sources[] = $item;
        }
        return [
            'available' => $backend->is_available(),
            'policyaccepted' => self::policy_accepted($backend),
            'canapply' => $controller->can_import(),
            'budget' => prompt_builder::SOURCE_BUDGET,
            'sources' => $sources,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Whether an AI request can be sent'),
            'policyaccepted' => new external_value(PARAM_BOOL, 'Whether the user has accepted the AI policy'),
            'canapply' => new external_value(PARAM_BOOL, 'Whether a proposal can replace the form'),
            'budget' => new external_value(PARAM_INT, 'Limit for all sources together, in characters'),
            'sources' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module type'),
                'type' => new external_value(PARAM_TEXT, 'Module type, as shown to users'),
                'section' => new external_value(PARAM_TEXT, 'Section name'),
                'graded' => new external_value(PARAM_BOOL, 'Whether this is the activity being graded'),
                'length' => new external_value(PARAM_INT, 'Length of its text, in characters'),
            ])),
        ]);
    }
}
