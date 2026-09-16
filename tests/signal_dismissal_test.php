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

namespace local_learningsuccess;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_learningsuccess\local\explanation\explanation;
use local_learningsuccess\local\explanation\explanation_engine;
use local_learningsuccess\local\intervention\message_template;
use local_learningsuccess\local\risk\risk_result;
use local_learningsuccess\local\signal\signal_collector;

/**
 * Unit tests for message templates, teacher signal dismissal overrides, and why-now / what-changed generation.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 vuvanhieu143
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signal_dismissal_test extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test message template rendering with placeholders.
     */
    public function test_message_template_rendering(): void {
        $user = (object) [
            'firstname' => 'Alex',
            'lastname' => 'Smith',
        ];
        $course = (object) [
            'fullname' => 'Advanced Machine Learning',
        ];

        $rendered = message_template::render(message_template::TEMPLATE_INACTIVITY, $user, $course);
        $this->assertStringContainsString('Hi Alex,', $rendered);
        $this->assertStringContainsString('Advanced Machine Learning', $rendered);

        $templates = message_template::get_templates();
        $this->assertArrayHasKey(message_template::TEMPLATE_INACTIVITY, $templates);
        $this->assertArrayHasKey(message_template::TEMPLATE_OVERDUE, $templates);
        $this->assertArrayHasKey(message_template::TEMPLATE_GRADE_DECLINE, $templates);
    }

    /**
     * Test message template auto-detection based on signals.
     */
    public function test_message_template_detection(): void {
        $inactivitysignals = [['rule' => 'inactivity', 'severity' => 'warning']];
        $this->assertEquals(message_template::TEMPLATE_INACTIVITY, message_template::detect_best_template($inactivitysignals));

        $overduesignals = [['rule' => 'missed_assignments', 'severity' => 'critical']];
        $this->assertEquals(message_template::TEMPLATE_OVERDUE, message_template::detect_best_template($overduesignals));

        $gradesignals = [['rule' => 'grade_performance', 'severity' => 'critical']];
        $this->assertEquals(message_template::TEMPLATE_GRADE_DECLINE, message_template::detect_best_template($gradesignals));
    }

    /**
     * Test teacher signal dismissal suppresses signal from collection.
     */
    public function test_teacher_signal_dismissal_suppression(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();

        $collector = new signal_collector();

        // Initially not dismissed.
        $this->assertFalse($collector->is_signal_dismissed($user->id, $course->id, 'inactivity'));

        // Dismiss the signal.
        $success = $collector->dismiss_signal(
            userid: $user->id,
            courseid: $course->id,
            signaltype: 'inactivity',
            teacherid: $teacher->id,
            reason: signal_collector::REASON_APPROVED_LEAVE
        );

        $this->assertTrue($success);
        $this->assertTrue($collector->is_signal_dismissed($user->id, $course->id, 'inactivity', 'warning'));

        // Verify database record.
        $record = $DB->get_record('local_learningsuccess_sign', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'signal_type' => 'inactivity',
        ]);
        $this->assertNotEmpty($record);
        $this->assertEquals(1, $record->dismissed);
        $this->assertEquals(signal_collector::REASON_APPROVED_LEAVE, $record->dismissreason);
        $this->assertEquals($teacher->id, $record->dismissedby);
    }

    /**
     * Test signal reactivation if conditions escalate to critical severity.
     */
    public function test_signal_reactivates_on_critical_escalation(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();

        $collector = new signal_collector();

        // Dismiss as warning/medium.
        $collector->dismiss_signal(
            userid: $user->id,
            courseid: $course->id,
            signaltype: 'grade_decline',
            teacherid: $teacher->id,
            reason: signal_collector::REASON_FALSE_POSITIVE
        );

        // Warning remains suppressed.
        $this->assertTrue($collector->is_signal_dismissed($user->id, $course->id, 'grade_decline', explanation::SEVERITY_WARNING));

        // Critical severity escalation reactivates the signal!
        $this->assertFalse($collector->is_signal_dismissed($user->id, $course->id, 'grade_decline', explanation::SEVERITY_CRITICAL));
    }

    /**
     * Test "Why now?" and "What changed?" snapshot comparison.
     */
    public function test_why_now_and_what_changed_generation(): void {
        $engine = new explanation_engine();

        $risk = new risk_result(score: 80.0, level: risk_result::LEVEL_CRITICAL, source: 'course_activity_signals');
        $signals = [
            ['title' => 'Inactivity', 'description' => 'No activity for 10 days'],
            ['title' => 'Overdue', 'description' => '2 overdue activities'],
        ];

        $whynow = $engine->generate_why_now($risk, $signals, null);
        $this->assertContains('No activity for 10 days', $whynow);
        $this->assertContains('2 overdue activities', $whynow);
        $this->assertContains('No active teacher intervention in place.', $whynow);

        // Test snapshot comparison ("What changed?").
        $before = [
            'completion' => 45.0,
            'grade' => 52.0,
            'inactive_days' => 12,
            'risk_score' => 85.0,
        ];
        $after = [
            'completion' => 70.0,
            'grade' => 68.0,
            'inactive_days' => 2,
            'risk_score' => 35.0,
        ];

        $diff = $engine->compare_snapshots($before, $after);
        $this->assertTrue($diff['has_changes']);
        $this->assertCount(4, $diff['metrics']);

        // Check completion improved.
        $completionmetric = array_values(array_filter($diff['metrics'], fn($m) => $m['metric'] === 'completion'))[0];
        $this->assertEquals('improved', $completionmetric['direction']);
        $this->assertEquals('+25%', $completionmetric['difference']);

        // Check inactive days decreased (improved).
        $activitymetric = array_values(array_filter($diff['metrics'], fn($m) => $m['metric'] === 'activity'))[0];
        $this->assertEquals('improved', $activitymetric['direction']);
    }

    /**
     * Test that signal history accurately updates firstseen vs lastseen and deactivates resolved signals.
     */
    public function test_signal_history_firstseen_and_lastseen_preserved(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // 1. Manually insert initial signal record representing an earlier observation.
        $initialtime = time() - (5 * DAYSECS);
        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'signal_type' => 'inactivity',
            'severity' => 'warning',
            'value' => 7.0,
            'metadata' => json_encode(['days' => 7]),
            'firstseen' => $initialtime,
            'lastseen' => $initialtime,
            'active' => 1,
            'dismissed' => 0,
            'timecreated' => $initialtime,
        ];
        $id = $DB->insert_record('local_learningsuccess_sign', $record);

        // 2. Run signal_collector with a mock signal to simulate current observation.
        $mockexplanation = new explanation(
            type: 'inactivity',
            severity: explanation::SEVERITY_CRITICAL,
            title: 'Inactivity',
            description: 'Inactive for 14 days',
            value: 14.0
        );

        $mocksignal = new class($mockexplanation) implements \local_learningsuccess\local\signal\signal {
            public function __construct(private explanation $exp) {}
            public function get_type(): string { return 'inactivity'; }
            public function evaluate(int $userid, int $courseid): ?explanation { return $this->exp; }
        };

        $collector = new signal_collector([$mocksignal]);
        $collector->collect($user->id, $course->id, persist: true);

        // 3. Verify firstseen remained initialtime, lastseen was bumped to recent.
        $updated = $DB->get_record('local_learningsuccess_sign', ['id' => $id]);
        $this->assertEquals($initialtime, (int) $updated->firstseen);
        $this->assertGreaterThan($initialtime, (int) $updated->lastseen);
        $this->assertEquals('critical', $updated->severity);
        $this->assertEquals(1, $updated->active);

        // 4. Now simulate resolved signal (no signals triggered).
        $emptycollector = new signal_collector([]);
        $emptycollector->collect($user->id, $course->id, persist: true);

        // 5. Verify the previously active signal was marked inactive.
        $deactivated = $DB->get_record('local_learningsuccess_sign', ['id' => $id]);
        $this->assertEquals(0, (int) $deactivated->active);
        $this->assertEquals($initialtime, (int) $deactivated->firstseen);
    }
}
