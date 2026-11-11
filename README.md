# Conditional text — Custom certificate element

A [Custom certificate](https://moodle.org/plugins/mod_customcert) element that prints **different text
depending on the learner who receives the certificate**. One certificate design can say *"With
distinction"*, *"With merit"* or *"Successfully completed"*, print a programme name per department,
or show a line only to learners who completed the course.

## How it works

Add the **Conditional text** element to a certificate page and define one or more rules. Each rule has a
condition and the text to print when the condition is met.

| Condition | Options |
|---|---|
| **Grade** | Course total or any grade item / activity; `≥ > ≤ < = ≠`; value as a percentage or in points |
| **Course completion** | The learner has completed the course |
| **Course progress (%)** | Percentage of tracked activities completed, as in the course progress bar |
| **Activity completion** | The learner has completed (or passed) a given activity |
| **User profile field** | Standard fields (department, institution, city, country, language, ...) or custom profile fields; *is equal to, is not equal to, contains, does not contain, is empty, is not empty* |
| **Group** | Member of a group of the course |
| **Cohort** | Member of a cohort |
| **Role in the course** | For example Non-editing teacher → "as Facilitator" |
| **Badge earned** | A site badge or a badge of the course |
| **Competency achieved** | Rated proficient in a competency of the course |
| **Enrolment method** | Enrolled through manual, self, PayPal, ... enrolment |
| **Date** | Completion, certificate issue or enrolment date, before / on or after a date |
| **Time spent in the course** | Hours, computed from the logs like the certificate's "required time" |
| **Days to complete the course** | Days from enrolment to completion (for example "fast track" ≤ 30 days) |
| **Always** | Matches everybody |

Every rule can be **inverted** (apply when the condition is *not* met) and **combined with the next rule**
(AND): a chain of combined rules prints the text of its last rule only when all of them are met.
To express OR, add separate rules with the same text.

- **First matching rule** (default): rules are checked from top to bottom and the first match wins.
  Order them from the most to the least demanding (90 %, then 75 %, then 50 %).
- **All matching rules**: every matching text is printed, one per line.
- **Text when no rule matches**: printed when nothing matches. Leave it empty to print nothing.

Example:

| # | Condition | Text |
|---|---|---|
| 1 | Course total ≥ 90 % | `WITH DISTINCTION` |
| 2 | Course total ≥ 75 % | `WITH MERIT` |
| Otherwise | | `Successfully completed` |

### Ready-made examples

The **Load an example** menu fills the form with a set of rules you can adapt before saving:
Distinction / Merit / Pass, Passed or attended, Excellent / Very good / Good, Completed or participated,
Achievement lines, With honours (two conditions), By preferred language, By department, By group, By cohort,
Facilitator or participant, Hours of training, Fast track and By course progress.

Rule texts support HTML, Moodle filters (for example multi-language content) and these placeholders:
`{firstname}`, `{lastname}`, `{fullname}`, `{coursename}`, `{courseshortname}`, `{coursegrade}`.
Font, size, colour, width, position and alignment work like the standard Text element.

## Behaviour

- Conditions are always evaluated with the data of the **certificate recipient**, never with the data of the
  person who views or downloads the certificate.
- Learners without a grade never match a grade rule.
- In the certificate preview and in the drag-and-drop editor, the first rule's text is shown.
- Custom profile fields that are hidden from everyone are only offered to designers who can view all user details.
- Template export/import and course backup/restore are supported; grade item, activity, group and course badge references are remapped on restore.
- Rules that point to something that no longer exists (a deleted group, activity, badge...) never apply,
  inverted or not.

## Requirements

- Moodle 5.3
- Custom certificate (`mod_customcert`) 5.3 or later

## Installation

Copy the plugin to `mod/customcert/element/conditionaltext` (in Moodle 5.1+ code layout:
`public/mod/customcert/element/conditionaltext`) and visit *Site administration → Notifications*.

## Privacy

The element stores no personal data.

## License

GNU GPL v3 or later. © 2026 Pedro Lois.
