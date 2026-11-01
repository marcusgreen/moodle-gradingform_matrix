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
 * Unit tests for the filling validator.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(filling_validator::class)]
final class filling_validator_test extends \basic_testcase {
    /** @var array two criteria with two levels each */
    private const CRITERIA = [
        1 => ['levels' => [10 => [], 11 => []]],
        2 => ['levels' => [20 => [], 21 => []]],
    ];

    public function test_is_complete(): void {
        $validator = new filling_validator();
        $this->assertTrue($validator->is_complete(
            self::CRITERIA,
            ['criteria' => [1 => ['levelid' => 10], 2 => ['levelid' => 21]]]
        ));
        // Level of another criterion.
        $this->assertFalse($validator->is_complete(
            self::CRITERIA,
            ['criteria' => [1 => ['levelid' => 10], 2 => ['levelid' => 11]]]
        ));
        $this->assertFalse($validator->is_complete(self::CRITERIA, ['criteria' => [1 => ['levelid' => 10]]]));
        $this->assertFalse($validator->is_complete(
            self::CRITERIA,
            ['criteria' => [1 => ['levelid' => 10], 3 => ['levelid' => 20]]]
        ));
        $this->assertFalse($validator->is_complete(self::CRITERIA, []));
        $this->assertFalse($validator->is_complete(self::CRITERIA, ['criteria' => 'x']));
    }

    public function test_is_empty(): void {
        $validator = new filling_validator();
        $this->assertTrue($validator->is_empty(self::CRITERIA, []));
        $this->assertTrue($validator->is_empty(self::CRITERIA, ['criteria' => [1 => ['remark' => '']]]));
        $this->assertFalse($validator->is_empty(self::CRITERIA, ['criteria' => [1 => ['remark' => 'x']]]));
        $this->assertFalse($validator->is_empty(self::CRITERIA, ['criteria' => [2 => ['levelid' => 20]]]));
    }
}
