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
use core_ai\aiactions\responses\response_generate_text;

/**
 * Unit tests for the backend, with core AI replaced by a mock.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(llm_backend::class)]
final class llm_backend_test extends \advanced_testcase {
    /**
     * Replaces core AI with a mock that gives the response.
     *
     * @param response_generate_text $response
     * @param bool $available
     * @return \core_ai\manager the mock
     */
    private function mock_core_ai(response_generate_text $response, bool $available = true): \core_ai\manager {
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('process_action')->willReturn($response);
        $manager->method('is_action_available')->willReturn($available);
        $manager->method('is_action_enabled_in_context')->willReturn($available);
        \core\di::set(\core_ai\manager::class, fn() => $manager);
        return $manager;
    }

    public function test_send_through_core_ai(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $response = new response_generate_text(success: true);
        $response->set_response_data(['generatedcontent' => 'A reply']);
        $manager = $this->mock_core_ai($response);
        $manager->expects($this->once())->method('process_action')
            ->with($this->callback(fn($action) => $action instanceof generate_text));

        $backend = new llm_backend(\context_system::instance()->id, 'gradingform_matrix');
        $this->assertSame(llm_backend::CORE_AI, $backend->get_backend());
        $this->assertTrue($backend->is_available());
        $this->assertSame('A reply', $backend->send('A prompt'));
    }

    public function test_failed_request(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->mock_core_ai(new response_generate_text(
            success: false,
            errorcode: 500,
            error: 'Internal server error',
            errormessage: 'Provider down'
        ));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aierror', 'gradingform_matrix', 'Provider down'));
        (new llm_backend(\context_system::instance()->id, 'gradingform_matrix'))->send('A prompt');
    }

    public function test_empty_reply(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $response = new response_generate_text(success: true);
        $response->set_response_data(['generatedcontent' => "  \n"]);
        $this->mock_core_ai($response);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aierrorempty', 'gradingform_matrix'));
        (new llm_backend(\context_system::instance()->id, 'gradingform_matrix'))->send('A prompt');
    }

    public function test_not_available(): void {
        $this->resetAfterTest();
        $this->mock_core_ai(new response_generate_text(success: true), false);
        $this->assertFalse((new llm_backend(\context_system::instance()->id, 'gradingform_matrix'))->is_available());
    }

    public function test_availability_in_an_activity(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $contextid = \context_module::instance($page->cmid)->id;
        $this->mock_core_ai(new response_generate_text(success: true));
        $backend = new llm_backend($contextid, 'gradingform_matrix');

        // Saved activity AI settings that only name the course assistance actions.
        $DB->update_record('course_modules', ['id' => $page->cmid, 'enableaitools' => 1,
            'enabledaiactions' => json_encode(['summarise_text' => 0, 'explain_text' => 0])]);
        $this->assertTrue($backend->is_available());

        $DB->set_field('course_modules', 'enableaitools', 0, ['id' => $page->cmid]);
        $this->assertFalse($backend->is_available());

        $DB->set_field('course_modules', 'enableaitools', 1, ['id' => $page->cmid]);
        $DB->set_field('course', 'enableaitools', 0, ['id' => $course->id]);
        $this->assertFalse($backend->is_available());
    }

    public function test_missing_backend_falls_back_to_core_ai(): void {
        $this->assertSame(llm_backend::CORE_AI, llm_backend::resolve('no_such_backend'));
        $this->assertSame(llm_backend::CORE_AI, llm_backend::resolve(llm_backend::TOOL_AIMANAGER));
        $this->assertSame(
            class_exists(\local_ai_manager\manager::class) ? llm_backend::LOCAL_AI_MANAGER : llm_backend::CORE_AI,
            llm_backend::resolve(llm_backend::LOCAL_AI_MANAGER)
        );
    }
}
