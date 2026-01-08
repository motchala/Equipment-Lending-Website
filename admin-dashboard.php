<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - EquipLend</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    margin: 0;
    background-color: #f8f9fa;
    overflow-x: hidden; /* prevent horizontal scroll when sidebar is open */
}

/* TOP NAVBAR */
.navbar {
    background-color: #800000 !important;
}

/* SIDEBAR (OVERLAY) */
#sidebar {
    position: fixed;
    top: 56px;
    left: -220px; /* hidden initially */
    width: 220px;
    height: 100%;
    background-color: #800000;
    transition: 0.3s;
    z-index: 1000;
}

#sidebar a {
    color: white;
    display: block;
    padding: 12px 20px;
    text-decoration: none;
}

#sidebar a:hover {
    background-color: #600000;
}

/* Show the sidebar when active */
.sidebar-open #sidebar {
    left: 0;
}

/* MAIN CONTENT PANEL */
#content {
    margin-top: 56px;
    padding: 20px;
    /* Does NOT move or resize at all */
}
</style>
</head>

<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid">
        <button class="btn btn-outline-light" onclick="toggleSidebar()">☰</button>
        <span class="navbar-brand ms-2">Admin Dashboard</span>
    </div>
</nav>

<!-- SIDEBAR -->
<div id="sidebar">
    <a href="admin.php" onclick="showSection('Items')">📋 Items</a>
    <a href="#" onclick="showSection('waiting')">📋 Waiting List</a>
    <a href="#" onclick="showSection('approve')">✅ Approved</a>
    <a href="#" onclick="showSection('decline')">❌ Declined</a>
</div>

<!-- MAIN CONTENT -->
<div id="content" class="container-fluid">


        <!-- ITEMS -->
    <div id="approve" class="section d-none">
        <h4>Items</h4>
        <p>No items yet.</p>
    </div>


    <!-- WAITING -->
    <div id="waiting" class="section">
        <h4>Student Waiting List</h4>
        <div class="table-responsive mt-3">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>2023-001</td>
                    <td>Juan Dela Cruz</td>
                    <td>BSIT</td>
                    <td>3rd</td>
                    <td><span class="badge bg-warning">Waiting</span></td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>

    <!-- APPROVED -->
    <div id="approve" class="section d-none">
        <h4>Approved Students</h4>
        <p>No approved students yet.</p>
    </div>

    <!-- DECLINED -->
    <div id="decline" class="section d-none">
        <h4>Declined Students</h4>
        <p>No declined students yet.</p>
    </div>

</div>

<script>
/* Toggle sidebar overlay */
function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
}

/* Show only the clicked section */
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(sec => {
        sec.classList.add('d-none');
    });
    document.getElementById(sectionId).classList.remove('d-none');
}
</script>

</body>
</html>
