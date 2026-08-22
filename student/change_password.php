<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php'); exit();
}

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass    = $_POST['new_password'];
    $confirm     = $_POST['confirm_password'];

    $pass_upper   = preg_match('/[A-Z]/', $new_pass);
    $pass_lower   = preg_match('/[a-z]/', $new_pass);
    $pass_special = preg_match('/[\W_]/', $new_pass);
    $pass_length  = strlen($new_pass) >= 8;

    if (!$pass_length) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!$pass_upper) {
        $error = 'Password must include at least one uppercase letter.';
    } elseif (!$pass_lower) {
        $error = 'Password must include at least one lowercase letter.';
    } elseif (!$pass_special) {
        $error = 'Password must include at least one special character.';
    } elseif ($new_pass === 'CBEdefault') {
        $error = 'You cannot keep the default password. Please choose a new one.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$hashed', must_change_password=0 WHERE user_id=$user_id");
        $_SESSION['must_change_password'] = 0;
        $success = 'Password changed successfully!';
    }
}

header('Content-Type: application/json');
if ($error)   echo json_encode(['status'=>'error',  'message'=>$error]);
elseif($success) echo json_encode(['status'=>'success','message'=>$success]);
else echo json_encode(['status'=>'idle']);
