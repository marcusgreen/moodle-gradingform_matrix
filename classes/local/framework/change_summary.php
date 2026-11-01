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
 * The kinds of change found between two versions of a definition.
 *
 * Keeps every kind that was found, so callers can ask about a specific one
 * (for example whether levels were removed) as well as the overall level.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class change_summary {
    /** @var change_level[] found changes keyed by their value */
    private array $changes = [];

    /**
     * Records a change.
     *
     * @param change_level $level
     */
    public function add(change_level $level): void {
        $this->changes[$level->value] = $level;
    }

    /**
     * Whether a given kind of change was found.
     *
     * @param change_level $level
     * @return bool
     */
    public function contains(change_level $level): bool {
        return isset($this->changes[$level->value]);
    }

    /**
     * Whether anything changed.
     *
     * @return bool
     */
    public function has_changes(): bool {
        return !empty($this->changes);
    }

    /**
     * The most significant change found.
     *
     * @return change_level
     */
    public function level(): change_level {
        if (!$this->changes) {
            return change_level::NONE;
        }
        return change_level::from(max(array_keys($this->changes)));
    }
}
