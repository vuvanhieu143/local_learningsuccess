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
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';
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
            'actual_action': 'Intervention completed by teacher.',
        }
    }])[0].then(result => {
        if (result.success) {
            window.location.reload();
        }
        return null;
    }).catch(err => {
        btn.disabled = false;
        Notification.exception(err);
    });
};

/**
 * Dismiss an active intervention via AJAX.
 *
 * @param {HTMLElement} btn
 */
const dismissIntervention = (btn) => {
    const id = btn.getAttribute('data-id');
    btn.disabled = true;

    Ajax.call([{
        methodname: 'local_learningsuccess_dismiss_intervention',
        args: {
            id: parseInt(id, 10),
        }
    }])[0].then(result => {
        if (result.success) {
            window.location.reload();
        }
        return null;
    }).catch(err => {
        btn.disabled = false;
        Notification.exception(err);
    });
};

/**
 * Dismiss an observable signal via AJAX.
 *
 * @param {HTMLElement} btn
 */
const dismissSignal = (btn) => {
    const signalItem = btn.closest(Selectors.regions.signalItem) || btn.closest('.signal-item-container');
    const reasonSelect = signalItem ? signalItem.querySelector('.dismiss-reason-select') : null;
    const reason = reasonSelect ? reasonSelect.value : 'other';

    const courseId = parseInt(
        btn.getAttribute('data-courseid') ||
        document.querySelector('[data-region="dashboard"], [data-courseid]')?.getAttribute('data-courseid') ||
        activeCourseId,
        10
    );
    const userId = parseInt(btn.getAttribute('data-userid'), 10);
    const signalType = btn.getAttribute('data-signal');

    btn.disabled = true;

    Ajax.call([{
        methodname: 'local_learningsuccess_dismiss_signal',
        args: {
            courseid: courseId,
            userid: userId,
            'signal_type': signalType,
            reason: reason,
        }
    }])[0].then(result => {
        if (result.success) {
            if (signalItem) {
                signalItem.style.opacity = '0.5';
                signalItem.style.pointerEvents = 'none';
            }
            Notification.addNotification({
                message: result.message,
                type: 'success',
            });
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            btn.disabled = false;
        }
        return null;
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
    const userId = btn.getAttribute('data-userid') ||
        btn.closest('[data-userid]')?.getAttribute('data-userid');
    const fullname = btn.getAttribute('data-fullname') || '';
    const recommended = btn.getAttribute('data-recommended') || '';

    let courseId = parseInt(
        btn.getAttribute('data-courseid') ||
        btn.closest('[data-courseid]')?.getAttribute('data-courseid') ||
        document.querySelector('[data-region="dashboard"], [data-courseid]')?.getAttribute('data-courseid') ||
        activeCourseId,
        10
    );

    if ((isNaN(courseId) || courseId <= 0) && activeCourseId) {
        courseId = parseInt(activeCourseId, 10);
    }

    const isQuickContact = btn.classList.contains('btn-quick-contact');

    const hasActive = btn.getAttribute('data-has-active') === 'true' ||
        btn.closest('[data-has-active]')?.getAttribute('data-has-active') === 'true';
    const activeStatus = btn.getAttribute('data-active-status') ||
        btn.closest('[data-active-status]')?.getAttribute('data-active-status') || '';

    let defaultActualAction = '';
    if (isQuickContact) {
        try {
            defaultActualAction = await getString('default_checkin_message', 'local_learningsuccess');
        } catch (e) {
            defaultActualAction = 'Hi, I wanted to check in to see how you are doing with our course.';
        }
    }

    const contextData = {
        courseid: courseId,
        userid: userId,
        'recommended_action': recommended,
        'actual_action': defaultActualAction,
        reason: isQuickContact ? 'Periodic teacher check-in' : '',
        'send_message_default': isQuickContact,
        'has_active_intervention': hasActive,
        'active_status': activeStatus,
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

            const courseIdInput = form.querySelector('[name="courseid"]')?.value;
            const userIdInput = form.querySelector('[name="userid"]')?.value;

            const finalCourseId = parseInt(courseIdInput || courseId || activeCourseId, 10);
            const finalUserId = parseInt(userIdInput || userId, 10);

            if (isNaN(finalCourseId) || finalCourseId <= 0) {
                Notification.exception(new Error('Invalid course ID'));
                return;
            }

            Ajax.call([{
                methodname: 'local_learningsuccess_create_intervention',
                args: {
                    courseid: finalCourseId,
                    userid: finalUserId,
                    type,
                    reason,
                    'recommended_action': recommendedAction,
                    'actual_action': actualAction,
                    'send_message': sendMessage,
                }
            }])[0].then(response => {
                if (response.success) {
                    modal.destroy();
                    window.location.reload();
                }
                return null;
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
            Notification.saveCancelPromise(
                getString('confirm_complete_title', 'local_learningsuccess'),
                getString('confirm_complete_body', 'local_learningsuccess'),
                getString('action_complete', 'local_learningsuccess'),
                {triggerElement: completeBtn}
            ).then(() => {
                completeIntervention(completeBtn);
                return;
            }).catch(() => {
                // Cancelled by user.
            });
            return;
        }

        const dismissBtn = e.target.closest(Selectors.actions.dismissInterventionButton);
        if (dismissBtn) {
            e.preventDefault();
            Notification.saveCancelPromise(
                getString('confirm_dismiss_title', 'local_learningsuccess'),
                getString('confirm_dismiss_body', 'local_learningsuccess'),
                getString('action_dismiss', 'local_learningsuccess'),
                {triggerElement: dismissBtn}
            ).then(() => {
                dismissIntervention(dismissBtn);
                return;
            }).catch(() => {
                // Cancelled by user.
            });
            return;
        }

        const dismissSigBtn = e.target.closest(Selectors.actions.dismissSignalButton);
        if (dismissSigBtn) {
            e.preventDefault();
            Notification.saveCancelPromise(
                getString('dismiss_signal', 'local_learningsuccess'),
                getString('dismiss_signal_confirm', 'local_learningsuccess'),
                getString('dismiss_signal', 'local_learningsuccess'),
                {triggerElement: dismissSigBtn}
            ).then(() => {
                dismissSignal(dismissSigBtn);
                return;
            }).catch(() => {
                // Cancelled by user.
            });
            return;
        }
    });
};

/**
 * Initialize intervention module.
 *
 * @param {Object|number} courseIdOrConfig
 */
export const init = (courseIdOrConfig) => {
    let courseId;
    if (typeof courseIdOrConfig === 'object' && courseIdOrConfig !== null) {
        courseId = parseInt(courseIdOrConfig.courseid, 10);
    } else {
        courseId = parseInt(courseIdOrConfig, 10);
    }

    if (!isNaN(courseId) && courseId > 0) {
        activeCourseId = courseId;
    } else {
        const rootEl = document.querySelector('[data-region="dashboard"], [data-region="student-detail"], [data-courseid]');
        if (rootEl && rootEl.dataset.courseid) {
            activeCourseId = parseInt(rootEl.dataset.courseid, 10);
        }
    }

    registerEventListeners();
};
