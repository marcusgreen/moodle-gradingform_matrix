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

namespace gradingform_matrix\local\framework;

/**
 * Unit tests for the definition diff.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(definition_diff::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(change_summary::class)]
final class definition_diff_test extends \basic_testcase {
    /** @var array one criterion with levels scored 0, 1 and 2 */
    private const CRITERIA = [
        5 => ['id' => 5, 'sortorder' => 1, 'description' => 'A', 'descriptionformat' => 0, 'levels' => [
            50 => ['id' => 50, 'score' => 0.0, 'definition' => 'a0', 'definitionformat' => 0],
            51 => ['id' => 51, 'score' => 1.0, 'definition' => 'a1', 'definitionformat' => 0],
            52 => ['id' => 52, 'score' => 2.0, 'definition' => 'a2', 'definitionformat' => 0],
        ]],
    ];

    /**
     * Compares the stored criteria with a modified copy.
     *
     * @param callable $modify receives and returns the criteria
     * @param bool $lockzeropoints submitted lockzeropoints option
     * @return change_summary
     */
    private function compare(callable $modify, bool $lockzeropoints = true): change_summary {
        $definition = (object)['name' => 'n', 'status' => 20, 'usermodified' => 2];
        return (new definition_diff())->compare(
            $definition,
            self::CRITERIA,
            true,
            clone $definition,
            $modify(self::CRITERIA),
            $lockzeropoints,
            2
        );
    }

    public function test_no_change(): void {
        $summary = $this->compare(fn($c) => $c);
        $this->assertFalse($summary->has_changes());
        $this->assertSame(change_level::NONE, $summary->level());
    }

    public function test_change_kinds(): void {
        $this->assertSame(change_level::TEXT, $this->compare(function ($c) {
            $c[5]['description'] = 'B';
            return $c;
        })->level());

        $summary = $this->compare(function ($c) {
            $c[5]['levels']['NEWID1'] = ['score' => 1.5, 'definition' => 'x'];
            return $c;
        });
        $this->assertSame(change_level::LEVELS_ADDED_SAME_MAX, $summary->level());

        $this->assertSame(change_level::SCORES_CHANGED, $this->compare(function ($c) {
            $c[5]['levels']['NEWID1'] = ['score' => 3, 'definition' => 'x'];
            return $c;
        })->level());

        $this->assertSame(change_level::SCORES_CHANGED, $this->compare(fn($c) => $c, false)->level());

        $summary = $this->compare(function ($c) {
            unset($c[5]['levels'][50]);
            $c[5]['description'] = 'B';
            return $c;
        });
        $this->assertSame(change_level::LEVELS_REMOVED, $summary->level());
        $this->assertTrue($summary->contains(change_level::TEXT));
        $this->assertFalse($summary->contains(change_level::SCORES_CHANGED));

        $this->assertSame(change_level::CRITERIA_ADDED, $this->compare(function ($c) {
            $c['NEWID1'] = ['description' => 'New', 'levels' => ['NEWID1' => ['score' => 0]]];
            return $c;
        })->level());

        $this->assertSame(change_level::SCORES_CHANGED, $this->compare(fn($c) => [])->level());
    }

    public function test_definition_fields_and_author(): void {
        $current = (object)['name' => 'n', 'usermodified' => 2];
        $diff = new definition_diff();
        $this->assertSame(
            change_level::TEXT,
            $diff->compare($current, [], true, (object)['name' => 'm'], [], true, 2)->level()
        );
        $this->assertSame(
            change_level::TEXT,
            $diff->compare($current, [], true, (object)['name' => 'n'], [], true, 3)->level()
        );
    }
}
