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
 * AI assistant dialogue on the grading form editor.
 *
 * The teacher picks course material and works out a grading form in a conversation. Each reply can propose a
 * whole form, shown as a preview, which can then be applied as a draft. The conversation and the current
 * proposal are kept here and sent with each turn; the server keeps no state.
 *
 * @module     gradingform_matrix/local/aiassistant
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';
import PolicyModal from 'core_ai/policymodal';
import {getString, getStrings} from 'core/str';
import {aiSources, aiTurn, aiApply} from 'gradingform_matrix/repository';

const SELECTORS = {
    OPEN: '[data-action="gradingform_matrix-aiassistant"]',
    SOURCE: '[data-region="ai-source"]',
    SIZE: '[data-region="ai-size"]',
    LOG: '[data-region="ai-log"]',
    EMPTY: '[data-region="ai-empty"]',
    FORM: '[data-region="ai-form"]',
    INSTRUCTION: '[data-region="ai-instruction"]',
    SEND: '[data-action="ai-send"]',
    RESET: '[data-action="ai-reset"]',
    BUSY: '[data-region="ai-busy"]',
    PREVIEW: '[data-region="ai-preview"]',
    APPLY: '[data-action="ai-apply"]',
};

/** @var {Number} earlier turns kept and sent; the server uses fewer */
const MAX_HISTORY = 8;

/** @var {Modal|null} the dialogue, kept so the conversation survives closing it */
let modal = null;

/**
 * One conversation in the dialogue.
 */
class Conversation {
    /**
     * Constructor.
     *
     * @param {Number} areaid
     * @param {HTMLElement} root the dialogue
     * @param {Object} sources result of the sources web service
     * @param {Object} strings
     */
    constructor(areaid, root, sources, strings) {
        this.areaid = areaid;
        this.root = root;
        this.budget = sources.budget;
        this.canapply = sources.canapply;
        this.strings = strings;
        this.history = [];
        this.proposal = '';
        this.busy = false;

        this.instruction = root.querySelector(SELECTORS.INSTRUCTION);
        this.log = root.querySelector(SELECTORS.LOG);
        this.preview = root.querySelector(SELECTORS.PREVIEW);
        this.emptyPreview = this.preview.innerHTML;
        this.apply = root.querySelector(SELECTORS.APPLY);

        root.querySelector(SELECTORS.FORM).addEventListener('submit', (e) => {
            e.preventDefault();
            this.send();
        });
        this.instruction.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.send();
            }
        });
        root.querySelector(SELECTORS.RESET).addEventListener('click', () => this.reset());
        this.apply.addEventListener('click', () => this.applyProposal());
        root.querySelectorAll(SELECTORS.SOURCE).forEach((box) => box.addEventListener('change', () => this.showSize()));
        this.showSize();
    }

    /**
     * The selected course modules.
     *
     * @return {Number[]}
     */
    selectedSources() {
        return [...this.root.querySelectorAll(SELECTORS.SOURCE)].filter((box) => box.checked).map((box) => Number(box.value));
    }

    /**
     * Shows how much material is selected, against the limit.
     */
    async showSize() {
        const used = [...this.root.querySelectorAll(SELECTORS.SOURCE)]
            .filter((box) => box.checked)
            .reduce((total, box) => total + Number(box.dataset.length), 0);
        const key = used > this.budget ? 'aisizeover' : 'aisize';
        this.root.querySelector(SELECTORS.SIZE).textContent =
            await getString(key, 'gradingform_matrix', {used, budget: this.budget});
    }

    /**
     * Adds a message to the conversation.
     *
     * @param {String} who 'you', 'assistant' or 'error'
     * @param {String} text plain text
     */
    addMessage(who, text) {
        const empty = this.log.querySelector(SELECTORS.EMPTY);
        if (empty) {
            empty.remove();
        }
        const message = document.createElement('div');
        message.className = `gradingform_matrix-ai-message gradingform_matrix-ai-${who}`;
        const label = document.createElement('strong');
        label.textContent = this.strings[who];
        const body = document.createElement('p');
        body.className = 'mb-0';
        body.textContent = text;
        message.append(label, body);
        this.log.append(message);
        this.log.scrollTop = this.log.scrollHeight;
    }

    /**
     * Shows or hides the waiting state.
     *
     * @param {Boolean} busy
     */
    setBusy(busy) {
        this.busy = busy;
        this.log.setAttribute('aria-busy', busy ? 'true' : 'false');
        this.root.querySelector(SELECTORS.BUSY).classList.toggle('d-none', !busy);
        this.root.querySelector(SELECTORS.SEND).disabled = busy;
        this.instruction.readOnly = busy;
    }

    /**
     * Sends the instruction.
     */
    async send() {
        const instruction = this.instruction.value.trim();
        if (this.busy || instruction === '') {
            return;
        }
        const pending = new Pending('gradingform_matrix/local/aiassistant:send');
        this.addMessage('you', instruction);
        this.setBusy(true);
        try {
            const result = await aiTurn(this.areaid, instruction, this.selectedSources(), this.history, this.proposal);
            this.addMessage('assistant', result.message || this.strings.noreply);
            if (result.error) {
                this.addMessage('error', await getString('aiproposalinvalid', 'gradingform_matrix', result.error));
            }
            if (result.proposal) {
                this.proposal = result.proposal;
                this.preview.innerHTML = result.preview;
                this.apply.disabled = !this.canapply;
            }
            this.history = [...this.history, {instruction, reply: result.message}].slice(-MAX_HISTORY);
            this.instruction.value = '';
        } catch (error) {
            this.addMessage('error', error.message || String(error));
        } finally {
            this.setBusy(false);
            this.instruction.focus();
            pending.resolve();
        }
    }

    /**
     * Starts a new conversation.
     */
    reset() {
        if (this.busy) {
            return;
        }
        this.history = [];
        this.proposal = '';
        this.log.replaceChildren();
        const empty = document.createElement('p');
        empty.className = 'small text-body-secondary mb-0';
        empty.dataset.region = 'ai-empty';
        empty.textContent = this.strings.empty;
        this.log.append(empty);
        this.preview.innerHTML = this.emptyPreview;
        this.apply.disabled = true;
        this.instruction.focus();
    }

    /**
     * Replaces the grading form with the proposal, after confirmation.
     */
    async applyProposal() {
        if (!this.proposal || this.busy) {
            return;
        }
        try {
            await Notification.saveCancelPromise(this.strings.apply, this.strings.applyconfirm, this.strings.apply);
        } catch (cancelled) {
            return;
        }
        const pending = new Pending('gradingform_matrix/local/aiassistant:apply');
        try {
            const result = await aiApply(this.areaid, this.proposal);
            window.location.href = result.editurl;
        } catch (error) {
            this.addMessage('error', error.message || String(error));
            pending.resolve();
        }
    }
}

