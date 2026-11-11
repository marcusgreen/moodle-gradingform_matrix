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

use gradingform_instance;

/**
 * Builds a table of grading results with the level, points and remark for each criterion.
 *
 * Core's grade exports only hold the final grade. This gives one row per graded item, using
 * the instance that counts (active, or waiting for a regrade). Drafts and history are left out.
 *
 * The grade is given as a percentage of the maximum grade. Core doesn't store the grade a
 * grading form gave, and the grade range is only known while grading.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results_exporter {
    /**
     * Constructor.
     *
     * @param array $criteria definition criteria in sort order, keyed by id, each with 'description' and 'levels'
     * @param array $options definition options; 'enableremarks' adds remark columns
     * @param grade_calculator $calculator
     * @param filling_repository $fillings
     * @param \Closure $totext turns text into plain text: fn(string $text, int $format = the method's format): string
     * @param string $component component of the lang strings used for the column names
     */
    public function __construct(
        /** @var array definition criteria in sort order */
        protected readonly array $criteria,
        /** @var array definition options */
        protected readonly array $options,
        /** @var grade_calculator */
        protected readonly grade_calculator $calculator,
        /** @var filling_repository */
        protected readonly filling_repository $fillings,
        /** @var \Closure turns text into plain text */
        protected readonly \Closure $totext,
        /** @var string component of the lang strings */
        protected readonly string $component,
    ) {
    }

    /**
     * Returns the column names, keyed like the rows.
     *
     * @param string[] $identityfields user fields to show besides the full name
     * @param bool $showitemid add the item id, for areas whose items can't be matched to users
     * @return string[]
     */
    public function columns(array $identityfields, bool $showitemid): array {
        $columns = ['fullname' => get_string('fullnameuser')];
        foreach ($identityfields as $field) {
            $columns[$field] = \core_user\fields::get_display_name($field);
        }
        if ($showitemid) {
            $columns['itemid'] = get_string('resultsitemid', $this->component);
        }
        $columns['grader'] = get_string('resultsgrader', $this->component);
        $columns['timemodified'] = get_string('resultstimemodified', $this->component);
        $columns['status'] = get_string('resultsstatus', $this->component);
        foreach ($this->criteria as $id => $criterion) {
            $name = ($this->totext)($criterion['description'] ?? '');
            $columns["c{$id}level"] = get_string('resultslevel', $this->component, $name);
            $columns["c{$id}points"] = get_string('resultspoints', $this->component, $name);
            if (!empty($this->options['enableremarks'])) {
                $columns["c{$id}remark"] = get_string('resultsremark', $this->component, $name);
            }
        }
        $columns['total'] = get_string('resultstotal', $this->component);
        $columns['grade'] = get_string('resultsgrade', $this->component);
        return $columns;
    }

    /**
     * Returns one row per graded item, sorted by name.
     *
     * @param int $definitionid
     * @param item_resolver $resolver
     * @param string[] $identityfields user fields to show besides the full name
     * @return array[] rows keyed like {@see columns()}
     */
    public function rows(int $definitionid, item_resolver $resolver, array $identityfields): array {
        global $DB;
        [$statussql, $params] = $DB->get_in_or_equal([
            gradingform_instance::INSTANCE_STATUS_ACTIVE,
            gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
        ], SQL_PARAMS_NAMED);
        $params['definitionid'] = $definitionid;
        $instances = $DB->get_records_select(
            'grading_instances',
            "definitionid = :definitionid AND status $statussql",
            $params,
            'timemodified DESC, id DESC'
        );
        // If an item has more than one instance that counts, keep the latest.
        $latest = [];
        foreach ($instances as $instance) {
            $latest[$instance->itemid] ??= $instance;
        }
        if (!$latest) {
            return [];
        }

        $userids = $resolver->user_ids(array_keys($latest));
        $users = $this->users(array_merge($userids, array_column($latest, 'raterid')), $identityfields);
        $fillings = $this->fillings->load_for_instances(array_column($latest, 'id'));

        $rows = [];
        foreach ($latest as $itemid => $instance) {
            $user = $users[$userids[$itemid] ?? 0] ?? null;
            $rater = $users[$instance->raterid] ?? null;
            $row = ['fullname' => $user ? fullname($user) : ''];
            foreach ($identityfields as $field) {
                $row[$field] = $user->$field ?? '';
            }
            if (!$resolver->supported()) {
                $row['itemid'] = $itemid;
            }
            $row += [
                'grader' => $rater ? fullname($rater) : '',
                'timemodified' => userdate($instance->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
                'status' => get_string(
                    $instance->status == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE ? 'resultsneedregrade' : 'resultsgraded',
                    $this->component
                ),
            ];
            $rows[] = $row + $this->criteria_cells($fillings[$instance->id] ?? []);
        }
        \core_collator::asort_array_of_arrays_by_key($rows, 'fullname');
        return array_values($rows);
    }

    /**
     * Returns the criterion, total and grade cells for one filling.
     *
     * A level that no longer exists counts as no level, as in {@see level_sum_calculator::total_score()}.
     *
     * @param array $fillings fillings keyed by criterion id
     * @return array
     */
    public function criteria_cells(array $fillings): array {
        $cells = [];
        foreach ($this->criteria as $id => $criterion) {
            $filling = $fillings[$id] ?? [];
            $level = $criterion['levels'][$filling['levelid'] ?? 0] ?? null;
            $cells["c{$id}level"] = $level ? ($this->totext)($level['definition'] ?? '') : '';
            $cells["c{$id}points"] = $level ? (float)$level['score'] : '';
            if (!empty($this->options['enableremarks'])) {
                $format = (int)($filling['remarkformat'] ?? FORMAT_MOODLE);
                $cells["c{$id}remark"] = ($this->totext)($filling['remark'] ?? '', $format);
            }
        }
        $cells['total'] = (float)level_sum_calculator::total_score($this->criteria, $fillings);
        $grade = $this->calculator->calculate($this->criteria, $fillings, [0, 100], $this->options, true);
        $cells['grade'] = $grade < 0 ? '' : round($grade, 2);
        return $cells;
    }

    /**
     * Loads users with the fields needed for their names and identity.
     *
     * @param int[] $userids
     * @param string[] $identityfields
     * @return \stdClass[] keyed by id
     */
    protected function users(array $userids, array $identityfields): array {
        global $DB;
        $userids = array_unique(array_filter($userids));
        if (!$userids) {
            return [];
        }
        $fields = array_unique(array_merge(['id'], \core_user\fields::get_name_fields(), $identityfields));
        return $DB->get_records_list('user', 'id', $userids, '', implode(',', $fields));
    }
}
