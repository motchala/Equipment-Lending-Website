<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: landing-page.php");
    exit();
}
$fullname = $_SESSION['fullname'];

$user_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $fullname)));

$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* ============================
   1. HANDLE BORROW REQUEST
   ============================ */
// checks both the button click and the hidden input from JS
if (isset($_POST['borrow_submit']) || isset($_POST['equipment_name'])) {

    if (!isset($_SESSION['user_id'])) {
        die("Unauthorized access");
    }

    $instructor = preg_replace("/[^a-zA-Z\s.']/", "", $_POST['instructor']);
    $instructor = mysqli_real_escape_string($conn, trim($instructor));

    $user_id = $_SESSION['user_id'];

    // Fetch user details safely
    $user_query = mysqli_query($conn, "SELECT fullname, student_id FROM tbl_users WHERE student_id = '" . mysqli_real_escape_string($conn, $user_id) . "'");
    $user = mysqli_fetch_assoc($user_query);

    if (!$user) {
        die("User profile not found.");
    }

    $student_name = $user['fullname'];
    $student_id = $user['student_id'];

    // Sanitize all inputs to prevent SQL Injection
    $borrow_date = mysqli_real_escape_string($conn, $_POST['borrow_date']);
    $return_date = mysqli_real_escape_string($conn, $_POST['return_date']);
    $equipment_name = mysqli_real_escape_string($conn, $_POST['equipment_name']);
    $room = mysqli_real_escape_string($conn, $_POST['room']);
    $instructor = mysqli_real_escape_string($conn, $_POST['instructor']);

    /* --- START DATE VALIDATION --- */
    $current_date = date('Y-m-d'); // Get today's date on server

    // 1. Check if Borrow Date is in the past
    if ($borrow_date < $current_date) {
        die("Error: You cannot select a borrow date in the past.");
    }

    // 2. Check if Return Date is before Borrow Date
    if ($return_date < $borrow_date) {
        die("Error: Return date cannot be before the borrow date.");
    }
    /* --- END DATE VALIDATION --- */


    // Insert into tbl_requests
    $insert_query = "INSERT INTO tbl_requests 
                    (student_name, student_id, equipment_name, instructor, room, borrow_date, return_date, status, request_date) 
                    VALUES 
                    ('$student_name', '$student_id', '$equipment_name', '$instructor', '$room', '$borrow_date', '$return_date', 'Waiting', NOW())";

    if (mysqli_query($conn, $insert_query)) {
        // Successful insert, redirect to dashboard
        header("Location: user-dashboard.php?success=1");
        exit();
    } else {
        die("Error processing request: " . mysqli_error($conn));
    }
}

/* ============================
   2. INVENTORY LOADING
   ============================ */
$category_result = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory ORDER BY category ASC");
$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory ORDER BY item_name ASC");

/* ============================
   3. MY REQUESTS
   ============================ */
