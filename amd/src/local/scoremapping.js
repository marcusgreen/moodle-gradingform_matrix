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
 * Live summary of the grade that the lowest and highest possible totals give.
 *
 * Keeps the summary rendered by gradingform_matrix_renderer::score_mapping_summary()
 * up to date while scores, levels, criteria or the 'lockzeropoints' option change.
 * The calculation repeats level_sum_calculator::lowest_grade_fraction().
 *
 * @module     gradingform_matrix/local/scoremapping
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import {SELECTORS, IDS} from 'gradingform_matrix/local/selectors';

/**
 * Round to a number of decimal places, dropping trailing zeros as PHP's round() does.
 *
 * @param {Number} value
 * @param {Number} places
 * @return {Number}
 */
const roundTo = (value, places) => Math.round(value * 10 ** places) / 10 ** places;

/**
 * Returns the lowest and highest possible totals, leaving out scores that are not numbers.
 *
 * @param {HTMLElement} criteria the criteria container
 * @param {String} decsep the decimal separator of the current language
 * @return {{minscore: Number, maxscore: Number}}
 */
export const scoreBounds = (criteria, decsep) => {
    let minscore = 0;
    let maxscore = 0;
    criteria.querySelectorAll(SELECTORS.CRITERION).forEach((criterion) => {
        const scores = [...criterion.querySelectorAll(`${SELECTORS.LEVEL} ${SELECTORS.SCORE_INPUT}`)]
            .map((input) => input.value.trim().replace(/ /g, '').replace(decsep, '.'))
            .filter((value) => value !== '' && isFinite(value))
            .map(Number);
        if (scores.length) {
            minscore += Math.min(...scores);
            maxscore += Math.max(...scores);
        }
    });
    return {minscore, maxscore};
};

/**
 * Returns the share of the maximum grade that the lowest possible total gives.
 *
 * @param {{minscore: Number, maxscore: Number}} bounds
 * @param {Boolean} lockzeropoints
 * @return {Number|null} fraction from 0 to 1, or null if no grade can be calculated
 */
export const lowestGradeFraction = ({minscore, maxscore}, lockzeropoints) => {
    if (maxscore <= minscore) {
        return null;
    }
    if (!lockzeropoints) {
        return 0;
    }
    if (maxscore <= 0) {
        return null;
    }
    return Math.max(0, minscore / maxscore);
};

/**
 * Start keeping one editor's summary up to date.
 *
 * @param {String} name the form element name
 */
export const init = async(name) => {
    const summary = document.getElementById(`matrix-${name}-scoremapping`);
    const criteria = document.getElementById(IDS.criteria(name));
    const form = summary?.closest('form');
    if (!summary || !criteria || !form) {
        return;
    }

    let decsep;
    try {
        decsep = await getString('decsep', 'langconfig');
    } catch (error) {
        Notification.exception(error);
        return;
    }

    // Strings are fetched asynchronously, so only the latest update may change the text.
    let latest = 0;
    const update = async() => {
        const lock = form.querySelector(`input[type=checkbox][name="${name}[options][lockzeropoints]"]`);
        const bounds = scoreBounds(criteria, decsep);
        const fraction = lowestGradeFraction(bounds, !!lock?.checked);
        const request = ++latest;
        let text = '';
        if (fraction !== null) {
            try {
                text = await getString('scoremappingsummary', 'gradingform_matrix', {
                    minscore: roundTo(bounds.minscore, 2),
                    maxscore: roundTo(bounds.maxscore, 2),
                    minpercent: roundTo(fraction * 100, 1),
                });
            } catch (error) {
                Notification.exception(error);
                return;
            }
        }
        if (request === latest) {
            summary.textContent = text;
            summary.hidden = text === '';
        }
    };

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    // Adding, duplicating and deleting criteria or levels changes the DOM without input events.
    new MutationObserver(update).observe(criteria, {childList: true, subtree: true});
};