/**
 * Asks the user to accept the AI policy.
 *
 * @return {Promise<Boolean>} whether it was accepted
 */
const acceptPolicy = async() => {
    const policy = await PolicyModal.create({});
    return new Promise((resolve) => {
        policy.getRoot().on(ModalEvents.save, () => resolve(true));
        policy.getRoot().on(ModalEvents.hidden, () => resolve(false));
    });
};

/**
 * Opens the dialogue, creating it on first use.
 *
 * @param {Number} areaid
 * @param {HTMLElement} trigger the button that opened it
 */
const open = async(areaid, trigger) => {
    if (modal) {
        modal.show();
        return;
    }
    const pending = new Pending('gradingform_matrix/local/aiassistant:open');
    try {
        const sources = await aiSources(areaid);
        if (!sources.available) {
            await Notification.alert(await getString('aiassistant', 'gradingform_matrix'),
                await getString('aiunavailable', 'gradingform_matrix'));
            return;
        }
        if (!sources.policyaccepted && !await acceptPolicy()) {
            return;
        }
        const [title, you, assistant, error, noreply, empty, apply, applyconfirm] = await getStrings([
            'aiassistant', 'aiyou', 'aiassistantname', 'error', 'ainoreply', 'aiconversationempty', 'aiapply', 'aiapplyconfirm',
        ].map((key) => ({key, component: key === 'error' ? 'moodle' : 'gradingform_matrix'})));
        modal = await Modal.create({
            title,
            body: Templates.render('gradingform_matrix/ai_assistant', {
                uniqid: `gradingform_matrix-ai-${areaid}`,
                canapply: sources.canapply,
                sources: sources.sources,
            }),
            large: true,
            removeOnClose: false,
            returnElement: trigger,
        });
        modal.getModal().addClass('modal-xl');
        await modal.getBodyPromise();
        new Conversation(areaid, modal.getRoot()[0], sources,
            {you, assistant, error, noreply, empty, apply, applyconfirm});
        modal.getRoot().on(ModalEvents.shown, () => modal.getRoot()[0].querySelector(SELECTORS.INSTRUCTION)?.focus());
        modal.show();
    } catch (error) {
        Notification.exception(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Opens the dialogue from the AI assistant button.
 *
 * @param {Number} areaid
 */
export const init = (areaid) => {
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest(SELECTORS.OPEN);
        if (trigger) {
            e.preventDefault();
            open(areaid, trigger);
        }
    });
};
