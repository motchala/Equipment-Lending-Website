# Faculty Password Reset: Simple Guide

## What is this?
If a faculty member forgets their password, a **Super Admin** can now give them a new temporary one, safely and in a few clicks.

## How it works

1. **Faculty types the wrong password.** After **5 wrong tries in a row**, the sign-in page shows a **Notify Super Admin** button.
2. **Faculty presses it.** The Super Admin gets a notification, and that faculty's row in the Faculty list gets an orange **Reset requested** tag.
3. **Super Admin opens** Faculty > Edit. The **Password** section (with **Reset password**) appears there only for a faculty with a reset request.
4. **Super Admin types their own password**, then presses **Generate reset password**.
5. **The system makes a random new password**, replaces the old one, and shows it once. The Super Admin copies it and gives it to the faculty.
6. **Faculty signs in with it** and is asked to choose their own new password right away (just the password, nothing else). Then the portal opens.

## Safety rules

- The Password / Reset section stays **hidden** in Edit Faculty, and the server refuses a reset, until the faculty has used all 5 attempts **and** pressed **Notify Super Admin**.
- The Super Admin must enter **their own password** first.
- If the faculty signs in correctly on their own, the **request disappears by itself**, and the reset locks again.
- The old password stops working at once. The new one is shown **only once** and is never written to the activity log.
- If the faculty was already signed in somewhere, that session is **signed out**.
- Only a **Super Admin** can do any of this.

## Good to know

- The 5-try lockout (5 minutes at first) starts on the same wrong password that unlocks the Notify Super Admin button. The 5-attempt count keeps going across later lockouts.
- The password is handed over by the Super Admin. It is not emailed.

## Quick test

1. Type a wrong password 5 times for a faculty account. The **Notify Super Admin** button appears.
2. Press it. As Super Admin, check the notification and the **Reset requested** tag on the row.
3. Edit that faculty, press **Reset password**, enter your password, and **Generate**.
4. Sign in as the faculty with the new password. You land on the choose-a-password screen.
5. Set a new password. The portal opens, and the tag is gone.
