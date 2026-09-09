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
 * Modern ES6 module managing intervention modals and AJAX actions for Moodle 5.x.
 *
 * @module     local_learningsuccess/intervention
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import { get_string as getString } from 'core/str';
import Selectors from 'local_learningsuccess/selectors';

let activeCourseId = null;

/**
 * Complete an active intervention via AJAX.
 *
 * @param {HTMLElement} btn
 */
const completeIntervention = (btn) => {
    const id = btn.getAttribute('data-id');
    btn.disabled = true;

    Ajax.call([{
        methodname: 'local_learningsuccess_complete_intervention',
        args: {
            id: parseInt(id, 10),
            actual_action: 'Intervention completed by teacher.'
        }
    }])[0].then(result => {
        if (result.success) {
            window.location.reload();
        }
    }).catch(err => {
        btn.disabled = false;
        Notification.exception(err);
    });
};

/**
 * Open the modal to record an intervention using Moodle 5.x ModalSaveCancel.
 *
 * @param {HTMLElement} btn
 */
const openInterventionModal = async(btn) => {
    const userId = btn.getAttribute('data-userid');
    const fullname = btn.getAttribute('data-fullname') || '';
    const initialType = btn.getAttribute('data-type') || 'CONTACT';
    const recommended = btn.getAttribute('data-recommended') || '';

    const isQuickContact = btn.classList.contains('btn-quick-contact') || initialType === 'CONTACT';

    let defaultActualAction = '';
    if (isQuickContact) {
        try {
            defaultActualAction = await getString('default_checkin_message', 'local_learningsuccess');
        } catch (e) {
            defaultActualAction = 'Hi, I wanted to check in to see how you are doing with our course.';
        }
    }

    const contextData = {
        courseid: activeCourseId,
        userid: userId,
        recommended_action: recommended,
        actual_action: defaultActualAction,
        reason: isQuickContact ? 'Periodic teacher check-in' : '',
        send_message_default: isQuickContact,
    };

    try {
        const titleString = isQuickContact
            ? await getString('action_quick_contact', 'local_learningsuccess')
            : await getString('action_create_intervention', 'local_learningsuccess');

        const modal = await ModalSaveCancel.create({
            title: `${titleString}: ${fullname}`,
            body: Templates.render('local_learningsuccess/intervention_modal', contextData),
            show: true,
            removeOnClose: true,
        });

        // Handle preset message templates.
        modal.getRoot().on('click', Selectors.actions.presetMessageButton, async(e) => {
            e.preventDefault();
            const presetBtn = e.currentTarget;
            const presetKey = presetBtn.getAttribute('data-preset');
            const form = modal.getRoot().find(Selectors.regions.interventionModalForm)[0];
            const actualInput = form.querySelector(Selectors.fields.actualAction);
            const typeSelect = form.querySelector(Selectors.fields.type);
            const sendCheckbox = form.querySelector(Selectors.fields.sendMessage);

            try {
                const msg = await getString(`preset_${presetKey}_text`, 'local_learningsuccess');
                actualInput.value = msg;
            } catch (err) {
                // Fallback.
            }

            if (presetKey === 'extension') {
                typeSelect.value = 'EXTENSION';
            } else if (presetKey === 'resource') {
                typeSelect.value = 'LEARNING_RESOURCE';
            } else {
                typeSelect.value = 'CONTACT';
            }

            if (sendCheckbox) {
                sendCheckbox.checked = true;
            }
        });

        modal.getRoot().on(ModalEvents.save, () => {
            const form = modal.getRoot().find(Selectors.regions.interventionModalForm)[0];
            const type = form.querySelector(Selectors.fields.type).value;
            const reason = form.querySelector(Selectors.fields.reason).value;
            const recommendedAction = form.querySelector(Selectors.fields.recommendedAction).value;
            const actualAction = form.querySelector(Selectors.fields.actualAction).value;
            const sendMessage = form.querySelector(Selectors.fields.sendMessage)?.checked ? true : false;

            Ajax.call([{
                methodname: 'local_learningsuccess_create_intervention',
                args: {
                    courseid: parseInt(activeCourseId, 10),
                    userid: parseInt(userId, 10),
                    type,
                    reason,
                    recommended_action: recommendedAction,
                    actual_action: actualAction,
                    send_message: sendMessage,
                }
            }])[0].then(response => {
                if (response.success) {
                    modal.destroy();
                    window.location.reload();
                }
            }).catch(Notification.exception);
        });
    } catch (error) {
        Notification.exception(error);
    }
};

/**
 * Register global click event listeners using Selectors.
 */
const registerEventListeners = () => {
    document.addEventListener('click', e => {
        const quickContactBtn = e.target.closest(Selectors.actions.quickContactButton);
        if (quickContactBtn) {
            e.preventDefault();
            openInterventionModal(quickContactBtn);
            return;
        }

        const recordBtn = e.target.closest(Selectors.actions.recordInterventionButton);
        if (recordBtn) {
            e.preventDefault();
            openInterventionModal(recordBtn);
            return;
        }

        const completeBtn = e.target.closest(Selectors.actions.completeInterventionButton);
        if (completeBtn) {
            e.preventDefault();
            completeIntervention(completeBtn);
        }
    });
};

/**
 * Initialize intervention module.
 *
 * @param {Object} config
 * @param {number} config.courseid
 */
export const init = (config) => {
    activeCourseId = parseInt(config.courseid, 10);
    registerEventListeners();
};
