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
 * Sums the scores of the selected levels and scales the total to the grade range.
 *
 * Same calculation as core rubric. With 'lockzeropoints' the total is a fraction of the maximum
 * score, clamped to the minimum grade; without it the minimum possible score maps to the minimum grade.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class level_sum_calculator implements grade_calculator {
    /**
     * Returns the lowest and highest possible total score.
     *
     * Criteria without levels contribute nothing.
     *
     * @param array $criteria definition criteria keyed by id, each with 'levels' keyed by id
     * @return array ['minscore' => float, 'maxscore' => float]
     */
    public static function score_bounds(array $criteria): array {
        $min = 0;
        $max = 0;
        foreach ($criteria as $criterion) {
            if (empty($criterion['levels'])) {
                continue;
            }
            $scores = array_column($criterion['levels'], 'score');
            $min += min($scores);
            $max += max($scores);
        }
        return ['minscore' => $min, 'maxscore' => $max];
    }

    /**
     * Returns the share of the maximum grade that the lowest possible total gives.
     *
     * The highest possible total always gives the maximum grade. Without 'lockzeropoints'
     * the lowest total always gives the minimum grade, so a form whose lowest levels are
     * worth points still gives 0.
     *
     * The editor's live summary (amd/src/local/scoremapping.js) repeats this calculation.
     *
     * @param array $bounds from {@see self::score_bounds()}
     * @param bool $lockzeropoints the 'lockzeropoints' option
     * @return float|null fraction from 0 to 1, or null if no grade can be calculated
     */
    public static function lowest_grade_fraction(array $bounds, bool $lockzeropoints): ?float {
        if ($bounds['maxscore'] <= $bounds['minscore']) {
            return null;
        }
        if (!$lockzeropoints) {
            return 0.0;
        }
        if ($bounds['maxscore'] <= 0) {
            return null;
        }
        return max(0.0, $bounds['minscore'] / $bounds['maxscore']);
    }

    /**
     * Returns the total score of the selected levels.
     *
     * A filling whose level no longer exists counts as zero.
     *
     * @param array $criteria
     * @param array $fillings
     * @return float
     */
    public static function total_score(array $criteria, array $fillings): float {
        $total = 0;
        foreach ($fillings as $criterionid => $filling) {
            $total += $criteria[$criterionid]['levels'][$filling['levelid'] ?? 0]['score'] ?? 0;
        }
        return $total;
    }

    /**
     * Calculates the grade.
     *
     * @param array $criteria definition criteria keyed by id, each with 'levels' keyed by id
     * @param array $fillings fillings keyed by criterion id
     * @param array $graderange grade values allowed by the activity
     * @param array $options definition options; uses 'lockzeropoints'
     * @param bool $allowdecimals whether the grade may have decimals
     * @return float|int the grade, or -1 if no grade can be calculated
     */
    public function calculate(
        array $criteria,
        array $fillings,
        array $graderange,
        array $options,
        bool $allowdecimals
    ): float|int {
        $bounds = self::score_bounds($criteria);
        if ($bounds['maxscore'] <= $bounds['minscore'] || empty($graderange)) {
            return -1;
        }
        $mingrade = min($graderange);
        $maxgrade = max($graderange);
        $score = self::total_score($criteria, $fillings);

        if (!empty($options['lockzeropoints'])) {
            $grade = max($mingrade, $score / $bounds['maxscore'] * $maxgrade);
            return $allowdecimals ? $grade : round($grade, 0);
        }
        $offset = ($score - $bounds['minscore']) / ($bounds['maxscore'] - $bounds['minscore']) * ($maxgrade - $mingrade);
        return ($allowdecimals ? $offset : round($offset, 0)) + $mingrade;
    }
}
