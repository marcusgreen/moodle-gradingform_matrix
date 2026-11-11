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
 * Finds the user each graded item belongs to.
 *
 * Core stores an item id for each grading instance, and what it points at depends on the
 * activity: in an assignment it is an assign_grades id, in a forum a forum_grades id. Core
 * has no API for this, so the known areas are listed here. Add an area by extending
 * {@see TABLES} in a subclass.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_resolver {
    /** Grade table, with id and userid fields, for each "component/area". */
    protected const TABLES = [
        'mod_assign/submissions' => 'assign_grades',
        'mod_forum/forum' => 'forum_grades',
    ];

    /**
     * Constructor.
     *
     * @param string $component the grading area's component, for example mod_assign
     * @param string $area the grading area's name, for example submissions
     */
    public function __construct(
        /** @var string the grading area's component */
        protected readonly string $component,
        /** @var string the grading area's name */
        protected readonly string $area,
    ) {
    }

    /**
     * Whether items in this area can be matched to users.
     *
     * @return bool
     */
    public function supported(): bool {
        return $this->table() !== null;
    }

    /**
     * Returns the user each item belongs to.
     *
     * @param int[] $itemids
     * @return int[] item id => user id; items that are not found, or a whole unsupported area, are left out
     */
    public function user_ids(array $itemids): array {
        global $DB;
        $table = $this->table();
        if ($table === null || !$itemids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($itemids);
        return array_map('intval', $DB->get_records_select_menu($table, "id $insql", $params, '', 'id, userid'));
    }

    /**
     * Returns the grade table for this area.
     *
     * @return string|null
     */
    protected function table(): ?string {
        return static::TABLES[$this->component . '/' . $this->area] ?? null;
    }
}