$my_requests_result = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $my_requests_result = mysqli_query($conn, "SELECT * FROM tbl_requests WHERE student_id = '$user_id' ORDER BY request_date DESC");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EQUIPLEND | User Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">


    <style>
        /* naka organized na sya gois :> */
        /* =========================================
       1. GLOBAL SETTINGS & VARIABLES
       Defines the root variables and base body styles.
       ========================================= */
        :root {
            --equi-red: #8B0000;
        }

        body {
            background-color: #f4f4f4;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* =========================================
       2. HEADER REGION
       Top navigation bar and its internal trigger buttons.
       ========================================= */
        header.navbar-custom {
            background-color: var(--equi-red);
            color: white;
            height: 60px;
            flex-shrink: 0;
            z-index: 1050;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .menu-toggle {
            cursor: pointer;
            font-size: 1.2rem;
            color: white;
            border: none;
            background: none;
            margin-right: 15px;
        }

        /* =========================================
       3. SIDEBAR LAYOUT (PARENT CONTAINERS)
       Defines the sidebar container, its collapsed state, and internal layout structure.
       ========================================= */
        aside#sidebar {
            width: 285px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: white;
            border-right: 1px solid #ddd;
            transition: margin-left 0.3s ease;
            height: 100%;
            position: relative;
            z-index: 1060;
        }

        aside#sidebar.collapsed {
            margin-left: -280px;
        }

        /* Inner Flex Container for Navigation Links */
        nav.nav.flex-column {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            margin: .1%;
        }

        /* Sidebar Footer Container (Logout Area) */
        .sidebar-footer {
            margin-top: auto;
            margin-bottom: 15px;
            padding: 15px 5px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* =========================================
       4. SIDEBAR ITEMS (CHILDREN)
       Styles for the actual clickable links and buttons inside the sidebar.
       ========================================= */

        /* -- Standard Navigation Links -- */
        .nav-link {
            color: #444;
            font-weight: 500;
            border-left: 5px solid transparent;
            transition: 0.2s;
            padding: 15px 25px;
        }

        .nav-link.active {
            background: #f8eaea;
            color: var(--equi-red);
            border-left-color: var(--equi-red);
        }

        .nav-link i {
            width: 25px;
            display: inline-block;
            text-align: center;
        }

        /* -- Sidebar Buttons (e.g., Logout) -- */
        .sidebar-btn {
            background: none;
            border: none;
            border-left: 4px solid transparent;
            color: #8B0000;
            font-weight: 600;
            padding: 14px 25px;
            display: flex;
            align-items: center;
            width: 100%;
            text-align: left;
            transition: 0.2s ease;
            cursor: pointer;
        }

        .sidebar-btn:hover {
            color: #fff;
            background: rgba(139, 0, 0, 0.3);
        }

        .sidebar-btn.active {
            color: #fff;
            background: rgba(139, 0, 0, 0.5);
            border-left: 4px solid #8B0000;
            font-weight: 600;
        }

        .sidebar-btn i {
            font-size: 1.25rem;
            width: 25px;
            display: inline-block;
            color: #8B0000;
        }

        /* -- Shared Icon Spacing Rule -- */
        /* This connects both Nav Links and Sidebar Buttons to ensure identical spacing */
        .nav-link i,
        .sidebar-btn i {
            margin-right: 15px !important;              /* Micro-adjustment: change this number to taste */
        }

        /* =========================================
       5. MAIN CONTENT AREA
       The area to the right of the sidebar where content loads.
       ========================================= */
        main {
            flex-grow: 1;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 25px;
            background-color: #f4f4f4;
        }

        /* =========================================
       6. COMPONENT STYLING
       Specific UI elements used within the Main Content area.
       ========================================= */

        /* -- General Card Container -- */
        article.card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        /* -- Equipment Grid Cards -- */
        figure.equipment-card {
            border: 1px solid #eee;
            border-radius: 12px;
            transition: 0.3s;
            margin: 0;
            padding: 20px;
            text-align: center;
        }

        figure.equipment-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }

        /* -- Table Styling -- */
        .table thead th {
            background-color: #222;
            color: white;
            border: none;
            font-size: 0.85rem;
        }

        /* -- Status Indicators -- */
        .status-pill {
            height: 12px;
            width: 28px;
            border-radius: 4px;
            display: inline-block;
            background-color: #FFB300;
        }

        /* =========================================
       7. UTILITY & STATE CLASSES
       Helpers for hiding elements or showing overlays.
       ========================================= */
        .hidden {
            display: none !important;
        }

        /* Loading Overlay States */
        #loading-overlay.active {
            display: flex !important;
        }

        #loading-overlay.hidden {
            display: none !important;
        }
    </style>
</head>

