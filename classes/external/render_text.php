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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use moodle_exception;

/**
 * Renders criterion and level texts as Markdown, for the editor's display of cells not being edited.
 *
 * Uses the same formatting as the rest of the plugin, so the editor shows what graders and students will see.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class render_text extends external_api {
    /** @var int most texts in one request */
    public const MAX_TEXTS = 500;

    /** @var int longest text accepted, in characters */
    public const MAX_LENGTH = 20000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context of the grading form'),
            'texts' => new external_multiple_structure(new external_value(PARAM_RAW, 'Markdown text')),
        ]);
    }

    /**
     * Renders the texts.
     *
     * @param int $contextid
     * @param array $texts
     * @return array HTML, in the same order
     */
    public static function execute(int $contextid, array $texts): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'texts'));
        $context = \context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('moodle/grade:managegradingforms', $context);
        if (count($params['texts']) > self::MAX_TEXTS) {
            throw new moodle_exception('aiinvalidrequest', 'gradingform_matrix');
        }
        return array_map(function (string $text) use ($context): string {
            if (\core_text::strlen($text) > self::MAX_LENGTH) {
                throw new moodle_exception('aiinvalidrequest', 'gradingform_matrix');
            }
            return format_text($text, FORMAT_MARKDOWN, ['context' => $context]);
        }, $params['texts']);
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_value(PARAM_RAW, 'The text as HTML'));
    }
}
