<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login/Register - EquipLend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .navbar { background-color: #800000 !important; }
        .btn-primary { background-color: #800000; border-color: #800000; }
        .btn-primary:hover { background-color: #600000; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">EquipLend</a>
        </div>
    </nav>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h1 class="text-center">Login or Register</h1>
                <p class="text-center">Please use your student email (e.g., name@student.edu). Admins use @admin.edu.</p>
                <ul class="nav nav-tabs" id="authTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Register</button>
                    </li>
                </ul>
                <div class="tab-content" id="authTabContent">

                    <div class="tab-pane fade show active" id="login" role="tabpanel">

                        <form name="tbl_accounts" method="POST" class="mt-3">
                            <div class="mb-3">
                                <input type="email" class="form-control" id="login-email" placeholder="Email"
                                 name="email"
                                >
                            </div>
                            <div class="mb-3">
                                <input type="password" class="form-control" id="login-password" placeholder="Password"
                                 name="password"
                                >
                            </div>
                            <button type="submit" class="btn btn-primary w-100" name="login">Login</button>
                        </form>

                    </div>

                    <div class="tab-pane fade" id="register" role="tabpanel">
                        <form class="mt-3">
                            <div class="mb-3">
                                <input type="text" class="form-control" id="reg-name" placeholder="Full Name">
                            </div>
                            <div class="mb-3">
                                <input type="email" class="form-control" id="reg-email" placeholder="Email">
                            </div>
                            <div class="mb-3">
                                <input type="password" class="form-control" id="reg-password" placeholder="Password">
                            </div>
                            <button type="button" class="btn btn-primary w-100" onclick="register()">Register</button>
                        </form>
                    </div>
                </div>
                <p id="auth-message" class="text-danger text-center mt-3"></p>
            </div>
        </div>
    </div>
    <footer class="bg-dark text-white text-center py-3 fixed-bottom">
        <p>&copy; 2026 EquipLend. <a href="index.html" class="text-white">Back to Home</a></p>
    </footer>

<?php


$conn = mysqli_connect("localhost", "root", "", "lending_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if(isset($_POST['login'])) {

    if($_POST['email'] == 'main@admin.edu' && $_POST['password'] == 'admin123' ) {
        header("Location: admin-dashboard.php");
        exit();
    } else {
        $login_error = "Invalid email or password.";
    }
}

?>

</body>
</html>