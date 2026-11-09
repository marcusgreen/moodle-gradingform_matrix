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
 * Checks a matrix definition being edited before it can be made ready.
 *
 * Runs on the criteria after the editor has applied any add, move and delete buttons,
 * so deleted criteria and levels are no longer present and do not count.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class definition_validator {
    /**
     * Validates the criteria and marks the fields that are wrong.
     *
     * Marked fields get 'error_description' or 'error_levels' on the criterion and
     * 'error_definition' or 'error_score' on the level, which the renderer highlights.
     *
     * @param array $criteria criteria keyed by id, each with 'description' and 'levels' keyed by id,
     *     each level with 'definition' and 'score'
     * @param array $options definition options, 'lockzeropoints' is used
     * @return array ['criteria' => the criteria with error flags, 'errors' => list of gradingform_matrix error string ids]
     */
    public function validate(array $criteria, array $options): array {
        $errors = [];
        if (!$criteria) {
            $errors['err_nocriteria'] = true;
        }

        $overallminscore = $overallmaxscore = 0;
        foreach ($criteria as $id => $criterion) {
            $levels = $criterion['levels'] ?? [];
            $minscore = $maxscore = null;
            foreach ($levels as $levelid => $level) {
                $score = unformat_float($level['score'], true);
                if (!strlen(trim($level['definition']))) {
                    $errors['err_nodefinition'] = true;
                    $levels[$levelid]['error_definition'] = true;
                }
                if ($score === null || $score === false) {
                    $errors['err_scoreformat'] = true;
                    $levels[$levelid]['error_score'] = true;
                }
                if ($minscore === null || $score < $minscore) {
                    $minscore = $score;
                }
                if ($maxscore === null || $score > $maxscore) {
                    $maxscore = $score;
                }
            }
            $criteria[$id]['levels'] = $levels;
            if (count($levels) < 2) {
                $errors['err_mintwolevels'] = true;
                $criteria[$id]['error_levels'] = true;
            }
            if (!strlen(trim($criterion['description']))) {
                $errors['err_nodescription'] = true;
                $criteria[$id]['error_description'] = true;
            }
            $overallminscore += (float)$minscore;
            $overallmaxscore += (float)$maxscore;
        }

        if ($overallmaxscore <= 0) {
            $errors['err_totalscore'] = true;
        }
        if (empty($options['lockzeropoints']) && $overallminscore == $overallmaxscore) {
            $errors['err_novariations'] = true;
        }

        return ['criteria' => $criteria, 'errors' => array_keys($errors)];
    }
}
