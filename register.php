<?php
session_start();
require_once 'dbconfig.php';

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'superadmin') {
        header("Location: superadmin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'cashier') {
        header("Location: pos.php");
        exit();
    }
}

$alert = [
    "title" => "",
    "text" => "",
    "icon" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (!empty($username) && !empty($password)) {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $alert = [
                "title" => "Username Taken",
                "text" => "That username is already registered. Please choose another.",
                "icon" => "error"
            ];
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, 'active')");
            $insert->execute([$username, $hashed_password, $role]);

            $alert = [
                "title" => "Registration Successful!",
                "text" => "Account created successfully. Redirecting to login...",
                "icon" => "success"
            ];
        }
    } else {
        $alert = [
            "title" => "Missing Fields",
            "text" => "Please fill in all fields before submitting.",
            "icon" => "warning"
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Among Us OMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Turret+Road:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #235685;
            font-family: 'Turret Road', sans-serif;
            color: white;
        }
        .card {
            background-color: rgba(117, 219, 244, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .btn-success {
            background-color: #FFDE2A;
            border-color: #FFDE2A;
            color: black;
            font-weight: bold;
        }
        .btn-success:hover {
            background-color: #e6c315;
            border-color: #e6c315;
        }
        a {
            color: #FFDE2A;
        }
        a:hover {
            color: #e6c315;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="card p-4" style="width: 400px;">
    <h3 class="text-center mb-3">Register Account</h3>

    <form method="POST" id="registerForm">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
                <option value="superadmin">Superadmin</option>
                <option value="cashier">Cashier</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success w-100">Register</button>

        <div class="text-center mt-3">
            <a href="index.php">← Back to Login</a>
        </div>
    </form>
</div>

<?php if (!empty($alert['title'])): ?>
<script>
Swal.fire({
    title: "<?= htmlspecialchars($alert['title']) ?>",
    text: "<?= htmlspecialchars($alert['text']) ?>",
    icon: "<?= htmlspecialchars($alert['icon']) ?>",
    confirmButtonText: "OK",
    confirmButtonColor: "<?= $alert['icon'] === 'success' ? '#28a745' : '#dc3545' ?>"
}).then((result) => {
    // Redirect after success
    if ("<?= $alert['icon'] ?>" === "success") {
        setTimeout(() => {
            window.location.href = "index.php";
        }, 2000);
    }
});
</script>
<?php endif; ?>

</body>
</html>
