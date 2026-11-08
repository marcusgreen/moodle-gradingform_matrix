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

/**
 * Runs one turn of a conversation about a grading form.
 *
 * Keeps no state: the caller passes the earlier turns and the current proposal each time, as with
 * local_sqlchat. When a reply holds a form that is not valid, the error is sent back for one repair attempt
 * before it is reported.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assistant {
    /**
     * Constructor.
     *
     * @param backend $backend
     * @param prompt_builder $builder
     * @param reply_parser $parser
     */
    public function __construct(
        /** @var backend sends prompts */
        protected readonly backend $backend,
        /** @var prompt_builder builds prompts */
        protected readonly prompt_builder $builder,
        /** @var reply_parser reads replies */
        protected readonly reply_parser $parser,
    ) {
    }

    /**
     * Runs one turn.
     *
     * @param string $instruction the teacher's new instruction
     * @param array $sources course material, title => plain text
     * @param array $history earlier turns, oldest first, each ['instruction' => string, 'reply' => string]
     * @param array|null $proposal the form proposed so far, or null
     * @return array ['message' => string, 'proposal' => array|null, 'error' => string|null, 'prompt' => string];
     *     proposal is null when the reply proposed no form or an invalid one, and error is set in the second case
     * @throws \moodle_exception if the request fails
     */
    public function ask(string $instruction, array $sources, array $history, ?array $proposal): array {
        $prompt = $this->builder->build($instruction, $sources, $history, $proposal);
        $result = $this->parser->parse($this->backend->send($prompt));
        if ($result['error'] !== null) {
            $repair = $prompt . "\n\nYour JSON block was rejected: {$result['error']}\n"
                . "Reply again with the same message and a corrected JSON block holding the whole form.";
            $result = $this->parser->parse($this->backend->send($repair));
        }
        return $result + ['prompt' => $prompt];
    }
}
