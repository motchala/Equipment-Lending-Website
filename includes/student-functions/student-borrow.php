<?php
session_start();
if (!isset($_SESSION['student_auth']) || $_SESSION['student_auth']['action'] !== 'borrow') {
    header("Location: ../../student-portal.php");
    exit();
}
$auth = $_SESSION['student_auth'];

$conn = mysqli_connect("localhost", "root", "", "lending_db");
$inventory = mysqli_query($conn, "SELECT * FROM tbl_inventory WHERE is_archived = 0 AND quantity > 0 ORDER BY item_name ASC");
$categories = mysqli_query($conn, "SELECT DISTINCT category FROM tbl_inventory WHERE is_archived = 0 ORDER BY category ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Borrow Equipment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../CSS/student-portal.css">
</head>

<body class="p-5">
    <!-- Top Bar — Minimal, no user account -->
    <header class="student-topbar">
        <div class="student-brand">
            <div class="student-brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2" />
                    <polyline points="2 17 12 22 22 17" />
                    <polyline points="2 12 12 17 22 12" />
                </svg>
            </div>
            <div>
                <div class="student-brand-title"><strong>PUP</strong>SYNC</div>
                <div class="student-brand-sub">Student Services</div>
            </div>
        </div>
        <a href="/Equipment-Lending-Website/landing-page.php" class="student-back-link">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Portal
        </a>
    </header>

    <main style="max-width:1200px; margin:0 auto; padding:32px;">
        <h2>Borrow Equipment</h2>
        <p>Authorized by faculty: <strong>
                <?php echo htmlspecialchars($auth['faculty_id']); ?>
            </strong></p>

        <!-- Equipment Grid -->
        <div class="eq-grid"
            style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:20px; margin-top:24px;">
            <?php while($item = mysqli_fetch_assoc($inventory)): ?>
            <div class="eq-item-card"
                style="border:1px solid var(--color-outline-variant); border-radius:16px; overflow:hidden; background:var(--color-surface);">
                <?php if(!empty($item['image_path'])): ?>
                <img src="/Equipment-Lending-Website/<?php echo htmlspecialchars($item['image_path']); ?>"
                    style="width:100%; height:160px; object-fit:cover;">
                <?php else: ?>
                <div
                    style="height:160px; display:flex; align-items:center; justify-content:center; background:var(--color-surface-high);">
                    <span class="material-symbols-outlined"
                        style="font-size:48px; color:var(--color-primary); opacity:0.3;">inventory_2</span>
                </div>
                <?php endif; ?>
                <div style="padding:16px;">
                    <div style="font-weight:700; font-size:1rem; margin-bottom:4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--color-secondary); margin-bottom:12px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                    <span
                        style="display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:8px; background:var(--color-success-container); color:var(--color-success); font-size:0.75rem; font-weight:700;">
                        <span class="material-symbols-outlined" style="font-size:12px;">check_circle</span>
                        <?php echo (int)$item['quantity']; ?> available
                    </span>
                    <button onclick="selectItem('<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>')"
                        style="width:100%; margin-top:12px; padding:10px; background:var(--color-primary-container); color:var(--color-on-primary); border:none; border-radius:12px; font-weight:700; cursor:pointer;">
                        Select
                    </button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        </main>

        <!-- Borrow Form Modal -->
        <div id="borrowFormModal"
            style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
            <div
                style="background:var(--color-surface); border-radius:20px; padding:32px; max-width:500px; width:90%; box-shadow:var(--shadow-lg);">
                <h3 style="margin-bottom:20px;">Borrow Request</h3>
                <p style="color:var(--color-secondary); margin-bottom:20px;">Item: <strong
                        id="selectedItemName"></strong></p>

                <form method="POST" action="student-borrow-submit.php">
                    <input type="hidden" name="equipment_name" id="formEquipment">

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.875rem; margin-bottom:6px;">Room /
                            Laboratory</label>
                        <input type="text" name="room" required placeholder="e.g. Lab 301"
                            style="width:100%; padding:10px 12px; border:1px solid var(--color-outline-variant); border-radius:12px; background:var(--color-surface-container);">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:0.875rem; margin-bottom:6px;">Borrow
                                Date</label>
                            <input type="date" name="borrow_date" id="borrowDate" required
                                style="width:100%; padding:10px 12px; border:1px solid var(--color-outline-variant); border-radius:12px; background:var(--color-surface-container);">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:0.875rem; margin-bottom:6px;">Return
                                Date</label>
                            <input type="date" name="return_date" id="returnDate" required
                                style="width:100%; padding:10px 12px; border:1px solid var(--color-outline-variant); border-radius:12px; background:var(--color-surface-container);">
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-top:24px;">
                        <button type="button" onclick="closeModal()"
                            style="flex:1; padding:12px; border:1px solid var(--color-outline-variant); background:transparent; border-radius:12px; color:var(--color-secondary); font-weight:600;">Cancel</button>
                        <button type="submit"
                            style="flex:1; padding:12px; background:linear-gradient(135deg, #800000, #5a0000); color:#fff; border:none; border-radius:12px; font-weight:700;">Submit
                            Request</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function selectItem(name) {
                document.getElementById('selectedItemName').textContent = name;
                document.getElementById('formEquipment').value = name;
                document.getElementById('borrowFormModal').style.display = 'flex';
            }
            function closeModal() {
                document.getElementById('borrowFormModal').style.display = 'none';
            }
            // Set min dates
            document.getElementById('borrowDate').min = new Date().toISOString().split('T')[0];
            document.getElementById('borrowDate').addEventListener('change', function () {
                document.getElementById('returnDate').min = this.value;
            });
        </script>
</body>

</html>