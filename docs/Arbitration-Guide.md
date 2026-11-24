# Arbitration Guide

*Equipment borrowing and room reservations: the shared points rule, and how arbitration works*

PUPSYNC  |  Based on the latest system folder  |  November 2026

---

# Part 1. The shared points rule

A faculty member and the students they authorize share one borrowing allowance of 7 points. Each different item costs 1 point, and each student costs 1 point.

## The four numbers

The Super Admin sets these in Settings, Arbitration, Borrowing points.

| Setting | What it limits | Standard |
| --- | --- | --- |
| Different items per faculty | Different items a faculty member holds at the same time. Quantity is not limited. | 5 |
| Students per day | Students a faculty member can authorize on the same day. It starts over every day. | 3 |
| Shared ceiling | Items held plus students authorized on the same day, together. | 7 |
| Items per student | Different items in one student request. | 2 |

## How points are counted

- **Items:** 1 point for each different item. 25 projectors is still 1 point. Asking for the same item again adds nothing.
- **Students:** 1 point for each student for the day. A student's whole cart counts as one student, however many items are in it.
- **What counts:** an item counts while it is Waiting, Approved or On Loan and overlaps the new request's time. An overdue item always counts. A student counts on their borrow date.
- **What frees a point:** a declined, cancelled or no-show request frees its point. A returned item frees the faculty's item point. A student who returns early still uses their slot for that day.
- **Order:** requests are counted in the order they were filed. In a cart, the later items are the ones refused.

## Examples (with the standard 5 / 3 / 7)

| Situation | Result |
| --- | --- |
| Faculty has 5 items and 1 student that day | Fine (6 points) |
| Faculty has 5 items and 2 students | Fine (7 points). A 3rd student is refused (8 points). |
| Faculty has 4 items and 3 students | Fine (7 points). A 5th item is refused. |
| Faculty asks for a 6th different item | Refused. The limit is 5 different items. |
| Faculty asks for 25 units of one item | Fine. It is 1 point. Stock decides the quantity. |
| A 4th student for the same day | Refused. The limit is 3 students per day. |
| One student asks for 3 different items | The 3rd item is refused. The limit is 2 per student. |
| A student asks for the next day | Fine. Each day has its own allowance. |

## Where it is checked

- **When a request is submitted,** every way of submitting (faculty single item or cart, student with a faculty code, item code). A refused request gets the reason right away and is not saved.
- **Faculty Borrow form:** shows "N of 7 points used" for the chosen day, and flags the items of a cart that will not fit before it is submitted.
- **Student form:** warns the student when their faculty has no student slot left for the date and time they picked. The check at Submit still decides.
- **Admin approval is not limited** by points. The rule only applies when a request is submitted.

## What people see

| Who | Message |
| --- | --- |
| Faculty (too many items) | You can borrow at most 5 different items at a time, and you already have 5 booked or requested for that time. |
| Faculty (over the ceiling) | Your 7-point allowance for Tue, Nov 24 would be over: 5 items + 3 students you authorized (each item and each student uses 1 point). |
| Student (day is full) | Your faculty has already authorized the most students allowed for Tue, Nov 24 (3). Please choose another date, or ask your faculty. |
| Student (too many items) | One student request can include at most 2 different items. |

## Presets on the settings screen

| Preset | Items | Students | Ceiling | Items per student |
| --- | --- | --- | --- | --- |
| Standard | 5 | 3 | 7 | 2 |
| Busy period | 3 | 2 | 4 | 1 |
| Relaxed | 8 | 5 | 11 | 3 |

Clicking a preset only fills in the fields. Nothing changes until you press Save.

> **Note:** The ceiling is the dial for how much students count against the faculty. At 8 (5 + 3) students never compete with items. The lower the ceiling, the more they compete. If a database already saved "Items per borrower" as 3, it stays 3 until you save a new value.

---

# Part 2. How arbitration works

Arbitration is the set of automatic rules that decide every equipment request and room reservation: approve it, make it wait for an admin, or decline it. The Super Admin controls the numbers in Settings, Arbitration. Admins can still decide any request by hand.

## The path of an equipment request

1. **Submit.** A faculty member sends one item or a cart. A student sends a request with a faculty code. An item code works the same way.
2. **Checks.** The checks in the table below run first, the same for everyone. If one fails, the request is declined on the spot with the reason, and it is not saved.
3. **Today or later?** A same-day request goes through the 10-minute buffer. A later-date request waits for an admin.
4. **Approved.** The faculty gets Claim and Return QR codes (Ready for Pick Up).
5. **Collected and returned.** Pick up makes it On Loan, and returning it makes it Returned. If nobody collects it, it is Cancelled once its end time passes. If it is not returned on time, it becomes Overdue.

