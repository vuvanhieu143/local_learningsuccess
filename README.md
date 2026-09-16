# Learning Success

**Learning Success** is a Moodle plugin that helps teachers identify students who may need support, understand the reasons behind potential difficulties, and take appropriate action.

It works alongside **Moodle Analytics** rather than replacing it.

The plugin turns analytics insights into a simple teacher workflow:

**Detect → Explain → Prioritise → Recommend → Intervene → Follow up → Measure**

## What does Learning Success do?

Learning Success helps teachers answer questions such as:

* Which students may need attention?
* Why might a student be struggling?
* Which students should I look at first?
* What action could I take?
* Have I already contacted this student?
* When should I follow up?
* Did the intervention appear to help?

Instead of only showing a risk score, Learning Success provides a workflow for turning that information into action.

## Requirements

* Moodle with **Moodle Analytics** enabled
* A configured Analytics model that provides student predictions
* A Moodle user with the appropriate course and teaching permissions

The plugin does not create a separate student-risk system. It uses information provided by Moodle Analytics.

## Installation

### 1. Download the plugin

Download or clone the `local_learningsuccess` plugin into:

```text
moodle/local/learningsuccess
```

The folder structure should look like:

```text
moodle/
└── local/
    └── learningsuccess/
```

### 2. Install through Moodle

Log in to Moodle as an administrator.

Go to:

**Site administration → Notifications**

Moodle will detect the new plugin and guide you through the installation.

Follow the on-screen instructions to complete the installation.

### 3. Configure Moodle Analytics

Learning Success uses Moodle Analytics to identify students who may require attention.

Before using the plugin, make sure:

1. Moodle Analytics is enabled.
2. An appropriate Analytics model is available.
3. The model has generated predictions for your courses.

If Moodle Analytics has not generated predictions yet, Learning Success may not have students to display.

## How to use

After installation, teachers can use Learning Success from a course to review students who may need attention.

The workflow is designed around seven simple steps.

### 1. Detect

Learning Success uses Moodle Analytics predictions to identify students who may require attention.

Students are grouped according to their current level of concern, helping teachers quickly understand where attention may be needed.

### 2. Explain

Select a student to see more information about why they may require support.

The explanation can include relevant learning signals such as:

* Course activity
* Participation
* Completion
* Assessment activity
* Other available learning indicators

The goal is to help teachers understand the situation rather than relying only on a risk score.

### 3. Prioritise

Not every student requires the same level of attention.

Learning Success helps prioritise students based on factors such as:

* Current risk
* Severity of learning signals
* Number of concerning signals
* Existing interventions
* Whether a follow-up is due

This allows teachers to focus on the students who need attention first.

### 4. Recommend

Learning Success can provide suggested actions based on the student's situation.

For example, a teacher may be encouraged to:

* Contact the student
* Encourage the student to complete missing work
* Recommend additional learning support
* Monitor the student's progress
* Follow up after an earlier intervention

Recommendations are intended to support teacher judgement, not replace it.

### 5. Intervene

When a teacher decides that action is appropriate, they can record the intervention.

An intervention can keep track of information such as:

* The action taken
* When the action was taken
* The reason for the intervention
* The student's situation before the intervention
* Planned follow-up

Teachers can also send a Moodle message as part of an intervention when appropriate.

### 6. Follow up

An intervention does not necessarily end after the first contact.

Learning Success can identify interventions that require follow-up so teachers can return to them at the appropriate time.

This helps avoid situations where a student is contacted once but then forgotten.

### 7. Measure the outcome

After an intervention, teachers can review what happened.

Learning Success can compare the student's situation before and after the intervention and record the outcome.

Teachers can also provide their own assessment of the outcome.

This helps answer:

> Did the intervention appear to help?

Over time, this can help teachers understand which types of support are useful for their students.

## Intervention statuses

An intervention moves through a simple workflow.

```text
Open
  ↓
Contacted
  ↓
Waiting
  ↓
Follow-up
  ↓
Completed
```

Depending on the situation, an intervention can also be:

* **Dismissed** — no intervention is required.
* **Unable to contact** — the teacher attempted to contact the student but could not.
* **Not applicable** — the intervention is no longer relevant.

These statuses help teachers keep track of what has happened and what still needs attention.

## Understanding student status

Learning Success uses student information from Moodle Analytics together with learning signals and intervention history.

A student may be shown as:

### Healthy

There is currently no strong indication that the student requires intervention.

### Monitor

There are some signals worth watching, but immediate intervention may not be necessary.

### At risk

There are stronger indications that the student may benefit from support.

### Critical

The student has significant indicators that may require prompt attention.

These statuses are intended to help teachers prioritise their work. They should not be treated as a definitive judgement about a student.

## Teacher judgement

Learning Success is a support tool for teachers.

Analytics predictions and learning signals are indicators, not conclusions. Teachers should consider the student's circumstances and use their professional judgement before taking action.

A teacher can also dismiss an intervention when they determine that no action is necessary.

## Moodle Analytics

Learning Success is designed to complement Moodle Analytics.

```text
Moodle Analytics
       ↓
 Identifies students
 who may need attention
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

## Permissions

Access to Learning Success follows Moodle's existing permission system.

Teachers need the appropriate permissions to view student information and manage interventions within their courses.

Administrators can control access using Moodle's standard role and capability settings.

## Privacy

Learning Success works with student learning information available within Moodle.

Schools and organisations should ensure that the plugin is used in accordance with their own privacy policies and applicable data-protection requirements.

Only users with appropriate Moodle permissions should be given access to student information and intervention data.

## Troubleshooting

### No students are shown

Check that:

* Moodle Analytics is enabled.
* An Analytics model is configured.
* The model has generated predictions.
* The course contains enrolled students.
* Your account has the required permissions.

### No risk information is available

Learning Success depends on Moodle Analytics predictions. If predictions have not yet been generated, there may be no risk information available to display.

Check the Analytics area in Moodle and make sure the relevant model is active and has generated predictions.

### A student does not appear in the priority list

The priority list changes according to the student's current learning signals, risk information, and intervention status.

A student who already has an active intervention may not appear as a new intervention priority.

## Uninstalling

The plugin can be removed through:

**Site administration → Plugins → Plugins overview**

Find **Learning Success** and follow Moodle's uninstall process.

Before uninstalling, make sure any intervention information that your organisation needs has been retained according to your data-retention policies.

## Copyright & License

Copyright (C) 2026 vuvanhieu143

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See the [LICENSE](LICENSE) file for more details.
