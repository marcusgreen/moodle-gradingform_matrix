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

namespace gradingform_matrix\local\framework\ai;

use gradingform_matrix\local\framework\definition_exporter;
use gradingform_matrix\local\framework\definition_importer;
use moodle_exception;

/**
 * Splits a model's reply into its message and the proposed grading form, and validates the form.
 *
 * The form is checked by the same importer as an uploaded file, so a proposal is held to the same rules.
 * The model may not set display options or add files: those are dropped before validation.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reply_parser {
    /**
     * Constructor.
     *
     * @param definition_importer $importer
     */
    public function __construct(
        /** @var definition_importer validates the proposed form */
        protected readonly definition_importer $importer,
    ) {
    }

    /**
     * Parses a reply.
     *
     * @param string $reply
     * @return array ['message' => string, 'proposal' => array|null, 'error' => string|null], where proposal is
     *     ['name' => string|null, 'description' => string|null, 'criteria' => list] and error is set when the
     *     reply holds a form that is not valid
     */
    public function parse(string $reply): array {
        $block = $this->find_block($reply);
        if ($block === null) {
            return ['message' => trim($reply), 'proposal' => null, 'error' => null];
        }
        [$json, $start, $length] = $block;
        $message = trim(substr($reply, 0, $start) . substr($reply, $start + $length));
        try {
            return ['message' => $message, 'proposal' => $this->validate($json), 'error' => null];
        } catch (moodle_exception $e) {
            return ['message' => $message, 'proposal' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Finds the JSON block: a fenced block, or failing that a reply that is only a JSON object.
     *
     * @param string $reply
     * @return array|null [json, offset of the block in the reply, length of the block]
     */
    protected function find_block(string $reply): ?array {
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $reply, $matches, PREG_OFFSET_CAPTURE)) {
            return [$matches[1][0], $matches[0][1], strlen($matches[0][0])];
        }
        $trimmed = trim($reply);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return [$trimmed, 0, strlen($reply)];
        }
        return null;
    }

    /**
     * Validates a proposed form, such as one the browser sends back.
     *
     * @param string $json
     * @return array ['name' => string|null, 'description' => string|null, 'criteria' => list]
     * @throws moodle_exception if the form is not valid
     */
    public function validate(string $json): array {
        $data = json_decode($json, true);
        if (is_array($data)) {
            unset($data['options'], $data['files']);
            $data['formatversion'] = definition_exporter::FORMAT_VERSION;
            $data['descriptionformat'] = FORMAT_MARKDOWN;
            $json = json_encode($data);
        }
        $imported = $this->importer->from_json($json);
        return [
            'name' => $imported['name'],
            'description' => $imported['description'],
            'criteria' => $imported['criteria'],
        ];
    }
}
