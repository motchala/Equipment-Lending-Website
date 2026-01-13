<?php
// Admindash.php
$conn = mysqli_connect("localhost", "root", "", "lending_db");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle Approve / Decline actions
// Handle Approve / Decline actions
if (isset($_GET['action'], $_GET['id'])) {
    $request_id = (int) $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'approve') {
        $status = 'Approved';
    } elseif ($action === 'decline') {
        $status = 'Declined';
    } else {
        exit();
    }

    $stmt = $conn->prepare("UPDATE tbl_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $status, $request_id);
    $stmt->execute();

    header("Location: admin-dashboard.php#sec-waiting");
    exit();
}



// Add item
if (isset($_POST['add_item'])) {

    $name = $_POST['item_name'];
    $category = $_POST['category'];
    $qty = $_POST['quantity'];

    // Image upload
    $image_path = "uploads/default.png";

    if (!empty($_FILES['item_image']['name'])) {

        //  ALLOWED IMAGE TYPES
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            die("Only JPG, PNG, and WEBP images are allowed.");
        }

        //  MAX FILE SIZE (2MB)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($_FILES['item_image']['size'] > $max_size) {
            die("Image too large. Maximum size is 2MB.");
        }


        $image_name = time() . "_" . $_FILES['item_image']['name'];
        $target = "uploads/" . $image_name;

        move_uploaded_file($_FILES['item_image']['tmp_name'], $target);
        $image_path = $target;
    }

    $sql = "INSERT INTO tbl_inventory (item_name, category, quantity, image_path)
            VALUES ('$name', '$category', $qty, '$image_path')";

    mysqli_query($conn, $sql);
    header("Location: admin-dashboard.php#sec-inventory");
    exit();
}

//Edit Item
if (isset($_POST['update_item'])) {

    $item_id = intval($_POST['item_id']);
    $name = $_POST['item_name'];
    $category = $_POST['category'];
    $qty = intval($_POST['quantity']);

    // Keep old image by default
    $image_path = $_POST['old_image'];

    // Upload new image if provided
    if (!empty($_FILES['item_image']['name'])) {

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $file_type = $_FILES['item_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            die("Only JPG, PNG, and WEBP images are allowed.");
        }

        $max_size = 2 * 1024 * 1024;
        if ($_FILES['item_image']['size'] > $max_size) {
            die("Image too large. Maximum size is 2MB.");
        }

        $image_name = time() . "_" . $_FILES['item_image']['name'];
        $target = "uploads/" . $image_name;

        move_uploaded_file($_FILES['item_image']['tmp_name'], $target);
        $image_path = $target;
    }

    $sql = "UPDATE tbl_inventory 
            SET item_name='$name',
                category='$category',
                quantity=$qty,
                image_path='$image_path'
            WHERE item_id=$item_id";

    mysqli_query($conn, $sql);

    header("Location: admin-dashboard.php#sec-inventory");
    exit();
}
// Delete Item
if (isset($_GET['delete_item'])) {
    $id = intval($_GET['delete_item']);

    $res = mysqli_query($conn, "SELECT image_path FROM tbl_inventory WHERE item_id=$id");
    $row = mysqli_fetch_assoc($res);

    if ($row && $row['image_path'] !== 'uploads/default.png') {
        unlink($row['image_path']);
    }

    mysqli_query($conn, "DELETE FROM tbl_inventory WHERE item_id=$id");
    header("Location: admin-dashboard.php#sec-inventory");
    exit();
}

// Fetch all requests
$waiting_sql = "SELECT * FROM tbl_requests WHERE status='Waiting'";
$approved_sql = "SELECT * FROM tbl_requests WHERE status='Approved'";
$declined_sql = "SELECT * FROM tbl_requests WHERE status='Declined'";

$waiting_result = mysqli_query($conn, $waiting_sql);
$approved_result = mysqli_query($conn, $approved_sql);
$declined_result = mysqli_query($conn, $declined_sql);

$inventory_result = mysqli_query($conn, "SELECT * FROM tbl_inventory ORDER BY created_at DESC");

$edit_item = null;