## Checks that run on every request

| Check | What it does | Default |
| --- | --- | --- |
| Overdue block | Declines a borrower who has an overdue item. | Off (switch) |
| Archived item | Declines an item that is no longer available. | Always on |
| Shared points | The rule in Part 1. | Always on |
| Request letter | A faculty member's own later-date request must carry a letter. | Off (switch) |
| Stock and schedule | A same-day request that cannot be done (item out right now, not enough free units) is declined. | Always on |

## Same-day requests

- After the checks above, the request is saved as Waiting and a **10-minute buffer** starts.
- An admin can approve or decline it at any time during the buffer.
- If nobody decides, the system approves it when every item has stock and nobody else has the item booked for that time. Otherwise it declines it.

## Later-date requests

- They **wait for an admin.** A later-date request is never approved automatically.
- If nobody decides, it is **declined automatically at 12:01 AM on the borrow date,** so the faculty finds out in the morning with the whole day left.
- A request submitted after 12:01 AM of its own borrow day is declined at its start time instead.

## When requests compete

When several requests want the same item at the same time, the first one filed wins. If they are tied, these tie-breaks apply, in order:

1. Request letter level (signatory)
2. Role weight
3. Good return history
4. Request ID (the lower one wins)

| Role | Weight | Who |
| --- | --- | --- |
| Student representative | 1 | A request a student filed with a faculty code |
| Regular faculty | 2 | A faculty member's own request |
| Priority | 4 | Faculty the Super Admin marked as priority (up to 10) |

Weights can be 1 to 10 in settings.

## Room reservations

- Checks first: the date is not in the past, the room is not under maintenance, and the overdue block if that switch is on. Then time clashes are settled first come, first served, with the same tie-breaks.
- Same-day reservations can be approved automatically (a setting, on by default).
- Later reservations wait for an admin. If nobody decides, the setting "If nobody decides" says when they are declined: at the start of the day (default), at the start time, or never.
- Other room limits are in the settings table below: active reservations, how far ahead, opening hours and days, and the longest reservation.

## Admin override

An admin can approve or decline any request by hand, whatever the automatic rules would say. The points limit applies when a request is submitted, so it does not stop an admin from approving.

## All settings (Super Admin only)

### Equipment: borrowing rules

| Setting | Default | Range |
| --- | --- | --- |
| Overdue block | Off | On / off |
| Request letter required (later-date, faculty) | Off | On / off |
| Different items per faculty | 5 | 1 to 20 |
| Students per day | 3 | 1 to 10 |
| Shared ceiling (points) | 7 | 1 to 30 |
| Items per student | 2 | 1 to 10 |
| Longest loan (days) | 3 | 1 to 3 |
| Extensions allowed | 1 | 0 to 1 |
| Low-stock alert (units) | 2 | 0 to 20 |

### Equipment: booking window

| Setting | Default | Range |
| --- | --- | --- |
| Opens / closes | 7:00 AM / 8:00 PM | On the hour |
| Book ahead up to (days) | 14 | 1 to 60 |
| Start-now grace (minutes). A request starting this soon counts as starting right now. | 15 | 0 to 60 |
| Cancel cutoff (minutes before start) | 60 | 0 to 240 |
| Loan lengths (hours) | 1, 2, 3, 6 | Any of 1 to 6 |

### Rooms

| Setting | Default | Range |
| --- | --- | --- |
| Active reservations per faculty | 2 | 1 to 10 |
| Book ahead up to (weeks) | 3 | 1 to 8 |
| Cancel cutoff (minutes before start) | 60 | 0 to 240 |
| Same-day reservations approved automatically | On | On / off |
| Request letter required | Off | On / off |
| If nobody decides | At start of the day | Start of day / start time / never |
| Opens / closes | 7:00 AM / 8:00 PM | On the hour |
| Open days | Mon to Sat | Any days |
| Longest reservation (hours) | 13 | 1 to 16 |

### Competing requests and semester

| Setting | Default | Range |
| --- | --- | --- |
| Role weights: student / faculty / priority | 1 / 2 / 4 | 1 to 10 each |
| Priority faculty | None | Up to 10 faculty |
| Semester name and dates | No active semester | 1st, 2nd, Summer, none |

## Good to know

- A request refused when it is submitted is not saved, and its log entries are removed. A request declined later, by an admin or by the deadline, stays in the faculty's history.
- There is no scheduled job. Deadlines are checked whenever an admin or faculty page refreshes.
- The engine also has a duplicate-request block, but it has no switch on the settings screen, so it is off.
- If a setting is missing or invalid, the system uses its default.