<body>
    <header class="navbar-custom px-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="h5 mb-0 text-white fw-bold">EQUIPLEND <small class="fw-light opacity-75">USER</small></h1>
        </div>

        <div class="text-white small d-none d-md-block">
            <i class="fas fa-user-circle me-1"></i> Welcome,
            <strong>
                <?php
                // Split the name by space
                $name_parts = explode(' ', trim($fullname));
                // Get the first part (the first name)
                $firstname = $name_parts[0];
                echo htmlspecialchars($firstname);
                ?>
            </strong>
        </div>
    </header>

    <div class="d-flex flex-grow-1 overflow-hidden">
        <aside id="sidebar">
            <nav class="nav flex-column mt-3">
                <button class="nav-link active text-start border-6 w-100 bg-transparent" id="btn-browse"
                    onclick="showSection('browser-section', 'btn-browse')">
                    <i class="fas fa-search me-2"></i> Browse Equipment
                </button>
                <button class="nav-link text-start border-6 w-100 bg-transparent" id="btn-status"
                    onclick="showSection('status-section', 'btn-status')">
                    <i class="fas fa-clipboard-check me-2"></i> My Requests
                </button>
            </nav>
            <div class="sidebar-footer">
                <button type="button" class="sidebar-btn border-6" onclick="handleLogout()">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </button>
            </div>
        </aside>

        <main>
            <?php if (isset($_GET['success'])): ?>
                <div id="success-alert" class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <strong>Success!</strong> Your borrow request has been
                    submitted for approval.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div id="overdue-alert" class="alert alert-danger shadow-sm hidden" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong>Overdue Alert:</strong> Please return your
                equipment to the laboratory immediately!
            </div>
            <section id="browser-section">
                <article class="card p-4">
                    <h2 class="h5 fw-bold mb-4">Browse Available Equipment</h2>
                    <div class="d-flex gap-2 mb-4" style="max-width: 520px;">
                        <!-- Search -->
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="equipmentSearch" class="form-control border-start-0"
                                placeholder="Search by name..." onkeyup="filterEquipment()">
                        </div>

                        <!-- Category Dropdown -->
                        <select id="categoryFilter" class="form-select shadow-sm" onchange="filterEquipment()">
                            <option value="">All Categories</option>
                            <?php
                            while ($cat = mysqli_fetch_assoc($category_result)) {

                                if (strtolower($cat['category']) === 'others')
                                    continue;
                                ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php } ?>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="row g-4" id="equipmentList">
                        <?php if (mysqli_num_rows($inventory_result) == 0) { ?>
                            <div class="col-12 text-center text-muted py-5">
                                No equipment available at the moment.
                            </div>
                        <?php } else { ?>
                            <?php while ($item = mysqli_fetch_assoc($inventory_result)) { ?>
                                <div class="col-md-4 col-lg-3 item-node"
                                    data-name="<?php echo strtolower($item['item_name']); ?>"
                                    data-category="<?php echo strtolower($item['category']); ?>">

                                    <figure class="equipment-card card">
                                        <img src="/Equipment-Lending-Website/<?php echo $item['image_path']; ?>"
                                            style="width:100%; height:140px; object-fit:cover; border-radius:10px;"
                                            alt="<?php echo htmlspecialchars($item['item_name']); ?>">

                                        <figcaption class="mt-3">
                                            <h3 class="h6 fw-bold">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                            </h3>

                                            <p class="small mt-1 mb-2">
                                                Status:
                                                <?php if ($item['quantity'] > 0) { ?>
                                                    <span class="text-success fw-bold">Available</span>
                                                <?php } else { ?>
                                                    <span class="text-danger fw-bold">Unavailable</span>
                                                <?php } ?>
                                            </p>
                                            <p class="small mt-1 mb-2">
                                                Stock:
                                                <?php if ($item['quantity'] > 0) { ?>
                                                    <span class="badge bg-success">
                                                        <?php echo (int) $item['quantity']; ?> left
                                                    </span>
                                                <?php } else { ?>
                                                    <span class="badge bg-danger">Out of stock</span>
                                                <?php } ?>
                                            </p>

                                            <button class="btn btn-success w-100 mt-2" <?php if ($item['quantity'] <= 0)
                                                echo 'disabled'; ?>
                                                onclick="openForm('<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>')">
                                                Borrow
                                            </button>

                                        </figcaption>
                                    </figure>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </article>
            </section>

            <!-- Borrow Form Section -->
            <section id="form-section" class="hidden">
                <article class="card shadow mx-auto" style="max-width: 750px;">
                    <header class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0 fw-bold">Borrowing Form</h2>
                        <button class="btn-close" onclick="showSection('browser-section', 'btn-browse')"></button>
                    </header>
                    <div class="card-body p-4">
                        <form id="borrowForm" method="POST" action="">
                            <input type="hidden" name="equipment_name" id="selectedItem">

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Instructor</label>
                                    <input type="text" name="instructor" id="instructorField" class="form-control"
                                        oninput="validateLetters(this)" placeholder="e.g. Sir. Migs" required>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Room / Laboratory</label>
                                    <input type="text" name="room" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Borrow Date</label>
                                    <input type="date" name="borrow_date" id="borrow_date" class="form-control"
                                        required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Return Date</label>
                                    <input type="date" name="return_date" id="return_date" class="form-control"
                                        required>
                                </div>
                            </div>
                            <button type="submit" name="borrow_submit" class="btn btn-danger w-100 fw-bold py-2 mt-4">
                                Submit Request
                            </button>
                        </form>
                    </div>
                </article>
            </section>

            <!-- My Requests Section -->
            <section id="status-section" class="hidden">
                <article class="card shadow">
                    <header class="card-header bg-white py-3">
                        <h2 class="h5 mb-0 fw-bold">My Borrowing Status</h2>
                    </header>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Instructor</th>
                                    <th>Room</th>
                                    <th>Borrow Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Only run the loop if we actually have database results
                                if ($my_requests_result && mysqli_num_rows($my_requests_result) > 0):
                                    while ($r = mysqli_fetch_assoc($my_requests_result)):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['instructor']); ?></td>
                                            <td><?php echo htmlspecialchars($r['room']); ?></td>
                                            <td><?php echo htmlspecialchars($r['borrow_date']); ?></td>
                                            <td><?php echo htmlspecialchars($r['return_date']); ?></td>
                                            <td>
                                                <?php
                                                $status = $r['status'];
                                                $badge_class = ($status == 'Approved') ? 'success' : (($status == 'Declined') ? 'danger' : 'warning');
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No requests found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </main>
    </div>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set reference for current date
        const todayStr = new Date().toISOString().split('T')[0];

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function handleLogout() {
            if (confirm("Confirm Logout?")) {
                window.location.href = "logout.php";
            }
        }

        // Switcher Function to hide/show sections
        function showSection(sectionId, btnId) {
            document.querySelectorAll('main > section').forEach(s => s.classList.add('hidden'));
            document.getElementById(sectionId).classList.remove('hidden');
            document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            if (btnId) document.getElementById(btnId).classList.add('active');
        }

        // Real-time Search Filter for equipment
        function filterEquipment() {
            const searchText = document.getElementById('equipmentSearch').value.toLowerCase();
            const selectedCategory = document.getElementById('categoryFilter').value.toLowerCase();

            document.querySelectorAll('.item-node').forEach(item => {
                const name = item.getAttribute('data-name');
                const category = item.getAttribute('data-category');

                const matchesName = name.includes(searchText);
                const matchesCategory = selectedCategory === "" || category === selectedCategory;

                item.style.display = (matchesName && matchesCategory) ? "" : "none";
            });
        }

        function cleanURL() {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.pathname);
        }

        // Pre-fills equipment name and opens the form
        function openForm(itemName) {
            document.getElementById('selectedItem').value = itemName;
            showSection('form-section');
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            if (document.getElementById('success-alert')) {
                cleanURL();
            }
            const userSlug = "<?php echo $user_slug; ?>";

            if (!window.location.search.includes(userSlug)) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?u=' + userSlug;
                window.history.replaceState({ path: newUrl }, '', newUrl);
            }

            // --- INITIALIZE DATE RESTRICTIONS ---
            const borrowInput = document.querySelector('input[name="borrow_date"]');
            const returnInput = document.querySelector('input[name="return_date"]');

            if (borrowInput && returnInput) {
                // Disable past dates for both
                borrowInput.min = todayStr;
                returnInput.min = todayStr;

                // When borrow date changes, update the minimum return date
                borrowInput.addEventListener('change', function () {
                    returnInput.min = this.value;
                    if (returnInput.value && returnInput.value < this.value) {
                        returnInput.value = this.value; // Reset return date if it becomes invalid
                    }
                });
            }
        });

        // Function to allow ONLY letters, spaces, and dots
        function validateLetters(input) {
            input.value = input.value.replace(/[^a-zA-Z\s.']/g, '');
        }

        // --- FORM SUBMISSION WITH VALIDATION ---
        document.getElementById('borrowForm').addEventListener('submit', function (e) {
            const borrowDate = document.querySelector('input[name="borrow_date"]').value;
            const returnDate = document.querySelector('input[name="return_date"]').value;

            // 1. Final check: Don't allow submission if date is in the past
            if (borrowDate < todayStr) {
                e.preventDefault();
                alert("Error: The borrow date cannot be in the past.");
                return;
            }

            // 2. Final check: Return date vs Borrow date
            if (returnDate < borrowDate) {
                e.preventDefault();
                alert("Error: The return date cannot be earlier than the borrow date.");
                return;
            }

            e.preventDefault();
            const overlay = document.getElementById('loading-overlay');

            overlay.classList.add('active');
            overlay.classList.remove('hidden');

            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'borrow_submit';
            hiddenInput.value = '1';
            this.appendChild(hiddenInput);

            setTimeout(() => {
                this.submit();
            }, 2000);
        });
    </script>

    <!-- Overlay page / fake load screen -->
    <div id="loading-overlay" class="hidden"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; flex-direction: column; align-items: center; justify-content: center;">
        <div class="spinner-border text-danger" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="mt-3 fw-bold text-dark">Processing your request...</p>
    </div>
</body>
</html>