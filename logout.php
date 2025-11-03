<?php
session_start();

// If user is not logged in, redirect immediately
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle logout via AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logout | Order Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
    title: 'Are you sure you want to logout?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, logout',
    cancelButtonText: 'Cancel'
}).then((result) => {
    if (result.isConfirmed) {
        // Smooth logout using fetch
        fetch('logout.php?action=logout')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Logged Out',
                        text: 'You have been successfully logged out.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                }
            });
    } else {
        // Redirect back based on role
        <?php if ($_SESSION['role'] === 'superadmin'): ?>
            window.location.href = 'superadmin_dashboard.php';
        <?php else: ?>
            window.location.href = 'pos.php';
        <?php endif; ?>
    }
});
</script>
</body>
</html>
