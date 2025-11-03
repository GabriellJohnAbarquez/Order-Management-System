<?php
session_start();
include 'dbconfig.php';

if (isset($_SESSION['user_id'])) {
    // Redirect logged-in users based on role
    if ($_SESSION['role'] === 'superadmin') {
        header("Location: superadmin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'cashier') {
        header("Location: pos.php");
        exit();
    }
}

$alert = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $alert = "<script>
                    Swal.fire({
                        icon: 'warning',
                        title: 'Account Suspended',
                        text: 'This account is suspended. Contact the superadmin.',
                        confirmButtonColor: '#3085d6'
                    });
                </script>";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'superadmin') {
                    $redirect = 'superadmin_dashboard.php';
                } else {
                    $redirect = 'pos.php';
                }

                $alert = "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful!',
                        text: 'Redirecting to your dashboard...',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = '$redirect';
                    });
                </script>";
            }
        } else {
            $alert = "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: 'Invalid username or password.',
                    confirmButtonColor: '#d33'
                });
            </script>";
        }
    } else {
        $alert = "<script>
            Swal.fire({
                icon: 'info',
                title: 'Missing Fields',
                text: 'Please fill in all fields before logging in.',
                confirmButtonColor: '#3085d6'
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Among Us OMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Turret+Road:wght@400;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
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
        .form-control {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .btn-primary {
            background-color: #FFDE2A;
            border-color: #FFDE2A;
            color: black;
            font-weight: bold;
        }
        .btn-primary:hover {
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
    <h3 class="text-center mb-3">Order Management System</h3>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>

        <div class="text-center mt-3">
            <a href="register.php">Don’t have an account? <strong>Register here</strong></a>
        </div>
    </form>
</div>

<?= $alert ?>

</body>
</html>
