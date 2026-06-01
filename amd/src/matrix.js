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
 * Handles level cell click/keyboard toggling and popout-aware sizing of
 * the remark textareas so the grader can use the matrix in a resized
 * overlay.
 *
 * @module     gradingform_matrix/matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    LEVEL: '.level',
    RADIO_WRAPPER: '.radio',
    RADIO_INPUT: 'input[type=radio]',
    REMARK_TEXTAREA: '.remark textarea',
    CRITERION: '.criterion',
    FITEM: '.fitem',
    POPOUT_CONTENT: '.felement',
};

const toggleLevel = (level) => {
    const row = level.closest(SELECTORS.CRITERION) || level.parentNode;
    const siblings = row.querySelectorAll(SELECTORS.LEVEL);
    const radio = level.querySelector(SELECTORS.RADIO_INPUT);
    if (!radio) {
        return;
    }
    const wasChecked = radio.checked;

    siblings.forEach((sib) => {
        sib.classList.remove('checked');
        sib.setAttribute('aria-checked', 'false');
        const sibRadio = sib.querySelector(SELECTORS.RADIO_INPUT);
        if (sibRadio) {
            sibRadio.checked = false;
        }
    });

    if (!wasChecked) {
        radio.checked = true;
        level.classList.add('checked');
        level.setAttribute('aria-checked', 'true');
    }
};

const handleEvent = (root) => (event) => {
    if (event.type === 'keydown' && event.key !== ' ' && event.key !== 'Enter') {
        return;
    }
    const level = event.target.closest(SELECTORS.LEVEL);
    if (!level || !root.contains(level)) {
        return;
    }
    // If click hit the radio input directly, let browser do its native toggle;
    // we still update class/aria afterwards via toggleLevel but skip preventDefault.
    if (event.target.tagName !== 'INPUT') {
        event.preventDefault();
    }
    toggleLevel(level);
};

const distributeRemarkHeights = (root) => {
    const fitem = root.closest(SELECTORS.FITEM);
    const textareas = root.querySelectorAll(SELECTORS.REMARK_TEXTAREA);
    if (!fitem || !fitem.classList.contains('popout')) {
        textareas.forEach((ta) => {
            ta.style.height = '';
        });
        return;
    }
    const felement = root.closest(SELECTORS.POPOUT_CONTENT);
    if (!felement) {
        return;
    }
    const rows = root.querySelectorAll(SELECTORS.CRITERION);
    if (!rows.length) {
        return;
    }
    const available = felement.clientHeight;
    const perRow = Math.max(60, Math.floor(available / rows.length) - 120);
    textareas.forEach((ta) => {
        ta.style.height = `${perRow}px`;
        ta.style.boxSizing = 'border-box';
    });
};

const observePopout = (root) => {
    const fitem = root.closest(SELECTORS.FITEM);
    if (!fitem || typeof ResizeObserver === 'undefined') {
        return;
    }
    const recompute = () => distributeRemarkHeights(root);
    new ResizeObserver(recompute).observe(fitem);
    new MutationObserver(recompute).observe(fitem, {
        attributes: true,
        attributeFilter: ['class'],
    });
    window.addEventListener('resize', recompute);
    recompute();
};

export const init = (name) => {
    const root = document.getElementById(`matrix-${name}`);
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

    observePopout(root);
};
