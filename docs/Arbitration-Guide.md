# Borrowing Points: Simple Guide

*How the points work, what they mean for faculty and students, and how requests are decided*

PUPSYNC  |  November 2026

---

# 1. How the points work

Every faculty member gets **7 points each day**. The faculty member and the students they authorize use the **same** 7 points.

- **1 point** for each different item the faculty borrows. The quantity does not matter: 25 chairs is still 1 point.
- **1 point** for each student the faculty authorizes that day. A student's whole request is 1 point.
- The faculty is responsible for their students, so the students' points come out of the faculty's 7.

## A day in points

Example: a faculty member borrows 3 different items and authorizes 2 students.

`[ Item ] [ Item ] [ Item ] [ Student ] [ Student ] [ free ] [ free ]`

**5 of 7 points used. 2 points left.**

The 2 free points could be 2 more items, or 1 more item and 1 more student.

## The four limits

| Limit | Number | In plain words |
| --- | --- | --- |
| Different items | 5 | The most different items a faculty member can have borrowed at the same time. |
| Students | 3 | The most students a faculty member can authorize in one day. |
| Total points | 7 | Items and students added together can never be more than this. |
| Items per student | 2 | The most different items in one student's request. |

Quantity has no limit of its own. The stock decides how many units can be borrowed.

---

# 2. What it means for faculty

- You can borrow up to **5 different items** at a time, in any quantity.
- You can authorize up to **3 students** a day.
- Items and students together can **not go over 7 points**.
- Each student you authorize uses **1 of your points** for the day they borrow.
- The Borrow form shows "**N of 7 points used**" before you submit, and marks the items that will not fit.
- If you go over, the request is **refused right away** with the reason, and nothing is saved. Remove an item, or ask a student to pick another day.

## When do points come back?

| What happens | Points back? |
| --- | --- |
| A request is declined or cancelled, or nobody collects it | Yes |
| You return the item | Yes, the item's point |
| A student returns the item early | No. The student's point stays used for that day. |
| A new day starts | Yes. Students' points start fresh each day. |
| An item is overdue | No. It still uses a point. |

---

# 3. What it means for students

- A student has **no points of their own.** They use 1 of their faculty's points for the day they pick.
- A student can ask for up to **2 different items** in one request, in any quantity. The whole request is 1 point.
- A student borrows for a few hours on a single day.
- When the student picks a date and time, the form **warns them** if their faculty has no points left for that day. They can pick another day or ask their faculty.
- If the day is full and they submit anyway, the request is **refused right away** with the same reason.

---

# 4. Examples

With the standard limits (5 items, 3 students, 7 points):

| Situation | Result |
| --- | --- |
| Faculty has 5 items and 1 student | Fine. 6 points. |
| Faculty has 5 items and 2 students | Fine. 7 points. A 3rd student is refused. |
| Faculty has 4 items and 3 students | Fine. 7 points. A 5th item is refused. |
| Faculty asks for a 6th different item | Refused. The limit is 5. |
| Faculty asks for 25 units of one item | Fine. It is 1 point. |
| A 4th student on the same day | Refused. The limit is 3 per day. |
| A student asks for 3 different items | The 3rd item is refused. The limit is 2. |
| A student picks another day | Fine. Each day starts fresh. |

## Messages people see

| Who | Message |
| --- | --- |
| Faculty | You can borrow at most 5 different items at a time, and you already have 5 booked or requested for that time. |
| Faculty | Your 7-point allowance for Tue, Nov 24 would be over: 5 items + 3 students you authorized (each item and each student uses 1 point). |
| Student | Your faculty has already authorized the most students allowed for Tue, Nov 24 (3). Please choose another date, or ask your faculty. |

---

# 5. How requests are decided (arbitration)

Arbitration is the set of automatic rules that decide every equipment request and room reservation: **approve** it, make it **wait for an admin**, or **decline** it. The Super Admin sets the numbers. Admins can still decide any request by hand, even over the points limit.

## What happens to an equipment request

1. **Submit.** A faculty member, or a student with a faculty code, sends the request.
2. **Checks.** The system checks the points and archived items, and also overdue items and the request letter if those switches are on. If a check fails, the request is refused right away and is not saved. (A request declined later, by an admin or by the deadline, stays in the faculty's history.)
3. **Same-day request.** It waits 10 minutes. An admin can decide at any time. If nobody does, the system approves it when stock is free and nobody else has the item booked, and declines it otherwise.
4. **Later-date request.** It waits for an admin. If nobody decides, it is declined at 12:01 AM on the borrow date. It is never approved automatically.
5. **After approval.** The faculty gets QR codes. Picking up makes it On Loan, and returning it makes it Returned. Not collected: Cancelled. Not returned on time: Overdue.

## If two requests want the same item

The first request filed wins. If they are tied, these break the tie, in order:

1. The request letter level
2. The role: priority faculty (4), faculty (2), student (1)
3. A good return history
4. The request number (lower wins)

## Room reservations

- The system checks the date is not in the past and the room is not under maintenance. Clashes are settled first come, first served.
- Same-day reservations can be approved automatically. Later ones wait for an admin and are declined if nobody decides in time. The limits per faculty and the open hours are in the settings below.

## Main settings (Settings, Arbitration, Super Admin only)

| Setting | Standard |
| --- | --- |
| Different items / students per day / total points / items per student | 5 / 3 / 7 / 2 |
| Longest loan, extensions allowed | 3 days, 1 extension |
| Equipment hours, book ahead | 7 AM to 8 PM, up to 14 days |
| Room reservations per faculty, book ahead | 2 at a time, up to 3 weeks |
| Room hours, longest reservation | 7 AM to 8 PM, Mon to Sat, 13 hours |
| Overdue block, request letter required | Both off |
| Presets (fill in the limits; press Save to apply) | Standard 5/3/7/2, Busy 3/2/4/1, Relaxed 8/5/11/3 |
