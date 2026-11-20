# Service survey reporting and export handoff

## When a faculty member is surveyed

- A survey is offered once per **successful transaction of the faculty member's own**: an equipment borrow
  that has been fully returned (a cart/batch counts as one transaction), or a room reservation that was
  approved and has ended.
- Borrows and reservations a **student made with the faculty member's code** (`submitted_as = 'student'`)
  never trigger a survey for the faculty member.
- The pop-up cannot be skipped. There is no Skip/X button, Escape and clicks outside do nothing, and a page
  refresh brings it back a couple of seconds after the page loads. Showing it writes nothing; the transaction
  is only used up when an answer is submitted (`tbl_service_survey_completions`, `answered = 1`), after which
  it is never offered again. `survey-submit.php` rejects the old `skipped` flag.
- It closes by itself only if the Super Admin turns the survey off or the transaction stops being eligible.
- Rows in `tbl_service_survey_completions` with `answered = 0` come from the older skippable behaviour; they
  stay used up and are not offered again.

## Reporting windows

The Super Admin configures `semester_start` and `semester_end` in
`tbl_service_survey_config`. Both dates must be set, or both left blank. A
configured semester is split into consecutive 14-day windows anchored to its
start date; the final window ends on the configured semester end date.

Each submitted survey is recorded in its active window. The completion marker,
legacy counters, period aggregates, and optional anonymous comment are written
as one transaction. Skipped surveys are not counted as responses or ratings.

## Data available for exports

- `tbl_service_survey_periods`: one row per window, uniquely identified by
  `semester_start`, `semester_end`, and `period_start`. It contains the window
  boundaries, response count, rating count, and sum of all star scores.
- `tbl_service_survey_period_questions`: question-level aggregates keyed by
  period, question ID, and question-text version. `question_text` is a snapshot,
  so reports retain the wording used when ratings were submitted. It includes
  response count, score sum, and counts for each of the five star values.
- `tbl_service_survey_comments`: anonymous comments with a `period_id` linking
  them to their reporting window. Existing comments have a `NULL` period ID
  because their exact historical window cannot be recovered.
- `tbl_service_survey_config`: the currently configured semester boundaries.
- `tbl_service_survey_questions`: legacy/global counters. They remain available
  for compatibility but do not contain timestamps or period attribution.

## Overall rating formula

For a period or semester-to-date total, calculate:

`SUM(total_score) / SUM(rating_count)`

Use the period rows belonging to the configured semester for semester-to-date
reporting. Do not average the displayed period averages; periods can have
different numbers of ratings. The dashboard withholds overall and
question-level rating breakdowns until at least five responses are available.

## Historical data

Ratings and comments collected before period reporting was configured, or
submitted outside the configured semester dates, remain unperiodized. Do not
assign those legacy aggregates or `NULL`-period comments to a semester or
window: the source data has no timestamps that would support that attribution.
Period records are retained when a later semester is configured, allowing
exports to include past windows. Individual survey answers and transaction
identifiers are not stored in the reporting aggregates.
