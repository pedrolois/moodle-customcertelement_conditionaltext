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
| **Course completion** | Has completed / has not completed the course |
| **User profile field** | Standard fields (department, institution, city, country, language, ...) or custom profile fields; *is equal to, is not equal to, contains, does not contain, is empty, is not empty* |
| **Always** | Always matches (an explicit catch-all) |

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

Rule texts support HTML, Moodle filters (for example multi-language content) and these placeholders:
`{firstname}`, `{lastname}`, `{fullname}`, `{coursename}`, `{courseshortname}`, `{coursegrade}`.
Font, size, colour, width, position and alignment work like the standard Text element.

## Behaviour

- Conditions are always evaluated with the data of the **certificate recipient**, never with the data of the
  person who views or downloads the certificate.
- Learners without a grade never match a grade rule.
- In the certificate preview and in the drag-and-drop editor, the first rule's text is shown.
- Custom profile fields that are hidden from everyone are only offered to designers who can view all user details.
- Template export/import and course backup/restore are supported; grade item references are remapped on restore.

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
