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
 * Unit tests for the level sum calculator.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(level_sum_calculator::class)]
final class level_sum_calculator_test extends \basic_testcase {
    /**
     * Builds criteria keyed 1..n, levels keyed criterion*10 + index.
     *
     * @param array ...$scores one array of level scores per criterion
     * @return array
     */
    private function criteria(array ...$scores): array {
        $criteria = [];
        foreach ($scores as $i => $levels) {
            $cid = $i + 1;
            foreach ($levels as $j => $score) {
                $criteria[$cid]['levels'][$cid * 10 + $j] = ['id' => $cid * 10 + $j, 'score' => $score];
            }
        }
        return $criteria;
    }

    public function test_score_bounds(): void {
        $criteria = $this->criteria([2, 0, 1], [-1, 3]);
        $criteria[3] = ['levels' => []];
        $this->assertEquals(['minscore' => -1, 'maxscore' => 5], level_sum_calculator::score_bounds($criteria));
    }

    public function test_total_score_ignores_missing_levels(): void {
        $criteria = $this->criteria([0, 1, 2], [0, 1, 2]);
        $this->assertEquals(3, level_sum_calculator::total_score($criteria, [1 => ['levelid' => 12], 2 => ['levelid' => 21]]));
        $this->assertEquals(2, level_sum_calculator::total_score($criteria, [1 => ['levelid' => 12], 2 => ['levelid' => 999]]));
    }

    /**
     * Lowest grade fraction cases.
     *
     * @return array
     */
    public static function lowest_grade_fraction_provider(): array {
        return [
            'locked, lowest levels worth points' => [1, 4, true, 0.25],
            'locked, zero levels' => [0, 4, true, 0.0],
            'locked, negative minimum clamped' => [-2, 4, true, 0.0],
            'locked, no positive maximum' => [-4, 0, true, null],
            'unlocked, lowest levels worth points' => [1, 4, false, 0.0],
            'unlocked, negative scores only' => [-4, -1, false, 0.0],
            'no spread' => [2, 2, true, null],
            'no spread, unlocked' => [2, 2, false, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lowest_grade_fraction_provider')]
    public function test_lowest_grade_fraction(float $min, float $max, bool $lockzero, ?float $expected): void {
        $bounds = ['minscore' => $min, 'maxscore' => $max];
        $this->assertSame($expected, level_sum_calculator::lowest_grade_fraction($bounds, $lockzero));
    }

    /**
     * The fraction matches the grade calculate() gives the lowest levels.
     */
    public function test_lowest_grade_fraction_matches_calculate(): void {
        $criteria = $this->criteria([1, 2, 3], [0, 1, 2]);
        $fillings = [1 => ['levelid' => 10], 2 => ['levelid' => 20]];
        $bounds = level_sum_calculator::score_bounds($criteria);
        foreach ([0, 1] as $lockzero) {
            $options = ['lockzeropoints' => $lockzero];
            $grade = (new level_sum_calculator())->calculate($criteria, $fillings, range(0, 100), $options, true);
            $this->assertEqualsWithDelta(
                level_sum_calculator::lowest_grade_fraction($bounds, (bool)$lockzero) * 100,
                $grade,
                0.0001
            );
        }
    }

    /**
     * Calculation cases.
     *
     * @return array
     */
    public static function calculate_provider(): array {
        $points = range(0, 100);
        return [
            'locked, three quarters' => [[[0, 1, 2], [0, 1, 2]], [12, 21], $points, 1, false, 75],
            'locked, negative total clamped' => [[[-1, 0, 2], [-1, 0, 2]], [10, 20], $points, 1, false, 0],
            'locked, rounded' => [[[0, 1, 2], [0, 1, 2]], [11, 20], range(0, 10), 1, false, 3],
            'locked, decimals' => [[[0, 1, 2], [0, 1, 2]], [11, 20], range(0, 10), 1, true, 2.5],
            'unlocked, minimum maps to minimum' => [[[1, 2, 3], [1, 2, 3]], [10, 20], $points, 0, false, 0],
            'unlocked, middle' => [[[1, 2, 3], [1, 2, 3]], [11, 21], $points, 0, false, 50],
            'unlocked, scale' => [[[0, 1, 2], [0, 1, 2]], [12, 21], [1, 2, 3, 4], 0, false, 3],
            'no spread' => [[[1, 1]], [10], $points, 1, false, -1],
            'no grade range' => [[[0, 1]], [11], [], 1, false, -1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('calculate_provider')]
    public function test_calculate(
        array $scores,
        array $selected,
        array $range,
        int $lockzero,
        bool $decimals,
        float|int $expected
    ): void {
        $criteria = $this->criteria(...$scores);
        $fillings = [];
        foreach ($selected as $levelid) {
            $fillings[intdiv($levelid, 10)] = ['levelid' => $levelid];
        }
        $grade = (new level_sum_calculator())->calculate(
            $criteria,
            $fillings,
            $range,
            ['lockzeropoints' => $lockzero],
            $decimals
        );
        $this->assertEquals($expected, $grade);
    }
}
