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
use gradingform_matrix_controller;
use moodle_exception;

/**
 * Runs one turn of the AI assistant's conversation.
 *
 * The browser keeps the conversation and the current proposal, and sends them with each turn. The proposal is
 * validated again here, because it comes from the browser.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_turn extends ai_base {
    /** @var int longest instruction accepted, in characters */
    public const MAX_INSTRUCTION = 2000;

    /** @var int most sources accepted */
    public const MAX_SOURCES = 50;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'areaid' => new external_value(PARAM_INT, 'Grading area id'),
            'instruction' => new external_value(PARAM_TEXT, 'The teacher\'s new instruction'),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'),
                'Course modules to read',
                VALUE_DEFAULT,
                []
            ),
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'instruction' => new external_value(PARAM_TEXT, 'An earlier instruction'),
                    'reply' => new external_value(PARAM_TEXT, 'The reply to it'),
                ]),
                'Earlier turns, oldest first',
                VALUE_DEFAULT,
                []
            ),
            'proposal' => new external_value(PARAM_RAW, 'The current proposal as JSON, or empty', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Runs the turn.
     *
     * @param int $areaid
     * @param string $instruction
     * @param array $cmids
     * @param array $history
     * @param string $proposal
     * @return array
     */
    public static function execute(
        int $areaid,
        string $instruction,
        array $cmids = [],
        array $history = [],
        string $proposal = ''
    ): array {
        global $PAGE;
        $params = self::validate_parameters(self::execute_parameters(), compact(
            'areaid',
            'instruction',
            'cmids',
            'history',
            'proposal'
        ));
        [$controller, $context] = self::setup($params['areaid']);
        $instruction = trim($params['instruction']);
        if (
            $instruction === '' || \core_text::strlen($instruction) > self::MAX_INSTRUCTION
                || count($params['cmids']) > self::MAX_SOURCES
        ) {
            throw new moodle_exception('aiinvalidrequest', 'gradingform_matrix');
        }
        $backend = self::backend($context);
        self::require_available($backend);

        $assistant = $controller->ai_assistant($backend);
        $current = null;
        if ($params['proposal'] !== '') {
            $current = $controller->ai_reply_parser()->validate($params['proposal']);
        } else if ($controller->is_form_defined()) {
            $current = self::saved_form($controller);
        }
        $sources = self::content_source($context)->extract_many($params['cmids']);
        $result = $assistant->ask($instruction, $sources, $params['history'], $current);

        $renderer = $PAGE->get_renderer('gradingform_matrix');
        return [
            'message' => $result['message'],
            'proposal' => $result['proposal'] ? json_encode($result['proposal'], JSON_UNESCAPED_UNICODE) : '',
            'preview' => $result['proposal'] ? self::preview($controller, $renderer, $result['proposal']) : '',
            'error' => (string)$result['error'],
            'prompt' => debugging('', DEBUG_DEVELOPER) ? $result['prompt'] : '',
        ];
    }

    /**
     * The saved form, in the proposal shape.
     *
     * @param gradingform_matrix_controller $controller
     * @return array
     */
    protected static function saved_form(gradingform_matrix_controller $controller): array {
        $export = json_decode($controller->export_definition(), true);
        return ['name' => $export['name'], 'description' => $export['description'], 'criteria' => $export['criteria']];
    }

    /**
     * Renders a proposal as the form would be previewed.
     *
     * @param gradingform_matrix_controller $controller
     * @param \gradingform_matrix_renderer $renderer
     * @param array $proposal
     * @return string HTML
     */
    protected static function preview(
        gradingform_matrix_controller $controller,
        \gradingform_matrix_renderer $renderer,
        array $proposal
    ): string {
        $criteria = [];
        $levelid = 1;
        foreach ($proposal['criteria'] as $index => $criterion) {
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $levels[$levelid++] = ['score' => $level['score'], 'definition' => $level['definition']];
            }
            $criteria[$index + 1] = [
                'sortorder' => $index + 1,
                'description' => $criterion['description'],
                'levels' => $levels,
            ];
        }
        $html = '';
        if (!empty($proposal['description'])) {
            $html .= \html_writer::div(format_text(
                $proposal['description'],
                FORMAT_MARKDOWN,
                ['context' => $controller->get_context()]
            ), 'gradingform_matrix-description');
        }
        return $html . $renderer->display_matrix(
            $criteria,
            $controller->get_options(),
            gradingform_matrix_controller::DISPLAY_PREVIEW,
            'aipreview'
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'message' => new external_value(PARAM_RAW, 'The assistant\'s message, plain text'),
            'proposal' => new external_value(PARAM_RAW, 'The proposed form as JSON, or empty'),
            'preview' => new external_value(PARAM_RAW, 'The proposed form rendered as HTML, or empty'),
            'error' => new external_value(PARAM_TEXT, 'Why a proposed form was rejected, or empty'),
            'prompt' => new external_value(PARAM_RAW, 'The prompt sent, in developer debugging mode only'),
        ]);
    }
}
