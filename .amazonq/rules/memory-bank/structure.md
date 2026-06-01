# Project Structure

## Directory Organization

```
Equipment-Lending-Website/
├── .amazonq/rules/memory-bank/     # AI assistant documentation
├── AJAX/                           # Asynchronous request handlers
├── CSS/                            # Stylesheets and design assets
├── includes/                       # Backend logic and utilities
├── JS/                             # Client-side JavaScript
├── uploads/                        # User-generated content
├── admin-dashboard.php             # Administrator interface
├── faculty-dashboard.php           # Faculty borrower interface
├── landing-page.php                # Public entry point with auth
└── return_confirm.php              # Equipment return verification
```

## Core Components

### Entry Points
- **landing-page.php**: Public-facing homepage with authentication modal
  - Role-based login (Faculty, Student, Admin)
  - Registration system for faculty accounts
  - Session management and redirects
  - Carousel hero section with branding

- **faculty-dashboard.php**: Faculty member workspace
  - Equipment browsing and search
  - Borrow request submission
  - Request history and tracking
  - Profile management
  - Notification center

- **admin-dashboard.php**: Administrative control panel
  - Real-time dashboard analytics
  - Inventory CRUD operations
  - Request arbitration interface
  - User management
  - System configuration

- **return_confirm.php**: Token-based return processing
  - Secure return verification
  - Status updates and logging

### Backend Logic (`includes/`)
- **admin-dashboard-functions.php**: Core admin operations
  - Database queries for inventory and requests
  - User management functions
  - Statistics aggregation

- **arbitration-engine.php**: AI-driven request processing
  - Rule evaluation logic
  - Priority-based decision making
  - Conflict resolution algorithms
  - Logging and audit trail

- **update-profile.php**: User profile management
  - Personal information updates
  - Profile picture uploads
  - Password changes

- **logout.php**: Session termination handler

- **poll-*.php**: Real-time data endpoints
  - Live inventory updates
  - Request status polling
  - Notification delivery

- **lending_db.sql**: Database schema and migrations
  - Table definitions
  - Initial data seeding
  - Schema evolution scripts

### AJAX Handlers (`AJAX/`)
- **admin-override.php**: Manual arbitration override
- **reprocess-request.php**: Re-evaluate declined requests
- **save-arbitration-config.php**: Update system rules
- **live-search.php**: Real-time equipment search

### Frontend Assets
- **CSS/**: Modular stylesheets
  - `admin-dashboard.css`: Admin interface styles
  - `faculty-dashboard.css`: Faculty interface styles
  - `landing-page.css`: Public page styles
  - `images-design/`: Hero carousel images

- **JS/**: Client-side interactivity
  - `admin-dashboard.js`: Admin panel logic
  - `admin-live-render.js`: Real-time UI updates
  - `faculty-dashboard.js`: Faculty dashboard interactions
  - `landing-page.js`: Authentication and carousel

### File Storage (`uploads/`)
- **profile_pictures/**: User avatars (named by faculty_id + timestamp)
- **request_letters/**: Supporting documentation for requests
- **[equipment images]**: Inventory item photos (timestamped)
- **default.png**: Fallback profile picture

## Architectural Patterns

### Database Architecture
- **MariaDB/MySQL** relational database (`lending_db`)
- **Tables**:
  - `tbl_users`: Faculty accounts with profile data
  - `tbl_accounts`: Admin credentials (legacy)
  - `tbl_inventory`: Equipment catalog with availability
  - `tbl_requests`: Borrow requests with status tracking
  - `tbl_arbitration_log`: Decision audit trail
  - `tbl_arbitration_config`: System rules and priorities
  - `tbl_room_reservations`: Future room booking feature

### Session Management
- PHP native sessions for authentication state
- Role-based session variables:
  - `$_SESSION['faculty_id']` for faculty users
  - `$_SESSION['admin']` for administrators
  - `$_SESSION['login_time']` for session tracking

### Request Lifecycle
1. **Submission**: Faculty submits via dashboard form
2. **Arbitration**: Engine evaluates against rules and inventory
3. **Decision**: Automatic approval/denial with logged reasoning
4. **Notification**: User alerted via dashboard and notifications
5. **Fulfillment**: Admin confirms pickup/return
6. **Completion**: Status updated, inventory adjusted

### Real-time Updates
- **Polling-based**: JavaScript intervals fetch latest data
- **Endpoints**: `poll-inventory.php`, `poll-requests.php`, `poll-requests-data.php`
- **UI Rendering**: Dynamic DOM updates without page refresh

### Security Layers
- **Password Hashing**: bcrypt for user credentials
- **Prepared Statements**: SQL injection prevention
- **Session Validation**: Role checks on protected pages
- **Input Sanitization**: htmlspecialchars() on all outputs
- **File Upload Validation**: Type and size restrictions

## Component Relationships

### Faculty Workflow
```
landing-page.php (login)
    ↓
faculty-dashboard.php (main interface)
    ↓
AJAX/live-search.php (equipment search)
    ↓
includes/arbitration-engine.php (request processing)
    ↓
return_confirm.php (return verification)
```

### Admin Workflow
```
landing-page.php (admin login)
    ↓
admin-dashboard.php (control panel)
    ↓
includes/admin-dashboard-functions.php (data operations)
    ↓
AJAX/admin-override.php (manual intervention)
    ↓
AJAX/save-arbitration-config.php (rule updates)
```

### Data Flow
```
User Input → PHP Validation → Database Query → Result Processing → JSON/HTML Response → UI Update
```

## Naming Conventions
- **Database Tables**: `tbl_` prefix (e.g., `tbl_users`, `tbl_inventory`)
- **PHP Files**: Kebab-case (e.g., `admin-dashboard.php`, `update-profile.php`)
- **CSS Classes**: Kebab-case with BEM-like modifiers (e.g., `stat-card`, `stat-card-clickable`)
- **JavaScript Functions**: camelCase (e.g., `openModal()`, `switchStudentTab()`)
- **Database Columns**: snake_case (e.g., `faculty_id`, `return_date`)
