<?php
// Admindash.php
$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle Approve / Decline actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']); // sanitize input
    $action = $_GET['action'];

    if ($action === "approve") {
        $status = "Approved";
    } elseif ($action === "decline") {
        $status = "Declined";
    } else {
        $status = "Waiting";
    }

    $update_sql = "UPDATE tbl_requests SET status='$status' WHERE request_id=$request_id";
    mysqli_query($conn, $update_sql);

    // redirect to avoid resubmission
    header("Location: Admindash.php");
    exit();
}

// Fetch all requests
$waiting_sql = "SELECT * FROM tbl_requests WHERE status='Waiting'";
$approved_sql = "SELECT * FROM tbl_requests WHERE status='Approved'";
$declined_sql = "SELECT * FROM tbl_requests WHERE status='Declined'";

$waiting_result = mysqli_query($conn, $waiting_sql);
$approved_result = mysqli_query($conn, $approved_sql);
$declined_result = mysqli_query($conn, $declined_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EquipLend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root { --maroon: #800000; --glass: rgba(255, 255, 255, 0.95); }
        
        body {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('7-hero-page.jpg'); 
            background-size: cover; background-attachment: fixed; min-height: 100vh; font-family: 'Inter', sans-serif;
        }

        /* NAVBAR - Enhanced Shadow for depth */
        .navbar { background-color: var(--maroon) !important; z-index: 1060; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        /* SIDEBAR - Improved Width and Transitions */
        #sidebar {
            position: fixed; top: 56px; left: -280px; width: 280px; height: calc(100% - 56px);
            background: rgba(45, 0, 0, 0.98); backdrop-filter: blur(15px); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1050; border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-open #sidebar { left: 0; }
        
        /* SIDEBAR NAVIGATION - Visual Hierarchy */
        #sidebar .nav-label {
            color: rgba(255,255,255,0.4); font-size: 0.7rem; text-transform: uppercase;
            letter-spacing: 1.5px; padding: 25px 25px 10px; font-weight: 700;
        }

        #sidebar a { 
            color: rgba(255, 255, 255, 0.75); padding: 14px 25px; display: flex; 
            align-items: center; text-decoration: none; transition: 0.2s ease;
            border-left: 4px solid transparent;
        }

        /* Active/Hover states to clearly show the user's location */
        #sidebar a:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }
        
        #sidebar a.active { 
            color: #fff; background: rgba(255, 255, 255, 0.12); 
            border-left: 4px solid #fff; font-weight: 600;
        }

        #sidebar a i { font-size: 1.25rem; width: 35px; }

        /* MAIN CONTENT AREA */
        .main-container { background: var(--glass); border-radius: 18px; padding: 30px; margin-top: 85px; box-shadow: 0 15px 35px rgba(0,0,0,0.4); }
        
        .view-section { display: none; }
        .view-section.active { display: block; animation: slideIn 0.3s ease-out; }

        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .item-img { width: 55px; height: 55px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; }
        #imagePreview { width: 100%; height: 160px; object-fit: contain; border: 2px dashed #bbb; border-radius: 12px; margin-bottom: 12px; display: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid">
        <button class="btn btn-outline-light border-0 shadow-none" onclick="toggleSidebar()"><i class="bi bi-list fs-2"></i></button>
        <span class="navbar-brand fw-bold ms-2">EQUIPLEND <span class="fw-light opacity-75">ADMIN</span></span>
        <div class="ms-auto">
            <button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="handleLogout()">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>
    </div>
</nav>

<div id="sidebar">
    <div class="nav-label">Management</div>
    <a onclick="showSection('waiting')" id="link-waiting" class="active">
        <i class="bi bi-people"></i> Waiting List
    </a>
    <a onclick="showSection('inventory')" id="link-inventory">
        <i class="bi bi-box-seam"></i> Inventory
    </a>

    <div class="nav-label">Approvals</div>
    <a onclick="showSection('approved')" id="link-approved">
        <i class="bi bi-patch-check"></i> Approved
    </a>
    <a onclick="showSection('declined')" id="link-declined">
        <i class="bi bi-x-octagon"></i> Declined
    </a>
    
   
</div>

