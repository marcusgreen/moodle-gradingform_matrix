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

use core_ai\aiactions\generate_text;
use moodle_exception;

/**
 * Sends prompts through Moodle's core AI subsystem, or through local_ai_manager or tool_aimanager when installed.
 *
 * Follows local_sqlchat's llm_bridge: when the configured backend's plugin is not installed it falls back to
 * core AI, so no AI plugin is ever a hard dependency. Core AI is reached through dependency injection, so
 * tests can replace it.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class llm_backend implements backend {
    /** @var string Moodle's core AI subsystem. */
    public const CORE_AI = 'core_ai_subsystem';

    /** @var string local_ai_manager, used by ByCS/mebis. */
    public const LOCAL_AI_MANAGER = 'local_ai_manager';

    /** @var string tool_aimanager (AIConnect). */
    public const TOOL_AIMANAGER = 'tool_aimanager';

    /** @var string the backend actually used, after falling back */
    protected readonly string $backend;

    /**
     * Constructor.
     *
     * @param int $contextid context the requests are made in
     * @param string $component component that holds the error strings, and that local_ai_manager logs requests for
     * @param string $backend requested backend, one of the constants
     * @param string $purpose purpose passed to local_ai_manager
     */
    public function __construct(
        /** @var int context the requests are made in */
        protected readonly int $contextid,
        /** @var string component that holds the error strings */
        protected readonly string $component,
        string $backend = self::CORE_AI,
        /** @var string purpose passed to local_ai_manager */
        protected readonly string $purpose = 'feedback',
    ) {
        $this->backend = self::resolve($backend);
    }

    /**
     * The backend that will be used for a requested one.
     *
     * @param string $backend
     * @return string
     */
    public static function resolve(string $backend): string {
        if ($backend === self::LOCAL_AI_MANAGER && class_exists(\local_ai_manager\manager::class)) {
            return $backend;
        }
        if ($backend === self::TOOL_AIMANAGER && class_exists(\tool_aiconnect\ai\ai::class)) {
            return $backend;
        }
        return self::CORE_AI;
    }

    /**
     * The backend in use.
     *
     * @return string
     */
    public function get_backend(): string {
        return $this->backend;
    }

    #[\Override]
    public function is_available(): bool {
        if ($this->backend !== self::CORE_AI) {
            // These plugins check availability themselves when a request is made.
            return true;
        }
        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->is_action_available(generate_text::class)) {
            return false;
        }
        $context = \context::instance_by_id($this->contextid);
        if ($context->contextlevel == CONTEXT_MODULE) {
            // An activity's AI settings only list the actions of the course assistance tools, never
            // generate_text, so is_action_enabled_in_context() would always say no once they are saved.
            // Respect the switches for the course and the activity instead.
            $fields = \core_ai\manager::get_ai_fields_from_course_module($context->instanceid);
            return \core_ai\manager::is_ai_tools_enabled_in_course($context)
                && ($fields->enableaitools === null || (bool)$fields->enableaitools);
        }
        return $manager->is_action_enabled_in_context($context, generate_text::class);
    }

    #[\Override]
    public function send(string $prompt): string {
        $content = match ($this->backend) {
            self::LOCAL_AI_MANAGER => $this->send_local_ai_manager($prompt),
            self::TOOL_AIMANAGER => $this->send_tool_aimanager($prompt),
            default => $this->send_core_ai($prompt),
        };
        if (trim($content) === '') {
            throw new moodle_exception('aierrorempty', $this->component);
        }
        return $content;
    }

    /**
     * Sends a prompt through core AI.
     *
     * @param string $prompt
     * @return string
     */
    protected function send_core_ai(string $prompt): string {
        global $USER;
        $action = new generate_text(contextid: $this->contextid, userid: $USER->id, prompttext: $prompt);
        $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        if (!$response->get_success()) {
            throw new moodle_exception(
                'aierror',
                $this->component,
                '',
                s($response->get_errormessage()),
                'code=' . $response->get_errorcode() . ' error=' . $response->get_error()
            );
        }
        return (string)($response->get_response_data()['generatedcontent'] ?? '');
    }

    /**
     * Sends a prompt through local_ai_manager.
     *
     * @param string $prompt
     * @return string
     */
    protected function send_local_ai_manager(string $prompt): string {
        $response = (new \local_ai_manager\manager($this->purpose))->perform_request($prompt, $this->component, $this->contextid);
        if ($response->get_code() !== 200) {
            throw new moodle_exception(
                'aierror',
                $this->component,
                '',
                s($response->get_errormessage()),
                'code=' . $response->get_code() . ' ' . $response->get_debuginfo()
            );
        }
        return (string)$response->get_content();
    }

    /**
     * Sends a prompt through tool_aimanager.
     *
     * @param string $prompt
     * @return string
     */
    protected function send_tool_aimanager(string $prompt): string {
        $response = (new \tool_aiconnect\ai\ai())->prompt_completion($prompt);
        $content = $response['response']['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            $error = $response['response']['error']['message'] ?? $response['error'] ?? '';
            throw new moodle_exception('aierror', $this->component, '', s((string)$error));
        }
        return (string)$content;
    }
}
