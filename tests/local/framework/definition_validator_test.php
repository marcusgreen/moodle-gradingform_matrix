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
 * Unit tests for the definition validator.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(definition_validator::class)]
final class definition_validator_test extends \basic_testcase {
    /** @var array options with zero points not locked */
    private const UNLOCKED = ['lockzeropoints' => null];

    /**
     * A criterion with a level for each score.
     *
     * @param array $scores
     * @param string $description
     * @return array
     */
    private static function criterion(array $scores, string $description = 'Criterion'): array {
        $levels = [];
        foreach ($scores as $i => $score) {
            $levels['NEWID' . $i] = ['definition' => 'Level ' . $i, 'score' => $score];
        }
        return ['description' => $description, 'levels' => $levels];
    }

    public function test_valid_definition(): void {
        $criteria = ['NEWID1' => self::criterion([0, 2, 4]), 'NEWID2' => self::criterion([0, 1])];
        $result = (new definition_validator())->validate($criteria, self::UNLOCKED);
        $this->assertSame([], $result['errors']);
        $this->assertSame($criteria, $result['criteria']);
    }

    public function test_no_criteria(): void {
        $result = (new definition_validator())->validate([], self::UNLOCKED);
        $this->assertSame(['err_nocriteria', 'err_totalscore', 'err_novariations'], $result['errors']);
    }

    public function test_empty_texts_are_marked(): void {
        $criteria = ['NEWID1' => self::criterion([0, 2], ' ')];
        $criteria['NEWID1']['levels']['NEWID1']['definition'] = '';
        $result = (new definition_validator())->validate($criteria, self::UNLOCKED);

        $this->assertSame(['err_nodefinition', 'err_nodescription'], $result['errors']);
        $this->assertTrue($result['criteria']['NEWID1']['error_description']);
        $this->assertTrue($result['criteria']['NEWID1']['levels']['NEWID1']['error_definition']);
        $this->assertArrayNotHasKey('error_definition', $result['criteria']['NEWID1']['levels']['NEWID0']);
    }

    public function test_bad_score_is_marked(): void {
        $criteria = ['NEWID1' => self::criterion([0, 'abc'])];
        $result = (new definition_validator())->validate($criteria, self::UNLOCKED);

        $this->assertContains('err_scoreformat', $result['errors']);
        $this->assertTrue($result['criteria']['NEWID1']['levels']['NEWID1']['error_score']);
        $this->assertArrayNotHasKey('error_score', $result['criteria']['NEWID1']['levels']['NEWID0']);
    }

    public function test_criterion_needs_two_levels(): void {
        $criteria = ['NEWID1' => self::criterion([0, 2]), 'NEWID2' => self::criterion([3])];
        $result = (new definition_validator())->validate($criteria, self::UNLOCKED);

        $this->assertSame(['err_mintwolevels'], $result['errors']);
        $this->assertTrue($result['criteria']['NEWID2']['error_levels']);
        $this->assertArrayNotHasKey('error_levels', $result['criteria']['NEWID1']);
    }

    public function test_total_score_must_be_positive(): void {
        $criteria = ['NEWID1' => self::criterion([0, 0]), 'NEWID2' => self::criterion([-2, 0])];
        $result = (new definition_validator())->validate($criteria, ['lockzeropoints' => 1]);
        $this->assertSame(['err_totalscore'], $result['errors']);
    }

    public function test_levels_must_vary_unless_zero_points_locked(): void {
        $criteria = ['NEWID1' => self::criterion([2, 2]), 'NEWID2' => self::criterion([1, 1])];
        $validator = new definition_validator();

        $this->assertSame(['err_novariations'], $validator->validate($criteria, self::UNLOCKED)['errors']);
        $this->assertSame([], $validator->validate($criteria, ['lockzeropoints' => 1])['errors']);
    }

    public function test_decimal_scores(): void {
        $criteria = ['NEWID1' => self::criterion(['0', '1.5'])];
        $this->assertSame([], (new definition_validator())->validate($criteria, self::UNLOCKED)['errors']);
    }
}
