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
use gradingform_matrix\local\framework\ai\content_source;
use gradingform_matrix\local\framework\ai\llm_backend;
use gradingform_matrix_controller;
use moodle_exception;

/**
 * Checks shared by the AI assistant's web services.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class ai_base extends external_api {
    /**
     * Loads the grading area and checks that the user may use the assistant on it.
     *
     * @param int $areaid
     * @return array [gradingform_matrix_controller, \context]
     */
    protected static function setup(int $areaid): array {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        // The controller and renderer classes are not autoloaded.
        require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
        $manager = get_grading_manager($areaid);
        $context = $manager->get_context();
        self::validate_context($context);
        if (!\gradingform_matrix\local\ai_settings::enabled()) {
            throw new moodle_exception('aidisabled', 'gradingform_matrix');
        }
        require_capability('moodle/grade:managegradingforms', $context);
        require_capability('gradingform/matrix:useaiassistant', $context);
        /** @var gradingform_matrix_controller $controller */
        $controller = $manager->get_controller(gradingform_matrix_controller::METHOD);
        return [$controller, $context];
    }

    /**
     * The backend for the area's context.
     *
     * @param \context $context
     * @return llm_backend
     */
    protected static function backend(\context $context): llm_backend {
        return new llm_backend($context->id, 'gradingform_matrix');
    }

    /**
     * Whether the user has accepted the AI policy, which only core AI requires.
     *
     * @param llm_backend $backend
     * @return bool
     */
    protected static function policy_accepted(llm_backend $backend): bool {
        global $USER;
        return $backend->get_backend() !== llm_backend::CORE_AI || \core_ai\manager::get_user_policy_status($USER->id);
    }

    /**
     * Fails unless a request can be sent.
     *
     * @param llm_backend $backend
     */
    protected static function require_available(llm_backend $backend): void {
        if (!$backend->is_available()) {
            throw new moodle_exception('aiunavailable', 'gradingform_matrix');
        }
        if (!self::policy_accepted($backend)) {
            throw new moodle_exception('aipolicynotaccepted', 'gradingform_matrix');
        }
    }

    /**
     * The course content the user may read, for the area's course.
     *
     * @param \context $context
     * @return content_source
     */
    protected static function content_source(\context $context): content_source {
        $coursecontext = $context->get_course_context();
        $cmid = $context->contextlevel == CONTEXT_MODULE ? (int)$context->instanceid : 0;
        return new content_source(get_fast_modinfo($coursecontext->instanceid), $cmid);
    }
}
