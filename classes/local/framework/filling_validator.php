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
 * Checks submitted grading data on the server, for both the form and web services.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filling_validator {
    /**
     * Whether every criterion has a level selected that belongs to that criterion.
     *
     * @param array $criteria definition criteria keyed by id, each with 'levels' keyed by id
     * @param mixed $submitted submitted element value
     * @return bool
     */
    public function is_complete(array $criteria, $submitted): bool {
        if (!isset($submitted['criteria']) || !is_array($submitted['criteria'])) {
            return false;
        }
        if (count($submitted['criteria']) < count($criteria)) {
            return false;
        }
        foreach ($criteria as $id => $criterion) {
            $levelid = $submitted['criteria'][$id]['levelid'] ?? null;
            if ($levelid === null || !array_key_exists($levelid, $criterion['levels'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Keeps only the submitted values that fit the definition, for saving.
     *
     * Criteria that are not in the definition are dropped, and so is a levelid that is not one of its
     * criterion's levels. Incomplete fillings are kept, because drafts are saved before grading is finished.
     * Needed because web services save fillings without {@see is_complete()} being called first.
     *
     * @param array $criteria definition criteria keyed by id, each with 'levels' keyed by id
     * @param mixed $submitted submitted element value
     * @return array submitted values keyed by criterion id
     */
    public function filter(array $criteria, $submitted): array {
        if (!isset($submitted['criteria']) || !is_array($submitted['criteria'])) {
            return [];
        }
        $filtered = [];
        foreach ($submitted['criteria'] as $id => $values) {
            if (!array_key_exists($id, $criteria) || !is_array($values)) {
                continue;
            }
            $levelid = $values['levelid'] ?? null;
            if ($levelid !== null && (!is_scalar($levelid) || !array_key_exists($levelid, $criteria[$id]['levels']))) {
                unset($values['levelid']);
            }
            $filtered[$id] = $values;
        }
        return $filtered;
    }

    /**
     * Whether nothing was selected or written for any criterion.
     *
     * @param array $criteria
     * @param mixed $submitted
     * @return bool
     */
    public function is_empty(array $criteria, $submitted): bool {
        foreach (array_keys($criteria) as $id) {
            if (isset($submitted['criteria'][$id]['levelid']) || !empty($submitted['criteria'][$id]['remark'])) {
                return false;
            }
        }
        return true;
    }
}
