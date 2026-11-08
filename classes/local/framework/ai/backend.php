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
 * Sends a prompt to a language model and returns its reply.
 *
 * None of the supported AI systems takes a list of messages, so a conversation is sent as one prompt.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface backend {
    /**
     * Whether a request can be sent at all, for example whether an AI provider is enabled.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Sends a prompt.
     *
     * @param string $prompt
     * @return string the model's reply
     * @throws \moodle_exception if the request fails or the reply is empty
     */
    public function send(string $prompt): string;
}
