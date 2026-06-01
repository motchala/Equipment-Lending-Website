# Product Overview

## Project Purpose
PUPSYNC is a centralized equipment lending and resource management system designed for PUP Biñan Campus. It streamlines the borrowing process for faculty members, enabling them to request, track, and return school equipment through a secure web-based platform.

## Value Proposition
- **Centralized Resource Hub**: Single platform for managing all equipment lending operations
- **Automated Request Processing**: AI-driven arbitration engine for intelligent request approval/denial
- **Real-time Tracking**: Live inventory monitoring and request status updates
- **Secure Authentication**: Role-based access control for faculty, students, and administrators
- **Transparent Operations**: Complete audit trail with arbitration logs and override tracking

## Key Features

### For Faculty (Borrowers)
- **Equipment Browsing**: Search and filter available equipment by category
- **Borrow Requests**: Submit requests with instructor details, room assignments, and date ranges
- **Request Tracking**: Monitor pending, approved, declined, and overdue requests
- **Profile Management**: Update personal information, contact details, and profile pictures
- **Notification System**: Real-time alerts for overdue items, request approvals, and system updates
- **Return Confirmation**: Token-based return verification system

### For Administrators
- **Dashboard Analytics**: Real-time statistics on inventory, requests, and user activity
- **Inventory Management**: Add, edit, archive equipment with image uploads
- **Request Arbitration**: AI-powered decision engine with manual override capabilities
- **User Management**: View and manage faculty accounts and borrowing history
- **Configuration Panel**: Adjust arbitration rules, role priorities, and system settings
- **Audit Logging**: Complete history of all arbitration decisions and admin actions

### Intelligent Arbitration System
- **Automated Decision Making**: Rules-based approval/denial with configurable parameters
- **Priority-Based Allocation**: Role hierarchy (Director > Adviser > Faculty > Student)
- **Conflict Resolution**: Tie-breaking logic for simultaneous requests
- **Blocking Rules**: Automatic denial for overdue borrowers, duplicate requests, missing documentation
- **Admin Override**: Manual intervention with reason tracking for exceptional cases

## Target Users

### Primary Users
- **Faculty Members**: Teaching staff who need to borrow equipment for classes and activities
- **Administrators**: Staff managing inventory, approving requests, and maintaining the system

### Future Users
- **Students**: View-only access to equipment availability (portal under development)
- **Department Heads**: Enhanced approval workflows and departmental analytics

## Use Cases

1. **Equipment Borrowing Workflow**
   - Faculty logs in and browses available equipment
   - Submits request with class details and date range
   - Arbitration engine evaluates request against rules and inventory
   - Faculty receives notification of approval/denial
   - Upon approval, faculty picks up equipment with confirmation
   - Returns equipment using token-based verification

2. **Inventory Management**
   - Admin adds new equipment with photos and categorization
   - System tracks quantity and availability in real-time
   - High-value items flagged for special handling
   - Archived items removed from active borrowing pool

3. **Overdue Management**
   - System automatically detects overdue returns
   - Faculty receives notifications and dashboard alerts
   - Borrowing privileges suspended until return
   - Admin can manually mark items as returned

4. **Request Arbitration**
   - Multiple faculty request same equipment
   - Engine evaluates based on role priority, timing, and history
   - Automatic approval/denial with logged reasoning
   - Admin can override decisions with justification
