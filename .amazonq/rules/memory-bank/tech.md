# Technology Stack

## Programming Languages

### PHP 8.2.12
- **Server-side scripting**: All backend logic and database interactions
- **Session management**: Native PHP sessions for authentication
- **Password hashing**: `password_hash()` and `password_verify()` with bcrypt
- **Database connectivity**: MySQLi extension with prepared statements
- **File handling**: Image uploads and profile picture management

### JavaScript (ES6+)
- **Client-side interactivity**: Form validation, modal controls, real-time updates
- **AJAX requests**: Fetch API and XMLHttpRequest for asynchronous operations
- **DOM manipulation**: Dynamic content rendering without page reloads
- **Event handling**: User interactions, polling intervals, notification updates

### SQL (MariaDB 10.4.32)
- **Database schema**: Table definitions with foreign key relationships
- **Queries**: Complex joins, aggregations, and conditional logic
- **Transactions**: ACID compliance for critical operations
- **Indexes**: Optimized lookups for tokens and foreign keys

### HTML5
- **Semantic markup**: Proper document structure with accessibility attributes
- **Forms**: Input validation with HTML5 attributes (required, minlength, pattern)
- **ARIA labels**: Screen reader support for interactive elements

### CSS3
- **Custom properties**: CSS variables for theming and consistency
- **Flexbox/Grid**: Modern layout techniques
- **Animations**: Transitions and keyframe animations for UI polish
- **Media queries**: Responsive design for mobile and desktop

## Build System & Dependencies

### Development Environment
- **XAMPP**: Local development stack (Apache + MariaDB + PHP)
- **Apache HTTP Server**: Web server with mod_rewrite enabled
- **MariaDB**: MySQL-compatible database server
- **phpMyAdmin**: Database administration interface

### External Libraries

#### Frontend
- **Google Fonts**: 
  - Cormorant Garamond (serif, display text)
  - Outfit (sans-serif, UI elements)
- **Font Awesome 6.0.0**: Icon library via CDN
  - Solid icons for UI elements
  - Brand icons for social media
  - Regular icons for toggles

#### No Build Tools
- **No npm/webpack**: Pure vanilla JavaScript, no transpilation
- **No CSS preprocessors**: Native CSS with custom properties
- **No frontend framework**: Vanilla JS for maximum performance

## Database Configuration

### Connection Details
```php
$conn = mysqli_connect("localhost", "root", "", "lending_db");
```

### Database Schema
- **Engine**: InnoDB (supports transactions and foreign keys)
- **Charset**: utf8mb4 (full Unicode support including emojis)
- **Collation**: utf8mb4_general_ci (case-insensitive)

### Key Tables
```sql
tbl_users          -- Faculty accounts (faculty_id PK)
tbl_accounts       -- Admin credentials (email UNIQUE)
tbl_inventory      -- Equipment catalog (item_id PK, AUTO_INCREMENT)
tbl_requests       -- Borrow requests (id PK, status ENUM)
tbl_arbitration_log       -- Decision audit trail (request_id UNIQUE)
tbl_arbitration_config    -- System configuration (config_key UNIQUE)
tbl_room_reservations     -- Future feature (id PK)
```

## Development Commands

### Database Setup
```bash
# Import schema and seed data
mysql -u root -p lending_db < includes/lending_db.sql

# Or via phpMyAdmin
# Navigate to http://localhost/phpmyadmin
# Import includes/lending_db.sql
```

### Local Server
```bash
# Start XAMPP services
# Apache: Port 80 (or configured port)
# MySQL: Port 3306

# Access application
http://localhost/Equipment-Lending-Website/landing-page.php
```

### File Permissions
```bash
# Ensure uploads directory is writable
chmod 755 uploads/
chmod 755 uploads/profile_pictures/
chmod 755 uploads/request_letters/
```

## Configuration Files

### PHP Configuration
- **Timezone**: `date_default_timezone_set('Asia/Manila')`
- **Session settings**: Default PHP session configuration
- **Upload limits**: Configured in php.ini
  - `upload_max_filesize = 10M`
  - `post_max_size = 10M`

### Database Migrations
- **Schema evolution**: ALTER TABLE statements in lending_db.sql
- **Column additions**: `IF NOT EXISTS` checks for safe migrations
- **Data seeding**: INSERT IGNORE for idempotent setup

## Security Measures

### Authentication
- **Password hashing**: bcrypt with cost factor 10
- **Session validation**: Role checks on every protected page
- **Login throttling**: Manual implementation (not automated)

### Database Security
- **Prepared statements**: All user inputs parameterized
- **Input validation**: Server-side checks before database operations
- **SQL injection prevention**: No string concatenation in queries

### File Upload Security
- **Type validation**: Check MIME types and extensions
- **Filename sanitization**: Timestamp prefixes to prevent collisions
- **Directory restrictions**: Uploads stored outside web root where possible

### XSS Prevention
- **Output escaping**: `htmlspecialchars()` on all user-generated content
- **Content Security Policy**: Not implemented (future enhancement)

## Performance Optimizations

### Database
- **Indexes**: Primary keys, unique constraints, foreign keys
- **Query optimization**: SELECT only needed columns
- **Connection pooling**: Single connection per request (no persistent)

### Frontend
- **Lazy loading**: Font Awesome loaded with `media="print" onload`
- **Image preloading**: Hero images preloaded for faster LCP
- **Minification**: Not implemented (future enhancement)

### Caching
- **Browser caching**: Relies on default Apache headers
- **Session caching**: PHP default session storage (files)
- **Query caching**: MySQL query cache (if enabled)

## Browser Compatibility
- **Target browsers**: Modern evergreen browsers (Chrome, Firefox, Safari, Edge)
- **ES6+ features**: Arrow functions, template literals, const/let
- **CSS features**: Custom properties, flexbox, grid
- **Fallbacks**: Minimal (assumes modern browser support)

## Deployment Requirements

### Server Requirements
- **PHP**: 8.0 or higher
- **MySQL/MariaDB**: 5.7+ / 10.2+
- **Apache**: 2.4+ with mod_rewrite
- **PHP Extensions**: mysqli, gd (for image processing), session

### File Structure
- **Document root**: Point to project root directory
- **Writable directories**: `uploads/`, `uploads/profile_pictures/`, `uploads/request_letters/`
- **Database**: Import `includes/lending_db.sql` on fresh installation

### Environment Variables
- **None**: Database credentials hardcoded (not production-ready)
- **Future**: Move to environment variables or config file

## Version Control
- **Git**: Repository tracked with `.gitignore`
- **Ignored files**: 
  - Uploaded images (except default.png)
  - IDE configuration files
  - Temporary files
