# <img alt="" src="https://raw.githubusercontent.com/vuvanhieu143/local_learningsuccess/main/pix/monologo.svg" width="64" style="max-width: 64px; vertical-align: middle;"> Learning Success

[![Latest Release](https://img.shields.io/github/v/release/vuvanhieu143/local_learningsuccess?sort=semver&color=orange)](https://github.com/vuvanhieu143/local_learningsuccess/releases)
[![Moodle Plugin CI](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml)
[![PHP Support](https://img.shields.io/badge/php-8.2--8.4-blue)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-5.0%2B-orange)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![License GPL-3.0](https://img.shields.io/github/license/vuvanhieu143/local_learningsuccess?color=lightgrey)](https://github.com/vuvanhieu143/local_learningsuccess/blob/main/LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/vuvanhieu143/local_learningsuccess)](https://github.com/vuvanhieu143/local_learningsuccess/graphs/contributors)

**Learning Success** is a Moodle plugin that helps teachers identify students who may need support, understand why they may be struggling, and take appropriate action.

It works alongside **Moodle Analytics** rather than replacing it. Moodle Analytics provides predictions and Learning Success turns those insights into a practical teacher workflow:

**Detect → Explain → Prioritise → Recommend → Intervene → Follow up → Measure**

Learning Success helps teachers answer questions such as:

- Which students may need attention?
- Why might a student be struggling?
- Which students should I look at first?
- What action could I take?
- Have I already contacted this student?
- When should I follow up?
- Did the intervention appear to help?

The plugin is designed to support teacher judgement. Analytics predictions and learning signals are indicators, not definitive judgements about students.

## Installation

Download the latest release from the [GitHub Releases](https://github.com/vuvanhieu143/local_learningsuccess/releases) page and install it through Moodle's plugin installer, or place the extracted plugin in:

```text
moodle/local/learningsuccess
```

Then log in as an administrator and go to **Site administration → Notifications** to complete the installation.

For a normal Moodle plugin installation, the plugin directory must be named `learningsuccess`.

## Configuration

Learning Success uses Moodle Analytics as its primary source of student risk information.

After installation:

1. Go to **Site administration → Plugins → Local plugins → Learning Success**.
2. Enable Learning Success.
3. Enable the features your site needs, such as Class Pulse and intervention tracking.
4. Configure the inactivity and grade-decline thresholds if required.
5. Select the Moodle Analytics model used by Learning Success.
6. Make sure Moodle Analytics is enabled and the selected model has generated predictions.

The default Analytics model is Moodle's course-dropout target. If your site uses another Analytics model, configure the corresponding model target in the Learning Success settings.

Learning Success does not create a separate machine-learning risk model. It uses Moodle Analytics predictions and combines them with available learning signals and intervention history to support teacher decisions.

## User Guide

### 1. Open Learning Success

Open Learning Success from a course where you have the required teaching permissions.

The main view is designed to help answer "Who needs attention now?" rather than simply display analytics data.

Depending on the course and available data, you can review student status, risk information, learning signals, recommendations, and existing interventions.

### 2. Detect students who may need attention

Learning Success uses Moodle Analytics predictions together with available course information to identify students who may need attention.

Students can be shown with statuses such as:

- **Healthy** — there is currently no strong indication that the student requires intervention.
- **Monitor** — there are signals worth watching, but immediate intervention may not be necessary.
- **At risk** — there are stronger indications that the student may benefit from support.
- **Critical** — significant indicators may require prompt attention.

A status is an indicator, not a definitive judgement about a student.

### 3. Understand why a student is highlighted

Select a student to review the available explanation and learning signals.

Depending on the available Moodle data, these can include:

- Course activity and participation
- Recent access or inactivity
- Completion information
- Assessment or grade information
- Other relevant learning signals
- Existing intervention history

Use this information to understand the context before deciding whether action is appropriate.

### 4. Prioritise your work

Learning Success helps identify which students may need attention first.

Priority can take into account factors such as:

- Current risk level and score
- Severity and number of learning signals
- Whether the student already has an intervention
- Whether a follow-up is due
- The urgency of the current situation

This is intended to reduce the need to manually inspect every student in the course.

### 5. Review recommended actions

Learning Success can suggest possible next steps based on the student's current situation.

Examples include:

- Contact the student
- Encourage completion of missing work
- Recommend additional learning support
- Monitor progress
- Follow up on an earlier intervention

Recommendations are suggestions. Teachers remain responsible for deciding whether an action is appropriate for the individual student.

### 6. Record an intervention

When you decide that action is appropriate, create an intervention for the student.

Record the action you take and, where appropriate, send a Moodle message to the student as part of the intervention.

An intervention can keep track of:

- The action taken
- The intervention status
- The reason for the intervention
- The student's situation before the intervention
- The planned follow-up date
- Notes and outcome information

Recording interventions prevents work from being lost and makes it easier to continue supporting the student later.

### 7. Follow up

Return to Learning Success to review interventions that are waiting for follow-up.

Follow-up helps teachers check what happened after the original intervention instead of treating the first contact as the end of the process.

Depending on the situation, an intervention can move through statuses such as:

```text
Open → Contacted → Waiting → Follow-up → Completed
```

Other outcomes are also available when appropriate:

- **Dismissed** — no intervention is required.
- **Unable to contact** — an attempt was made but the student could not be contacted.
- **Not applicable** — the intervention is no longer relevant.

### 8. Complete the intervention and record the outcome

When the intervention is finished, complete it and record the outcome.

Learning Success can compare information from before and after the intervention to help show whether the student's situation changed.

Teachers can also record their own assessment of the outcome. This is useful because analytics data may not capture every reason why an intervention succeeded or failed.

The goal is to answer:

> Did the intervention appear to help?

### A typical teacher workflow

A typical session can be as simple as:

```text
Open Learning Success
        ↓
Review students needing attention
        ↓
Select a student
        ↓
Understand the reasons and signals
        ↓
Review the recommended action
        ↓
Decide whether to intervene
        ↓
Record the intervention
        ↓
Follow up when required
        ↓
Complete the intervention and record the outcome
```

### Understanding the data

Learning Success may combine several types of information to help teachers understand a student's situation:

- **Analytics prediction** — the current prediction supplied by the selected Moodle Analytics model.
- **Learning signals** — observable course activity or performance indicators.
- **Recommendation** — a suggested action based on the student's current situation.
- **Intervention history** — actions already taken by teachers.
- **Outcome** — information recorded after an intervention is completed.

The available information depends on the course, the selected Analytics model, and the data Moodle has collected for the student.

A student with insufficient recent data may not have the same level of explanation as a student with a longer learning history.

## Moodle Analytics

Learning Success is designed to complement Moodle Analytics rather than duplicate it.

```text
Moodle Analytics
      ↓
Identifies students who may need attention
      ↓
Learning Success
      ↓
Explains the situation
      ↓
Helps prioritise students
      ↓
Suggests possible actions
      ↓
Records interventions
      ↓
Follows up
      ↓
Measures outcomes
```

This separation allows Moodle Analytics to remain responsible for prediction while Learning Success focuses on the teacher's workflow.

## Compatibility

Supported and tested with:

- **Moodle:** 5.0 and later
- **PHP:** 8.2 and later
- **Databases:** supported Moodle database drivers for the supported Moodle versions
- **Browsers:** current versions of Chrome, Firefox, Edge, and Safari

The automated test matrix currently covers Moodle 5.1, Moodle 5.2, and the Moodle development branch with PHP 8.2, 8.3, and 8.4. Moodle 5.0 is supported by the plugin's minimum requirement but is not part of the current automated matrix.

Refer to the Moodle release requirements for the PHP and database requirements of your Moodle version.

## Upgrade

For normal Moodle plugin upgrades:

1. Back up your Moodle site according to your normal upgrade procedure.
2. Download the new Learning Success release.
3. Replace the existing `local/learningsuccess` plugin code with the new release.
4. Log in as an administrator, or run Moodle's normal upgrade process.
5. Go to **Site administration → Notifications** if Moodle does not automatically start the upgrade.

Read the Release Notes for version-specific changes before upgrading.

## Permissions

Learning Success uses Moodle's standard role and capability system.

Users must have the appropriate course and teaching permissions to view student information and manage interventions.

If your site uses separate groups, access to students is also subject to Moodle's group restrictions where applicable.

Administrators can manage access through Moodle's standard role and capability settings.

## Privacy

Learning Success works with student learning information already available in Moodle and stores intervention information entered through the plugin.

Schools and organisations should use the plugin in accordance with their own privacy policies, data-retention rules, and applicable data-protection requirements.

Only users with appropriate Moodle permissions should be given access to student information and intervention records.

## Troubleshooting

### No students are shown

Check that:

- Learning Success is enabled.
- Moodle Analytics is enabled.
- The configured Analytics model is available.
- The model has generated predictions where predictions are required.
- The course has enrolled students.
- Your account has the required permissions.

### No risk information is available

Learning Success depends on Moodle Analytics predictions for its Analytics-based risk information. If predictions have not yet been generated, there may be no risk information available.

Check the Analytics area in Moodle and make sure the selected model is enabled and has generated predictions.

### A student has limited or no explanation

The available explanation depends on the learning data Moodle has collected. New students, students with little course activity, or courses with limited assessment/completion data may have fewer signals available.

### A student is not in the priority list

Priority changes according to current risk information, learning signals, and intervention status. A student who already has an active intervention may not appear as a new intervention priority.

## Contributing

Contributions are welcome.

Please use the Issue Tracker to report bugs or request improvements. Code changes can be submitted through Pull Requests.

## License

Copyright (C) 2026 vuvanhieu143

Learning Success is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See the [LICENSE](LICENSE) file for more details.
