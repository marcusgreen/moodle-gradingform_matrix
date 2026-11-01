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
 * Turns a filled-in grading form into a grade.
 *
 * Implementations must be pure: no database access, formatting or plugin lookups.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface grade_calculator {
    /**
     * Calculates the grade.
     *
     * @param array $criteria definition criteria keyed by id, each with 'levels' keyed by id
     * @param array $fillings fillings keyed by criterion id
     * @param array $graderange grade values allowed by the activity (the keys of get_grade_range())
     * @param array $options definition options
     * @param bool $allowdecimals whether the grade may have decimals
     * @return float|int the grade, or -1 if no grade can be calculated (core's convention)
     */
    public function calculate(
        array $criteria,
        array $fillings,
        array $graderange,
        array $options,
        bool $allowdecimals
    ): float|int;
}
