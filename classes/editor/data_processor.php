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

/**
 * Contains the form data processing logic for the matrix rubric editor element.
 *
 * Extracted from MoodleQuickForm_matrixeditor so that data processing
 * (button handling, validation, sorting) is separate from display orchestration.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_matrix\editor;

defined('MOODLE_INTERNAL') || die();

use gradingform_matrix_controller;

/**
 * Processes form data submitted from the rubric editor element.
 *
 * Handles button presses (addcriterion, addlevel, moveup, movedown, delete, duplicate)
 * that are normally handled by JavaScript but must also work without it.
 * After calling prepare(), callers can read the public state properties.
 */
class data_processor {

    /**
     * @var bool True if a non-submit (JS) button was detected in the submitted data.
     *           Such buttons are meant to be handled client-side; when they reach the
     *           server it means the user has JavaScript disabled.
     */
    public bool $nonjsbuttonpressed = false;

    /**
     * @var string|false|null Validation result after prepare() is called with $withvalidation=true.
     *                         null  = validation not yet run
     *                         false = validation passed (no errors)
     *                         string = HTML error message(s)
     */
    public $validationerrors = null;

    /**
     * Processes submitted rubric editor form data.
     *
     * - Handles non-JS button presses (add/delete/move criteria and levels).
     * - Fills missing options with their defaults.
     * - Optionally validates all criteria and levels, recording errors in $validationerrors.
     *
     * @param array|null $value Raw submitted value from the form element; null means read from element.
     * @param bool $withvalidation Whether to run validation and populate $validationerrors.
     * @return array Processed data array with keys 'criteria' and 'options' (and any extra keys).
     */
    public function prepare(?array $value, bool $withvalidation = false): array {
        $this->nonjsbuttonpressed = false;

        $totalscore = 0;
        $errors = [];
        $return = ['criteria' => [], 'options' => gradingform_matrix_controller::get_default_options()];

        if (!isset($value['criteria'])) {
            $value['criteria'] = [];
            $errors['err_nocriteria'] = 1;
        }

        // If options are present in $value, replace default values with submitted values.
        if (!empty($value['options'])) {
            foreach (array_keys($return['options']) as $option) {
                // Special treatment for checkboxes.
                if (!empty($value['options'][$option])) {
                    $return['options'][$option] = $value['options'][$option];
                } else {
                    $return['options'][$option] = null;
                }
            }
        }

        if (is_array($value)) {
            // For other array keys of $value no special treatment needed; copy them as-is.
            foreach (array_keys($value) as $key) {
                if ($key != 'options' && $key != 'criteria') {
                    $return[$key] = $value[$key];
                }
            }
        }

        // Iterate through criteria.
        $lastaction = null;
        $lastid     = null;
        $overallminscore = $overallmaxscore = 0;

        foreach ($value['criteria'] as $id => $criterion) {
            if ($id == 'addcriterion') {
                $id        = $this->get_next_id(array_keys($value['criteria']));
                $criterion = ['description' => '', 'levels' => []];
                $i         = 0;
                // When adding a new criterion, copy the number of levels and their scores from the last criterion.
                if (!empty($value['criteria'][$lastid]['levels'])) {
                    foreach ($value['criteria'][$lastid]['levels'] as $lastlevel) {
                        $criterion['levels']['NEWID' . ($i++)]['score'] = $lastlevel['score'];
                    }
                } else {
                    $criterion['levels']['NEWID' . ($i++)]['score'] = 0;
                }
                // Add more levels so there are at least 3 in the new criterion.
                for ($i = $i; $i < 3; $i++) {
                    $criterion['levels']['NEWID' . $i]['score'] = $criterion['levels']['NEWID' . ($i - 1)]['score'] + 1;
                }
                // Set other required fields (definition) for the levels in the new criterion.
                foreach (array_keys($criterion['levels']) as $i) {
                    $criterion['levels'][$i]['definition'] = '';
                }
                $this->nonjsbuttonpressed = true;
            }

            $levels   = [];
            $minscore = $maxscore = null;

            if (array_key_exists('levels', $criterion)) {
                foreach ($criterion['levels'] as $levelid => $level) {
                    if ($levelid == 'addlevel') {
                        $levelid = $this->get_next_id(array_keys($criterion['levels']));
                        $level   = ['definition' => '', 'score' => 0];
                        foreach ($criterion['levels'] as $lastlevel) {
                            if (isset($lastlevel['score'])) {
                                $level['score'] = max($level['score'], ceil(unformat_float($lastlevel['score'])) + 1);
                            }
                        }
                        $this->nonjsbuttonpressed = true;
                    }
                    if (!array_key_exists('delete', $level)) {
                        $score = unformat_float($level['score'], true);
                        if ($withvalidation) {
                            if (!strlen(trim($level['definition']))) {
                                $errors['err_nodefinition'] = 1;
                                $level['error_definition']  = true;
                            }
                            if ($score === null || $score === false) {
                                $errors['err_scoreformat'] = 1;
                                $level['error_score']      = true;
                            }
                        }
                        $levels[$levelid] = $level;
                        if ($minscore === null || $score < $minscore) {
                            $minscore = $score;
                        }
                        if ($maxscore === null || $score > $maxscore) {
                            $maxscore = $score;
                        }
                    } else {
                        $this->nonjsbuttonpressed = true;
                    }
                }
            }

            $totalscore            += (float)$maxscore;
            $criterion['levels']    = $levels;

            if ($withvalidation && !array_key_exists('delete', $criterion)) {
                if (count($levels) < 2) {
                    $errors['err_mintwolevels']    = 1;
                    $criterion['error_levels']     = true;
                }
                if (!strlen(trim($criterion['description']))) {
                    $errors['err_nodescription']   = 1;
                    $criterion['error_description'] = true;
                }
                $overallmaxscore += $maxscore;
                $overallminscore += $minscore;
            }

            if (array_key_exists('moveup', $criterion) || $lastaction == 'movedown') {
                unset($criterion['moveup']);
                if ($lastid !== null) {
                    $lastcriterion = $return['criteria'][$lastid];
                    unset($return['criteria'][$lastid]);
                    $return['criteria'][$id]     = $criterion;
                    $return['criteria'][$lastid] = $lastcriterion;
                } else {
                    $return['criteria'][$id] = $criterion;
                }
                $lastaction = null;
                $lastid     = $id;
                $this->nonjsbuttonpressed = true;
            } else if (array_key_exists('delete', $criterion)) {
                $this->nonjsbuttonpressed = true;
            } else {
                if (array_key_exists('movedown', $criterion)) {
                    unset($criterion['movedown']);
                    $lastaction = 'movedown';
                    $this->nonjsbuttonpressed = true;
                }
                $return['criteria'][$id] = $criterion;
                $lastid = $id;
            }
        }

        if ($totalscore <= 0) {
            $errors['err_totalscore'] = 1;
        }

        // Add sort order field to criteria.
        $csortorder = 1;
        foreach (array_keys($return['criteria']) as $id) {
            $return['criteria'][$id]['sortorder'] = $csortorder++;
        }

        // Create validation error string (if needed).
        if ($withvalidation) {
            if (!$return['options']['lockzeropoints']) {
                if ($overallminscore == $overallmaxscore) {
                    $errors['err_novariations'] = 1;
                }
            }
            if (count($errors)) {
                $rv = [];
                foreach ($errors as $error => $v) {
                    $rv[] = get_string($error, 'gradingform_matrix');
                }
                $this->validationerrors = join('<br/ >', $rv);
            } else {
                $this->validationerrors = false;
            }
        }

        return $return;
    }

    /**
     * Scans array $ids to find the biggest element matching NEWID*, increments it by 1 and returns it.
     *
     * @param array $ids Array of existing id strings.
     * @return string Next available NEWID string.
     */
    protected function get_next_id(array $ids): string {
        $maxid = 0;
        foreach ($ids as $id) {
            if (preg_match('/^NEWID(\d+)$/', $id, $matches) && ((int)$matches[1]) > $maxid) {
                $maxid = (int)$matches[1];
            }
        }
        return 'NEWID' . ($maxid + 1);
    }
}
