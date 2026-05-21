# Product: PUPSYNC — Equipment Lending System

PUPSYNC is a web-based equipment lending and room reservation platform for **PUP Biñan Campus** (Polytechnic University of the Philippines, Biñan, Laguna, Philippines).

## Purpose
Allows faculty and students to borrow school equipment (projectors, HDMI cables, extension cords, AC remotes, etc.) and reserve rooms through a centralized, tracked system.

## User Roles
- **Student** — Can view equipment availability and submit borrow requests. Read-only access to inventory.
- **Faculty** — Can borrow equipment and reserve rooms. Has a dedicated faculty dashboard.
- **Admin** — Full system control: manage inventory, approve/decline requests, view all borrow history, manage users.

## Core Features
- Role-based login (student, faculty, admin) from a single landing page
- Equipment borrow request workflow: Waiting → Approved / Declined / Overdue / Returned
- Automatic status transitions: expired requests auto-declined, overdue items auto-flagged
- Inventory management with soft-delete (archive/restore)
- Room reservation system
- Live search (AJAX) across request tables
- User profile management with profile picture upload
- Admin settings: theme, accent color, compact mode, font size, accessibility options

## Branding
- Product name: **PUPSYNC**
- Tagline: "Borrow smart, return proud."
- Primary color: maroon (`#600302`) — PUP brand color
- Timezone: Asia/Manila (UTC+8)
