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
 * How much a definition change affects existing gradings.
 *
 * The integer values are the ones the editor form and regrade confirmation already use.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum change_level: int {
    // Nothing changed.
    case NONE = 0;
    // Only texts or sort orders changed; regrading is probably not needed.
    case TEXT = 1;
    // Levels were added without raising a criterion's maximum score.
    case LEVELS_ADDED_SAME_MAX = 2;
    // Scores or the score-to-grade mapping changed; grades can be recalculated automatically.
    case SCORES_CHANGED = 3;
    // Levels were removed; some gradings may point to a level that no longer exists.
    case LEVELS_REMOVED = 4;
    // Criteria were added; existing gradings are incomplete.
    case CRITERIA_ADDED = 5;
}
