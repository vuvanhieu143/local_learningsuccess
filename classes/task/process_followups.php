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

namespace local_learningsuccess\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_learningsuccess\local\intervention\intervention_status;
use stdClass;

/**
 * Scheduled task scanning active interventions and alerting teachers when follow-up dates are reached.
 *
 * @package    local_learningsuccess
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_followups extends scheduled_task {

    /**
     * Get task human-readable name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_process_followups', 'local_learningsuccess');
    }

    /**
     * Execute follow-up processing.
     */
    public function execute(): void {
        global $DB;

        $now = time();
        $sql = "followupat > 0 AND followupat <= :now AND status IN (:open, :contacted, :waiting, :inprogress)";
        $params = [
            'now' => $now,
            'open' => intervention_status::OPEN,
            'contacted' => intervention_status::CONTACTED,
            'waiting' => intervention_status::WAITING,
            'inprogress' => intervention_status::IN_PROGRESS,
        ];

        $due = $DB->get_records_select('local_ls_intervention', $sql, $params);
        $count = 0;
        $manager = new \local_learningsuccess\local\intervention\intervention_manager();

        foreach ($due as $record) {
            try {
                $manager->transition_to((int) $record->id, intervention_status::FOLLOW_UP, 'Follow-up date reached (automated task)');
                $this->notify_teacher($record);
                $count++;
            } catch (\Throwable $e) {
                // Safeguard against invalid state during scheduled task execution.
                continue;
            }
        }

        mtrace("Processed {$count} student follow-ups due.");
    }

    /**
     * Dispatch notification to intervening teacher.
     *
     * @param stdClass $intervention
     * @return bool
     */
    protected function notify_teacher(stdClass $intervention): bool {
        global $DB;

        try {
            $teacher = $DB->get_record('user', ['id' => $intervention->teacherid], '*', IGNORE_MISSING);
            $student = $DB->get_record('user', ['id' => $intervention->userid], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $intervention->courseid], '*', IGNORE_MISSING);

            if (!$teacher || !$student || !$course) {
                return false;
            }

            $coursename = format_string($course->fullname);
            $studentname = fullname($student);

            $eventdata = new \core\message\message();
            $eventdata->component         = 'moodle';
            $eventdata->name              = 'instantmessage';
            $eventdata->userfrom          = \core_user::get_noreply_user();
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string('followup_notification_subject', 'local_learningsuccess', $studentname);
            $eventdata->fullmessage       = get_string('followup_notification_body', 'local_learningsuccess', (object) [
                'student' => $studentname,
                'course' => $coursename,
            ]);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = get_string('followup_notification_subject', 'local_learningsuccess', $studentname);
            $eventdata->notification      = 1;
            $eventdata->courseid          = $intervention->courseid;

            return (bool) message_send($eventdata);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
