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

// Backticks here are Markdown code fences around the JSON proposal, not shell commands.
// phpcs:disable moodle.Strings.ForbiddenStrings.Found

use core_ai\aiactions\responses\response_generate_text;
use core_external\external_api;

/**
 * Tests for the AI assistant's web services, with core AI replaced by a mock.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ai_sources::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ai_turn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ai_apply::class)]
final class ai_test extends \advanced_testcase {
    /** @var string a valid proposal */
    private const FORM = '{"name": "Essay form", "description": "Use **it**.", "criteria": [
        {"description": "Content", "levels": [{"score": 0, "definition": "**Weak**"}, {"score": 2, "definition": "Strong"}]},
        {"description": "Style", "levels": [{"score": 0, "definition": "Poor"}, {"score": 1, "definition": "Good"}]}
    ]}';

    /**
     * Creates an assignment with a grading area, as a teacher who accepted the AI policy.
     *
     * @return array [areaid, teacher, assignment cm, course]
     */
    private function setup_area(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $assign = $generator->create_module('assign', ['course' => $course->id, 'intro' => 'Write an essay.']);
        $generator->create_module('page', ['course' => $course->id, 'name' => 'Guide', 'content' => 'Cite sources.']);
        $manager = get_grading_manager(\context_module::instance($assign->cmid), 'mod_assign', 'submissions');
        $manager->set_active_method('matrix');
        $this->setUser($teacher);
        \core_ai\manager::user_policy_accepted($teacher->id, \context_system::instance()->id);
        set_config('enableai', 1, 'gradingform_matrix');
        $areaid = (int)$DB->get_field('grading_areas', 'id', ['contextid' => $manager->get_context()->id,
            'component' => 'mod_assign', 'areaname' => 'submissions'], MUST_EXIST);
        return [$areaid, $teacher, $assign, $course];
    }

    /**
     * Replaces core AI with a mock that gives the replies in order and records the prompts.
     *
     * @param string[] $replies
     * @param bool $available
     * @return \ArrayObject the prompts sent
     */
    private function mock_core_ai(array $replies, bool $available = true): \ArrayObject {
        $prompts = new \ArrayObject();
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->method('is_action_available')->willReturn($available);
        $manager->method('is_action_enabled_in_context')->willReturn($available);
        $manager->method('process_action')->willReturnCallback(function ($action) use (&$replies, $prompts) {
            $prompts[] = $action->get_configuration('prompttext');
            $response = new response_generate_text(success: true);
            $response->set_response_data(['generatedcontent' => array_shift($replies)]);
            return $response;
        });
        \core\di::set(\core_ai\manager::class, fn() => $manager);
        return $prompts;
    }

    public function test_sources(): void {
        [$areaid, , $assign] = $this->setup_area();
        $this->mock_core_ai([]);

        $result = external_api::clean_returnvalue(ai_sources::execute_returns(), ai_sources::execute($areaid));
        $this->assertTrue($result['available']);
        $this->assertTrue($result['policyaccepted']);
        $this->assertTrue($result['canapply']);
        $this->assertSame((int)$assign->cmid, $result['sources'][0]['cmid']);
        $this->assertTrue($result['sources'][0]['graded']);
        $this->assertSame(\core_text::strlen('Write an essay.'), $result['sources'][0]['length']);
        $this->assertSame('Guide', $result['sources'][1]['name']);
    }

    public function test_turn_returns_message_proposal_and_preview(): void {
        [$areaid, , $assign] = $this->setup_area();
        $prompts = $this->mock_core_ai(["Here is a draft.\n```json\n" . self::FORM . "\n```"]);

        $result = external_api::clean_returnvalue(
            ai_turn::execute_returns(),
            ai_turn::execute($areaid, 'Draft a form', [(int)$assign->cmid], [], '')
        );

        $this->assertSame('Here is a draft.', $result['message']);
        $this->assertSame('', $result['error']);
        $this->assertSame('Essay form', json_decode($result['proposal'], true)['name']);
        $this->assertStringContainsString('gradingform_matrix', $result['preview']);
        $this->assertStringContainsString('<strong>Weak</strong>', $result['preview']);
        $this->assertStringContainsString('Write an essay.', $prompts[0]);
    }

    public function test_turn_sends_history_and_current_proposal(): void {
        [$areaid] = $this->setup_area();
        $prompts = $this->mock_core_ai(['Two levels are enough here.']);

        $result = ai_turn::execute(
            $areaid,
            'Is that enough levels?',
            [],
            [['instruction' => 'Draft a form', 'reply' => 'Here is a draft.']],
            self::FORM
        );

        $this->assertSame('', $result['proposal']);
        $this->assertStringContainsString("Teacher: Draft a form\nYou: Here is a draft.", $prompts[0]);
        $this->assertStringContainsString('"description": "Content"', $prompts[0]);
    }

    public function test_turn_rejects_an_invalid_proposal_from_the_browser(): void {
        [$areaid] = $this->setup_area();
        $this->mock_core_ai([]);
        $this->expectException(\moodle_exception::class);
        ai_turn::execute($areaid, 'Change it', [], [], '{"criteria": []}');
    }

    public function test_turn_requires_policy(): void {
        [$areaid, , , $course] = $this->setup_area();
        $this->mock_core_ai([]);
        // A teacher who has not accepted the AI policy.
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aipolicynotaccepted', 'gradingform_matrix'));
        ai_turn::execute($areaid, 'Draft a form');
    }

    public function test_turn_requires_an_ai_provider(): void {
        [$areaid] = $this->setup_area();
        $this->mock_core_ai([], false);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aiunavailable', 'gradingform_matrix'));
        ai_turn::execute($areaid, 'Draft a form');
    }

    public function test_site_switch_turns_the_assistant_off(): void {
        [$areaid] = $this->setup_area();
        $this->mock_core_ai([]);
        set_config('enableai', 0, 'gradingform_matrix');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('aidisabled', 'gradingform_matrix'));
        ai_sources::execute($areaid);
    }

    public function test_capability_is_required(): void {
        [$areaid, , , $course] = $this->setup_area();
        $this->mock_core_ai([]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        ai_sources::execute($areaid);
    }

    public function test_apply_saves_a_draft(): void {
        [$areaid] = $this->setup_area();
        $this->mock_core_ai([]);

        $result = ai_apply::execute($areaid, self::FORM);
        $this->assertSame(2, $result['criteria']);

        $controller = get_grading_manager($areaid)->get_controller('matrix');
        $definition = $controller->get_definition();
        $this->assertSame('Essay form', $definition->name);
        // The Markdown description is stored as HTML, so it opens in the text editor.
        $this->assertEquals(FORMAT_HTML, $definition->descriptionformat);
        $this->assertStringContainsString('<strong>it</strong>', $definition->description);
        $this->assertEquals(\gradingform_controller::DEFINITION_STATUS_DRAFT, $definition->status);
        $this->assertCount(2, $definition->matrix_criteria);
    }
}
