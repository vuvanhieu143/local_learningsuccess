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
use local_learningsuccess\local\actionability\actionability_engine;
use local_learningsuccess\local\actionability\actionability_result;
use local_learningsuccess\local\intervention\intervention_status;
use local_learningsuccess\local\recommendation\recommendation_engine;
use local_learningsuccess\local\risk\risk_result;

/**
 * Unit tests for actionability_engine, actionability_result, and work-queue prioritisation.
 *
 * @package    local_learningsuccess
 * @category   test
 * @copyright  2026 Learning Success Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class actionability_test extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test actionability_result structure and serialization.
     */
    public function test_actionability_result_structure(): void {
        $result = new actionability_result(
            userid: 101,
            level: actionability_result::LEVEL_URGENT,
            priorityscore: 3450.5,
            summaryreason: 'Severe inactivity and missed deadlines',
            reasons: ['No activity for 10 days', '3 overdue assignments'],
            primaryaction: ['type' => 'checkin', 'title' => 'Check in'],
            alternatives: [['type' => 'extension', 'title' => 'Grant Extension']]
        );

        $this->assertEquals(101, $result->get_userid());
        $this->assertEquals(actionability_result::LEVEL_URGENT, $result->get_level());
        $this->assertEquals(3450.5, $result->get_priority_score());
        $this->assertEquals('Severe inactivity and missed deadlines', $result->get_summary_reason());
        $this->assertCount(2, $result->get_reasons());
        $this->assertEquals('checkin', $result->get_primary_action()['type']);
        $this->assertCount(1, $result->get_alternatives());

        $arr = $result->to_array();
        $this->assertIsArray($arr);
        $this->assertEquals('urgent', $arr['level']);
        $this->assertFalse($arr['has_active_intervention']);
    }

    /**
     * Test that high risk student with NO intervention is marked URGENT.
     */
    public function test_unattended_critical_risk_is_urgent(): void {
        $engine = new actionability_engine();
        $risk = new risk_result(score: 85.0, level: risk_result::LEVEL_CRITICAL, source: 'course_activity_signals');
        $signals = [
            ['rule' => 'inactivity', 'severity' => 'critical', 'message' => 'No activity for 14 days', 'value' => 14],
            ['rule' => 'completion', 'severity' => 'critical', 'message' => 'Completion stalled at 20%', 'value' => 20],
        ];

        $res = $engine->evaluate(userid: 5, courseid: 2, risk: $risk, signals: $signals, activeintervention: null);

        $this->assertEquals(actionability_result::LEVEL_URGENT, $res->get_level());
        $this->assertGreaterThan(3000.0, $res->get_priority_score());
        $this->assertNotNull($res->get_primary_action());
        $this->assertContains('No activity for 14 days', $res->get_reasons());
    }

    /**
     * Test that high risk student who was contacted yesterday is categorized as MONITOR (not clogging urgent queue).
     */
    public function test_contacted_waiting_student_moves_to_monitor(): void {
        $engine = new actionability_engine();
        $risk = new risk_result(score: 85.0, level: risk_result::LEVEL_CRITICAL, source: 'course_activity_signals');
        $signals = [
            ['rule' => 'inactivity', 'severity' => 'critical', 'message' => 'No activity for 14 days', 'value' => 14],
        ];

        $now = time();
        $activeintervention = (object) [
            'id' => 42,
            'status' => intervention_status::WAITING,
            'type' => 'contact',
            'timecreated' => $now - DAYSECS,
            'followupat' => $now + (3 * DAYSECS),
        ];

        $res = $engine->evaluate(
            userid: 5,
            courseid: 2,
            risk: $risk,
            signals: $signals,
            activeintervention: $activeintervention,
            now: $now
        );

        $this->assertEquals(actionability_result::LEVEL_MONITOR, $res->get_level());
        $this->assertLessThan(200.0, $res->get_priority_score());
        $this->assertNull($res->get_primary_action());
    }

    /**
     * Test that student with due follow-up is elevated to FOLLOW_UP state with review action.
     */
    public function test_due_followup_is_elevated_to_follow_up(): void {
        $engine = new actionability_engine();
        $risk = new risk_result(score: 45.0, level: risk_result::LEVEL_MONITOR, source: 'course_activity_signals');
        $signals = [];

        $now = time();
        $activeintervention = (object) [
            'id' => 99,
            'status' => intervention_status::WAITING,
            'type' => 'contact',
            'timecreated' => $now - (7 * DAYSECS),
            'followupat' => $now - 3600, // Due 1 hour ago
        ];

        $res = $engine->evaluate(
            userid: 7,
            courseid: 2,
            risk: $risk,
            signals: $signals,
            activeintervention: $activeintervention,
            now: $now
        );

        $this->assertEquals(actionability_result::LEVEL_FOLLOW_UP, $res->get_level());
        $this->assertGreaterThan(2500.0, $res->get_priority_score());
        $this->assertEquals('review_followup', $res->get_primary_action()['type']);
    }

    /**
     * Test duplicate check-in suppression in recommendation_engine.
     */
    public function test_recommendation_engine_suppresses_duplicate_checkin(): void {
        $recengine = new recommendation_engine();
        $signals = [
            ['rule' => 'inactivity', 'severity' => 'critical', 'message' => 'No activity for 10 days'],
            ['rule' => 'completion', 'severity' => 'warning', 'message' => 'Low completion'],
        ];

        // Without active intervention, checkin is primary.
        $part1 = $recengine->get_primary_and_alternatives($signals, null);
        $this->assertEquals('checkin', $part1['primary']['type']);

        // With active checkin intervention, duplicate checkin is deferred.
        $activeinv = (object) [
            'id' => 10,
            'type' => 'checkin',
            'status' => intervention_status::CONTACTED,
        ];
        $part2 = $recengine->get_primary_and_alternatives($signals, $activeinv);

        if (!empty($part2['alternatives'])) {
            $this->assertNotEquals('checkin', $part2['primary']['type']);
        }
    }
}
