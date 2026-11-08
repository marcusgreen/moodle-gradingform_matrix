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
 * Matrix grading form behaviour.
 *
 * Handles level cell click/keyboard toggling, and adds size controls when
 * the grading form is popped out.
 *
 * @module     gradingform_matrix/matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {SELECTORS, IDS} from 'gradingform_matrix/local/selectors';
import * as Popout from 'gradingform_matrix/local/popout';

/** Keys that move the selection within a criterion, and the direction they move it in left-to-right pages. */
const ARROWS = {ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1};

/**
 * Selects a level, or clears its criterion's selection.
 *
 * @param {HTMLElement} level
 * @param {Boolean} selected
 */
const setLevel = (level, selected) => {
    const row = level.closest(SELECTORS.CRITERION) || level.parentNode;
    row.querySelectorAll(SELECTORS.LEVEL).forEach((sibling) => {
        const on = selected && sibling === level;
        sibling.classList.toggle('checked', on);
        sibling.setAttribute('aria-checked', on ? 'true' : 'false');
        const radio = sibling.querySelector(SELECTORS.RADIO_INPUT);
        if (radio) {
            radio.checked = on;
        }
    });
};

/**
 * Makes one level the only tab stop of its criterion, as in a native radio group.
 *
 * @param {HTMLElement} group the radiogroup element
 * @param {HTMLElement} [level] defaults to the selected level, or the first one
 */
const setTabStop = (group, level) => {
    const levels = [...group.querySelectorAll(SELECTORS.RADIO_LEVEL)];
    const stop = level || levels.find((l) => l.getAttribute('aria-checked') === 'true') || levels[0];
    levels.forEach((l) => {
        l.tabIndex = l === stop ? 0 : -1;
    });
};

/**
 * Moves the selection with the arrow, Home and End keys.
 *
 * @param {KeyboardEvent} event
 * @return {Boolean} whether the key was handled
 */
const moveSelection = (event) => {
    const level = event.target.closest(SELECTORS.RADIO_LEVEL);
    const group = level ? level.closest(SELECTORS.RADIOGROUP) : null;
    if (!group || !(event.key in ARROWS || event.key === 'Home' || event.key === 'End')) {
        return false;
    }
    const levels = [...group.querySelectorAll(SELECTORS.RADIO_LEVEL)];
    let index = levels.indexOf(level);
    if (event.key === 'Home') {
        index = 0;
    } else if (event.key === 'End') {
        index = levels.length - 1;
    } else {
        const rtl = document.dir === 'rtl' && (event.key === 'ArrowLeft' || event.key === 'ArrowRight');
        index = (index + (rtl ? -ARROWS[event.key] : ARROWS[event.key]) + levels.length) % levels.length;
    }
    const next = levels[index];
    event.preventDefault();
    setLevel(next, true);
    setTabStop(group, next);
    next.focus();
    return true;
};

const handleEvent = (root) => (event) => {
    if (event.type === 'keydown' && moveSelection(event)) {
        return;
    }
    if (event.type === 'keydown' && event.key !== ' ' && event.key !== 'Enter') {
        return;
    }
    const level = event.target.closest(SELECTORS.LEVEL);
    if (!level || !root.contains(level)) {
        return;
    }
    const radio = level.querySelector(SELECTORS.RADIO_INPUT);
    if (!radio) {
        return;
    }
    // If click hit the radio input directly, let browser do its native toggle;
    // we still update class/aria afterwards but skip preventDefault.
    if (event.target.tagName !== 'INPUT') {
        event.preventDefault();
    }
    // Selecting the selected level again clears the criterion.
    setLevel(level, !level.classList.contains('checked'));
    const group = level.closest(SELECTORS.RADIOGROUP);
    if (group) {
        setTabStop(group, level);
    }
};

export const init = (name) => {
    const root = document.getElementById(IDS.root(name));
    if (!root) {
        return;
    }

    root.addEventListener('click', handleEvent(root));
    root.addEventListener('keydown', handleEvent(root));

    root.querySelectorAll(SELECTORS.RADIO_WRAPPER).forEach((el) => {
        el.style.display = 'none';
    });

    root.querySelectorAll(SELECTORS.LEVEL).forEach((level) => {
        const radio = level.querySelector(SELECTORS.RADIO_INPUT);
        if (radio && radio.checked) {
            level.classList.add('checked');
        }
    });
    root.querySelectorAll(SELECTORS.RADIOGROUP).forEach((group) => setTabStop(group));

    Popout.watch(root);
};
