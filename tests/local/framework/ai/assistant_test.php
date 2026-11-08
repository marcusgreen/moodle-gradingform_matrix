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

use gradingform_matrix\local\framework\definition_importer;

/**
 * Unit tests for the assistant, with a scripted backend.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(assistant::class)]
final class assistant_test extends \basic_testcase {
    /** @var string a valid proposal */
    private const FORM = '```json
{"name": "Essay", "criteria": [{"description": "Content", "levels": [{"score": 0, "definition": "Weak"},
{"score": 1, "definition": "Strong"}]}]}
```';

    /**
     * Returns a backend that gives the replies in order and records the prompts.
     *
     * @param string[] $replies
     * @return backend
     */
    private function backend(array $replies): backend {
        return new class ($replies) implements backend {
            /** @var string[] prompts received */
            public array $prompts = [];

            /**
             * Constructor.
             *
             * @param string[] $replies
             */
            public function __construct(
                /** @var string[] replies still to give */
                private array $replies,
            ) {
            }

            #[\Override]
            public function is_available(): bool {
                return true;
            }

            #[\Override]
            public function send(string $prompt): string {
                $this->prompts[] = $prompt;
                return array_shift($this->replies);
            }
        };
    }

    /**
     * Returns an assistant.
     *
     * @param backend $backend
     * @return assistant
     */
    private function assistant(backend $backend): assistant {
        $importer = new definition_importer(['sortlevelsasc' => 1], 'gradingform_matrix');
        return new assistant($backend, new prompt_builder(), new reply_parser($importer));
    }

    public function test_turn_with_proposal(): void {
        $backend = $this->backend(["Here is a draft.\n" . self::FORM]);
        $result = $this->assistant($backend)->ask('Draft a form', ['Brief' => 'Write an essay.'], [], null);

        $this->assertCount(1, $backend->prompts);
        $this->assertSame($backend->prompts[0], $result['prompt']);
        $this->assertStringContainsString('Write an essay.', $result['prompt']);
        $this->assertSame('Here is a draft.', $result['message']);
        $this->assertSame('Essay', $result['proposal']['name']);
        $this->assertNull($result['error']);
    }

    public function test_invalid_form_is_repaired_once(): void {
        $backend = $this->backend([
            "Draft:\n```json\n{\"name\": \"Essay\", \"criteria\": []}\n```",
            "Draft:\n" . self::FORM,
        ]);
        $result = $this->assistant($backend)->ask('Draft a form', [], [], null);

        $this->assertCount(2, $backend->prompts);
        $this->assertStringStartsWith($backend->prompts[0], $backend->prompts[1]);
        $this->assertStringContainsString(get_string('err_nocriteria', 'gradingform_matrix'), $backend->prompts[1]);
        $this->assertSame('Essay', $result['proposal']['name']);
        $this->assertNull($result['error']);
    }

    public function test_error_is_reported_when_the_repair_fails(): void {
        $invalid = "```json\n{\"name\": \"Essay\", \"criteria\": []}\n```";
        $backend = $this->backend([$invalid, $invalid]);
        $result = $this->assistant($backend)->ask('Draft a form', [], [], null);

        $this->assertCount(2, $backend->prompts);
        $this->assertNull($result['proposal']);
        $this->assertSame(get_string('err_nocriteria', 'gradingform_matrix'), $result['error']);
    }

    public function test_question_without_form_needs_no_repair(): void {
        $backend = $this->backend(['Four levels suit a longer essay.']);
        $result = $this->assistant($backend)->ask('How many levels?', [], [], null);

        $this->assertCount(1, $backend->prompts);
        $this->assertSame('Four levels suit a longer essay.', $result['message']);
        $this->assertNull($result['proposal']);
    }
}