<div class="container">
    <div class="main-container">

    <!-- WAITING LIST SECTION -->
        <div id="sec-waiting" class="view-section active">
            <h4 class="fw-bold mb-4 text-maroon"><i class="bi bi-people me-2"></i>Student Waiting List</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr><th>ID</th><th>Name</th><th>Requested Item</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    
                    <tbody id="waiting-body">
                        <?php while($row = mysqli_fetch_assoc($waiting_result)) { ?>
                    <tr>
                        <td>
                            <?php echo $row['student_id']; ?>
                        </td>
                        <td class="fw-bold">
                            <?php echo $row['student_name']; ?>
                        </td>
                        <td>
                            <?php echo $row['equipment_name']; ?>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark px-3 py-2">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td>
                            <a href="Admindash.php?action=approve&id=<?php echo $row['request_id']; ?>" class="btn btn-success btn-sm rounded-circle p-2">
                                <i class="bi bi-check-lg"></i>
                            </a>
                            <a href="Admindash.php?action=decline&id=<?php echo $row['request_id']; ?>" class="btn btn-danger btn-sm rounded-circle p-2 ms-1">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </td>
                    </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>

        <div id="sec-inventory" class="view-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold m-0 text-maroon"><i class="bi bi-box-seam me-2"></i>Equipment Inventory</h4>
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="prepareAdd()">
                    <i class="bi bi-plus-lg me-1"></i> Add Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                        <th>Photo</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Status</th>
                        
                        <th>Actions</th></tr>
                    </thead>
                    <tbody id="inventory-list"></tbody>
                </table>
            </div>
        </div>
        <!-- APPROVED SECTION -->
        <div id="sec-approved" class="view-section">
            <h4 class="fw-bold mb-4 text-success"><i class="bi bi-patch-check me-2"></i>Approved Requests</h4>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark"><tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Item</th>
                        <th>Status</th>
                    </tr>
                </thead>
                        <tbody id="approved-list">
                            <?php while($row = mysqli_fetch_assoc($approved_result)) { ?>
                            <tr>
                                <td><?php echo $row['student_id']; ?></td>
                                <td class="fw-bold"><?php echo $row['student_name']; ?></td>
                                <td><?php echo $row['equipment_name']; ?></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>

                </table>
            </div>
        </div>

        <!-- DECLINED SECTION -->
        <div id="sec-declined" class="view-section">
            <h4 class="fw-bold mb-4 text-danger"><i class="bi bi-x-octagon me-2"></i>Declined Requests</h4>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Item</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="declined-list">
                        <?php while($row = mysqli_fetch_assoc($declined_result)) { ?>
                        <tr>
                            <td>
                                <?php echo $row['student_id']; ?>
                            </td>
                            <td class="fw-bold">
                                <?php echo $row['student_name']; ?>
                            </td>
                            <td>
                                <?php echo $row['equipment_name']; ?>
                            </td>
                            <td>
                                <span class="badge bg-danger"><?php echo $row['status']; ?>
                            </span>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>

                </table>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header text-white" style="background: var(--maroon);">
                <h5 class="modal-title fw-bold" id="modalTitle">Equipment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="itemForm">
                    <input type="hidden" id="editIndex">
                    <div class="text-center mb-4">
                        <img id="imagePreview" src="" alt="Preview">
                        <label class="form-label d-block small text-muted">Upload Item Image</label>
                        <input type="file" id="itemFile" class="form-control form-control-sm mt-1" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Item Name</label>
                        <input type="text" id="itemName" class="form-control rounded-3" placeholder="Enter name..." required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Quantity</label>
                            <input type="number" id="itemQty" class="form-control rounded-3" min="0" value="1">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select id="itemCategory" class="form-select rounded-3">
                                <option>Photography</option>
                                <option>Laptops</option>
                                <option>Projectors</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-success w-100 py-3 rounded-3 fw-bold shadow-sm" onclick="saveItem()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let inventory = [];
    let currentImageData = "https://via.placeholder.com/150?text=No+Photo";

    // Sidebar Slide Toggle
    function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }

    // Logic for Navigation and UI Highlighting
    function showSection(sectionId) {
        // Hide All
        document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
        // Show Active
        document.getElementById('sec-' + sectionId).classList.add('active');
        // Clear Links
        document.querySelectorAll('#sidebar a').forEach(a => a.classList.remove('active'));
        // Highlight Active Link
        document.getElementById('link-' + sectionId).classList.add('active');

        // Auto-close Sidebar on Mobile devices to clear the screen
        if (window.innerWidth < 992) {
            document.body.classList.remove('sidebar-open');
        }
    }

    function handleLogout() {
        if(confirm("Confirm Logout?")) { window.location.href = "login.php"; }
    }

    // CRUD/Utility Functions
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'block';
                currentImageData = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function renderInventory() {
        const list = document.getElementById('inventory-list');
        list.innerHTML = inventory.length === 0 ? '<tr><td colspan="6" class="text-center text-muted py-5">Inventory is empty.</td></tr>' : '';
        inventory.forEach((item, index) => {
            list.innerHTML += `
                <tr>
                    <td><img src="${item.image}" class="item-img shadow-sm"></td>
                    <td class="fw-bold text-maroon">${item.name}</td>
                    <td>${item.category}</td>
                    <td><span class="badge bg-info text-dark">${item.qty} units</span></td>
                    <td><span class="badge ${item.qty > 0 ? 'bg-success' : 'bg-danger'}">${item.qty > 0 ? 'Available' : 'No Stock'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editItem(${index})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(${index})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
        });
    }

    function saveItem() {
        const index = document.getElementById('editIndex').value;
        const newItem = {
            name: document.getElementById('itemName').value,
            category: document.getElementById('itemCategory').value,
            qty: document.getElementById('itemQty').value,
            image: currentImageData
        };
        if(index === "") inventory.push(newItem);
        else inventory[index] = newItem;
        renderInventory();
        bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
    }

    function prepareAdd() {
        document.getElementById('itemForm').reset();
        document.getElementById('editIndex').value = "";
        document.getElementById('imagePreview').style.display = 'none';
        currentImageData = "https://via.placeholder.com/150?text=No+Photo";
    }

    function editItem(index) {
        const item = inventory[index];
        document.getElementById('editIndex').value = index;
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemCategory').value = item.category;
        document.getElementById('itemQty').value = item.qty;
        document.getElementById('imagePreview').src = item.image;
        document.getElementById('imagePreview').style.display = 'block';
        currentImageData = item.image;
        new bootstrap.Modal(document.getElementById('itemModal')).show();
    }

    function deleteItem(index) { if(confirm("Permanently delete?")) { inventory.splice(index, 1); renderInventory(); } }

    function processRequest(id, action) {
        const row = document.getElementById('req-' + id);
        const name = row.cells[1].innerText;
        const item = row.cells[2].innerText;
        const targetList = action === 'approve' ? 'approved-list' : 'declined-list';
        const badge = action === 'approve' ? 'bg-success' : 'bg-danger';

        document.getElementById(targetList).innerHTML += `
            <tr><td>${id}</td><td class="fw-bold">${name}</td><td>${item}</td><td><span class="badge ${badge}">${action.toUpperCase()}</span></td></tr>`;
        row.remove(); 
    }

    renderInventory();
</script>

</body>
</html>