if (isset($_GET['edit_item'])) {
    $edit_id = intval($_GET['edit_item']);
    $edit_query = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE item_id=$edit_id");
    $edit_item = mysqli_fetch_assoc($edit_query);
}

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
        :root {
            --maroon: #800000;
            --glass: rgba(255, 255, 255, 0.95);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                url('7-hero-page.jpg');
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        /* NAVBAR - Enhanced Shadow for depth */
        .navbar {
            background-color: var(--maroon) !important;
            z-index: 2100;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* SIDEBAR - Improved Width and Transitions */
        #sidebar {
            position: fixed;
            top: 56px;
            left: -280px;
            width: 280px;
            height: calc(100% - 56px);
            background: rgba(128, 0, 0, 1);
            transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-bottom: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-footer .sidebar-btn {
            border-top: none;
            border-bottom: none;
            width: 100%;
            color: #f5f5dc;
        }

        .sidebar-open #sidebar {
            left: 0;
        }

        /* SIDEBAR NAVIGATION - Visual Hierarchy */
        #sidebar .nav-label {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 25px 25px 10px;
            font-weight: 700;
            display: block;
        }

        .sidebar-btn {
            background: none;
            border: none;
            border-left: 4px solid transparent;
            color: rgba(255, 255, 255, 0.75);
            padding: 14px 25px;
            display: flex;
            align-items: center;
            width: 100%;
            text-align: left;
            transition: 0.2s ease;
            cursor: pointer;
        }

        .sidebar-btn i {
            font-size: 1.25rem;
            width: 35px;
            display: inline-block;
        }

        .sidebar-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-btn.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            border-left: 4px solid #fff;
            font-weight: 600;
        }

        /* Dimmer Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1500;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

		#close-sidebar:hover {
            opacity: 1;
        }
		
        /* MAIN CONTENT AREA */
        .main-container {
            background: var(--glass);
            border-radius: 18px;
            padding: 30px;
            margin-top: 85px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            position: relative;
        }

        .view-section {
            display: none;
        }

        .view-section.active {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .item-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="overlay" id="ui-overlay"></div>
    <nav class="navbar navbar-dark fixed-top">
        <div class="container-fluid justify-content-start"> <button class="btn btn-outline-light border-0 shadow-none"
                onclick="toggleSidebar()">
                <i class="bi bi-list fs-2"></i>
            </button>
            <span class="navbar-brand fw-bold ms-2">
                EQUIPLEND <span class="fw-light opacity-75">ADMIN</span>
            </span>
        </div>
    </nav>

    <div id="sidebar">
        <div class="nav-label">Management</div>

        <button type="button" onclick="showSection('waiting')" id="link-waiting" class="sidebar-btn active">
            <i class="bi bi-people"></i> Waiting List
        </button>

        <button type="button" onclick="showSection('inventory')" id="link-inventory" class="sidebar-btn">
            <i class="bi bi-box-seam"></i> Inventory
        </button>

        <div class="nav-label">Approvals</div>

        <button type="button" onclick="showSection('approved')" id="link-approved" class="sidebar-btn">
            <i class="bi bi-patch-check"></i> Approved
        </button>

        <button type="button" onclick="showSection('declined')" id="link-declined" class="sidebar-btn">
            <i class="bi bi-x-octagon"></i> Declined
        </button>

        <div class="sidebar-footer">
            <button type="button" class="sidebar-btn border-0" onclick="handleLogout()">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>
    </div>

    <div class="container">
        <div class="main-container">

            <!-- WAITING LIST SECTION -->
            <div id="sec-waiting" class="view-section active">
                <h4 class="fw-bold mb-4 text-maroon"><i class="bi bi-people me-2"></i>Student Waiting List</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Requested Item</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody id="waiting-body">
                            <?php while ($row = mysqli_fetch_assoc($waiting_result)) { ?>
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
                                        <a href="admin-dashboard.php?action=approve&id=<?php echo $row['request_id']; ?>"
                                            class="btn btn-success btn-sm rounded-circle p-2">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                        <a href="admin-dashboard.php?action=decline&id=<?php echo $row['request_id']; ?>"
                                            class="btn btn-danger btn-sm rounded-circle p-2 ms-1">
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
                    <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal"
                        data-bs-target="#itemModal" onclick="prepareAdd()">
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($inventory_result) == 0) { ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        Inventory is empty.
                                    </td>
                                </tr>
                            <?php } else { ?>
                                <?php while ($item = mysqli_fetch_assoc($inventory_result)) { ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $item['image_path']; ?>" class="item-img shadow-sm">
                                        </td>

                                        <td class="fw-bold text-maroon">
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                        </td>

                                        <td>
                                            <?php echo htmlspecialchars($item['category']); ?>
                                        </td>

                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo $item['quantity']; ?> units
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($item['quantity'] > 0) { ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php } else { ?>
                                                <span class="badge bg-danger">No Stock</span>
                                            <?php } ?>
                                        </td>

                                        <td>
                                            <!-- EDIT (future improvement) -->
                                            <a href="admin-dashboard.php?edit_item=<?php echo $item['item_id']; ?>#sec-inventory"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>


                                            <!-- DELETE -->
                                            <a href="admin-dashboard.php?delete_item=<?php echo $item['item_id']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this item?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- APPROVED SECTION -->
            <div id="sec-approved" class="view-section">
                <h4 class="fw-bold mb-4 text-success"><i class="bi bi-patch-check me-2"></i>Approved Requests</h4>
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
                        <tbody id="approved-list">
                            <?php while ($row = mysqli_fetch_assoc($approved_result)) { ?>
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
                            <?php while ($row = mysqli_fetch_assoc($declined_result)) { ?>
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
                    <h5 class="modal-title fw-bold">
                        <?php echo $edit_item ? "Edit Equipment" : "Add Equipment"; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <form method="POST" enctype="multipart/form-data">

                        <?php if ($edit_item) { ?>
                            <input type="hidden" name="item_id" value="<?php echo $edit_item['item_id']; ?>">
                            <input type="hidden" name="old_image" value="<?php echo $edit_item['image_path']; ?>">
                        <?php } ?>

                        <div class="text-center mb-3">
                            <img src="<?php echo $edit_item ? $edit_item['image_path'] : 'uploads/default.png'; ?>"
                                class="item-img mb-2">
                            <input type="file" name="item_image" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Item Name</label>
                            <input type="text" name="item_name" class="form-control"
                                value="<?php echo $edit_item['item_name'] ?? ''; ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Quantity</label>
                                <input type="number" name="quantity" class="form-control" min="0"
                                    value="<?php echo $edit_item['quantity'] ?? 1; ?>" required>
                            </div>

                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Category</label>
                                <select name="category" class="form-select">
                                    <?php
                                    $categories = ["Photography", "Laptops", "Projectors"];
                                    foreach ($categories as $cat) {
                                        $selected = ($edit_item && $edit_item['category'] == $cat) ? "selected" : "";
                                        echo "<option $selected>$cat</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="<?php echo $edit_item ? 'update_item' : 'add_item'; ?>"
                            class="btn btn-success w-100 py-3 rounded-3 fw-bold">
                            Save Changes
                        </button>

                    </form>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
		let inventory = [];
        let currentImageData = "https://via.placeholder.com/150?text=No+Photo";
		
        // Sidebar Slide Toggle
        function toggleSidebar() {
            const overlay = document.getElementById('ui-overlay');
            document.body.classList.toggle('sidebar-open');
            overlay.classList.toggle('active');
        }

        // Close sidebar/blur when clicking the blurry area
        document.getElementById('ui-overlay').addEventListener('click', () => {
            document.body.classList.remove('sidebar-open');
            document.getElementById('ui-overlay').classList.remove('active');
        });

        // Navigation Logic
        function showSection(sectionId) {
            // Hide All sections
            document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));

            // Show Active section
            const target = document.getElementById('sec-' + sectionId);
            if (target) target.classList.add('active');

            // UI Highlighting for Sidebar Buttons
            document.querySelectorAll('.sidebar-btn').forEach(el => el.classList.remove('active'));
            const activeBtn = document.getElementById('link-' + sectionId);
            if (activeBtn) activeBtn.classList.add('active');

            // Auto-close sidebar on mobile after clicking
            if (window.innerWidth < 992) {
                document.body.classList.remove('sidebar-open');
                document.getElementById('ui-overlay').classList.remove('active');
            }
        }

        function handleLogout() {
            if (confirm("Confirm Logout?")) {
                window.location.href = "landing-page.php";
            }
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
            if (index === "") inventory.push(newItem);
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

        function deleteItem(index) { if (confirm("Permanently delete?")) { inventory.splice(index, 1); renderInventory(); } }

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

    <?php if ($edit_item) { ?>
        <script>
            window.onload = function () {
                new bootstrap.Modal(document.getElementById('itemModal')).show();
            };
        </script>
    <?php } ?>

    <!-- this prevents going back to waiting list section after reload -->
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            const hash = window.location.hash.replace('#sec-', '');
            if (hash) {
                showSection(hash);
            }
        });
    </script>

</body>

</html>