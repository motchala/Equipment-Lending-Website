<?php
// ================== DATABASE CONNECTION ==================
$conn = mysqli_connect("localhost", "root", "", "lending_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ================== FUNCTIONS ==================
function insertEquipment($conn, $equipmentName, $displayName, $description, $imageURL, $available) {
    $sql = "INSERT INTO tbl_lending (equipmentName, displayName, description, imageURL, available)
            VALUES ('$equipmentName', '$displayName', '$description', '$imageURL', $available)";
    return mysqli_query($conn, $sql);
}

function updateEquipment($conn, $id, $equipmentName, $displayName, $description, $imageURL, $available) {
    $sql = "UPDATE tbl_lending 
            SET equipmentName='$equipmentName',
                displayName='$displayName',
                description='$description',
                imageURL='$imageURL',
                available=$available
            WHERE id=$id";
    return mysqli_query($conn, $sql);
}

function deleteEquipment($conn, $id) {
    $sql = "DELETE FROM tbl_lending WHERE id = $id";
    return mysqli_query($conn, $sql);
}


function fetchEquipment($conn, $id = null) {
    if ($id !== null) {
        return mysqli_query($conn, "SELECT * FROM tbl_lending WHERE id=$id");
    }
    return mysqli_query($conn, "SELECT * FROM tbl_lending");
}

// ================== HANDLE POST ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // INSERT
    if (isset($_POST['insertSub'])) {
        $equipmentName = $_POST['equipmentName'];
        $displayName   = $_POST['displayName'];
        $description   = $_POST['description'];
        $imageURL      = $_POST['imageURL'];
        $available     = isset($_POST['available']) ? 1 : 0;

        if (insertEquipment($conn, $equipmentName, $displayName, $description, $imageURL, $available)) {
            echo "<script>alert('Equipment added successfully');</script>";
        } else {
            echo "<script>alert('Insert failed');</script>";
        }
    }

    // UPDATE
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id            = (int)$_POST['id'];
        $equipmentName = $_POST['equipmentName'];
        $displayName   = $_POST['displayName'];
        $description   = $_POST['description'];
        $imageURL      = $_POST['imageURL'];
        $available     = isset($_POST['available']) ? 1 : 0;

        if (updateEquipment($conn, $id, $equipmentName, $displayName, $description, $imageURL, $available)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo "<script>alert('Update failed');</script>";
        }
    }

    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];

        if (deleteEquipment($conn, $id)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo "<script>alert('Delete failed');</script>";
        }
    }

}

// ================== HANDLE EDIT ==================
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = fetchEquipment($conn, $id);
    if ($row = mysqli_fetch_assoc($res)) {
        $editData = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Item Dashboard - EquipLend</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.navbar { background-color: #800000 !important; }
.btn-primary { background-color: #800000; border-color: #800000; }
.btn-primary:hover { background-color: #600000; }
</style>
</head>

<body>

<nav class="navbar navbar-dark">
<div class="container">
    <span class="navbar-brand">Add Item Dashboard</span>
</div>
</nav>

<div class="container mt-5">
<div class="row">

<!-- ================== ADD FORM ================== -->
<div class="col-md-6">
<form method="POST">
<div class="card">
<div class="card-header"><h5>Add New Equipment</h5></div>
<div class="card-body">

<input class="form-control mb-2" name="equipmentName" placeholder="Equipment Name" required>
<input class="form-control mb-2" name="displayName" placeholder="Display Name" required>
<textarea class="form-control mb-2" name="description" placeholder="Description"></textarea>
<input class="form-control mb-2" name="imageURL" placeholder="Image URL">

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" name="available">
<label class="form-check-label">Available</label>
</div>

<button type="submit" name="insertSub" class="btn btn-primary">Add Equipment</button>

</div>
</div>
</form>
</div>

<!-- ================== EDIT FORM ================== -->
<div class="col-md-6">
<?php if ($editData): ?>
<div class="card">
<div class="card-header"><h5>Edit Equipment</h5></div>
<div class="card-body">

<form method="POST">
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= $editData['id'] ?>">

<input class="form-control mb-2" name="equipmentName" value="<?= $editData['equipmentName'] ?>" required>
<input class="form-control mb-2" name="displayName" value="<?= $editData['displayName'] ?>" required>
<textarea class="form-control mb-2" name="description"><?= $editData['description'] ?></textarea>
<input class="form-control mb-2" name="imageURL" value="<?= $editData['imageURL'] ?>">

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" name="available" <?= $editData['available'] ? 'checked' : '' ?>>
<label class="form-check-label">Available</label>
</div>

<button class="btn btn-primary">Update</button>
<a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Cancel</a>

</form>
</div>
</div>
<?php endif; ?>
</div>

</div>

<!-- ================== TABLE ================== -->
<h2 class="mt-5">Current Equipment</h2>

<table class="table table-striped">
<thead>
<tr>
<th>ID</th><th>Name</th><th>Display</th><th>Description</th><th>Available</th><th>Action</th>
</tr>
</thead>
<tbody>

<?php
$result = fetchEquipment($conn);
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['equipmentName']}</td>";
    echo "<td>{$row['displayName']}</td>";
    echo "<td>{$row['description']}</td>";
    echo "<td>" . ($row['available'] ? 'Yes' : 'No') . "</td>";
    echo "<td>
            <a href='?edit={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>

            <form method='POST' style='display:inline;'>
                <input type='hidden' name='action' value='delete'>
                <input type='hidden' name='id' value='{$row['id']}'>
                <button 
                    type='submit' 
                    class='btn btn-sm btn-danger'
                    onclick='return confirm(\"Are you sure you want to delete this item?\")'>
                    Delete
                </button>
            </form>
          </td>";
    echo "</tr>";
}

?>

</tbody>
</table>

</div>
</body>
</html>

<?php mysqli_close($conn); ?>
