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
 * Unit tests for the prompt builder.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(prompt_builder::class)]
final class prompt_builder_test extends \basic_testcase {
    public function test_build_includes_every_part_in_order(): void {
        $builder = new prompt_builder('- Method rule.');
        $proposal = ['name' => 'Essay', 'description' => null, 'criteria' => [
            ['description' => 'Content', 'levels' => [['score' => 0.0, 'definition' => 'Weak']]],
        ]];
        $prompt = $builder->build(
            'Make it stricter',
            ['Brief' => 'Write 500 words.'],
            [['instruction' => 'Draft a form', 'reply' => 'Here is a draft.']],
            $proposal
        );

        $positions = array_map(fn($needle) => strpos($prompt, $needle), [
            'Rules:',
            '- Method rule.',
            "=== Course material: Brief ===\nWrite 500 words.",
            '"description": "Content"',
            "Teacher: Draft a form\nYou: Here is a draft.",
            "The teacher's new instruction:\nMake it stricter",
        ]);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_build_leaves_out_empty_parts(): void {
        $prompt = (new prompt_builder())->build('Draft a form', [], [], null);
        $this->assertStringNotContainsString('Course material', $prompt);
        $this->assertStringNotContainsString('current grading form', $prompt);
        $this->assertStringNotContainsString('conversation so far', $prompt);
        $this->assertStringEndsWith("The teacher's new instruction:\nDraft a form", $prompt);
    }

    public function test_history_keeps_the_last_turns_truncated(): void {
        $history = [];
        for ($i = 1; $i <= 6; $i++) {
            $history[] = ['instruction' => "Instruction {$i}", 'reply' => "Reply {$i}"];
        }
        $history[] = ['instruction' => str_repeat('x', 1000), 'reply' => ''];
        $prompt = (new prompt_builder())->build('Next', [], $history, null);

        $this->assertStringNotContainsString('Instruction 3', $prompt);
        $this->assertStringContainsString('Instruction 4', $prompt);
        $this->assertStringContainsString(str_repeat('x', prompt_builder::MAX_TURN_LENGTH - 1) . '…', $prompt);
        $this->assertStringNotContainsString(str_repeat('x', prompt_builder::MAX_TURN_LENGTH), $prompt);
    }

    public function test_fit_sources_shares_the_budget(): void {
        $builder = new prompt_builder('', 100);
        $fitted = $builder->fit_sources([
            'long' => str_repeat('a', 200),
            'short' => str_repeat('b', 10),
            'medium' => str_repeat('c', 60),
        ]);

        // The order is kept, short sources are untouched, and the rest is shared.
        $this->assertSame(['long', 'short', 'medium'], array_keys($fitted));
        $this->assertSame(str_repeat('b', 10), $fitted['short']);
        $this->assertSame(45, \core_text::strlen($fitted['medium']));
        $this->assertSame(45, \core_text::strlen($fitted['long']));
        $this->assertStringEndsWith('…', $fitted['long']);
        $total = array_sum(array_map(fn($text) => \core_text::strlen($text), $fitted));
        $this->assertLessThanOrEqual(100, $total);
    }
}
