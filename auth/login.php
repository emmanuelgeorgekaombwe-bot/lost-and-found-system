<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role']==='admin' ? '../admin/dashboard.php' : '../student/dashboard.php'));
    exit();
}

$error   = '';
$success = '';

// ── RESET PASSWORD REQUEST ──
if (isset($_POST['action']) && $_POST['action'] === 'reset') {
    $email = trim($_POST['reset_email']);
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $res = mysqli_query($conn, "SELECT user_id, full_name FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."'");
        if (mysqli_num_rows($res) === 0) {
            $error = 'No account found with that email address.';
        } else {
            $user  = mysqli_fetch_assoc($res);
            $def   = password_hash('CBEdefault', PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password='$def', must_change_password=1 WHERE user_id={$user['user_id']}");
            $success = 'Your password has been reset to the default password: CBEdefault. Please log in and change it immediately.';
        }
    }
}

// ── NORMAL LOGIN ──
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        $res = mysqli_query($conn, "SELECT * FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."'");
        if ($res && mysqli_num_rows($res) === 1) {
            $user = mysqli_fetch_assoc($res);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']            = $user['user_id'];
                $_SESSION['full_name']          = $user['full_name'];
                $_SESSION['role']               = $user['role'];
                $_SESSION['reg_number']         = $user['reg_number'];
                $_SESSION['must_change_password']= $user['must_change_password'] ?? 0;
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/dashboard.php'); exit();
                }
                header('Location: ../student/dashboard.php'); exit();
            } else {
                $error = 'Incorrect password. Please try again.';
            }
        } else {
            $error = 'No account found with that email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5}
        html,body{min-height:100%;font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white)}
        body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 80% 60% at 15% 10%,rgba(26,58,110,0.55) 0%,transparent 60%);pointer-events:none;z-index:0}
        body::after{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(201,168,76,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,0.04) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0}
        .logo-watermark{position:fixed;inset:0;background-image:url('../assets/images/College_of_Business_Education.jpg');background-repeat:no-repeat;background-position:center center;background-size:50%;opacity:0.04;pointer-events:none;z-index:0}

        nav{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:14px 48px;background:rgba(10,22,40,0.85);backdrop-filter:blur(12px);border-bottom:1px solid rgba(201,168,76,0.15)}
        .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
        .nav-brand img{width:38px;height:38px;object-fit:contain;border-radius:4px;background:rgba(255,255,255,0.05);padding:2px}
        .nav-logo{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--gold)}
        .nav-logo span{color:var(--white);font-weight:400}
        .nav-link{font-size:0.88rem;color:var(--muted);text-decoration:none;transition:color 0.2s}
        .nav-link:hover{color:var(--white)}

        .page{position:relative;z-index:1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:100px 24px 60px}
        .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:16px;padding:48px 44px;width:100%;max-width:440px;animation:fadeUp 0.5s ease both}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

        .card-label{font-size:0.75rem;font-weight:600;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px;text-align:center}
        .card-title{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;margin-bottom:6px;text-align:center}
        .card-sub{font-size:0.87rem;color:var(--muted);font-weight:300;text-align:center;margin-bottom:28px}

        .alert{padding:13px 16px;border-radius:8px;font-size:0.88rem;margin-bottom:22px}
        .alert-error{background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.3);color:#f08080}
        .alert-success{background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97}

        .form-group{margin-bottom:18px}
        label{display:block;font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase}
        input{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px 16px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--white);outline:none;transition:border-color 0.2s}
        input::placeholder{color:rgba(138,155,181,0.5)}
        input:focus{border-color:rgba(201,168,76,0.5);background:rgba(255,255,255,0.07)}

        .btn-submit{width:100%;padding:13px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:6px;transition:all 0.2s}
        .btn-submit:hover{background:var(--gold2);transform:translateY(-1px)}

        .forgot-link{display:block;text-align:right;font-size:0.82rem;color:var(--gold);cursor:pointer;margin-top:6px;text-decoration:none;background:none;border:none;font-family:'DM Sans',sans-serif}
        .forgot-link:hover{text-decoration:underline}

        .divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:24px 0}
        .card-footer{text-align:center;margin-top:22px;font-size:0.87rem;color:var(--muted)}
        .card-footer a{color:var(--gold);text-decoration:none;font-weight:500}

        /* ── POPUP OVERLAY ── */
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .overlay.show{display:flex}
        .popup{background:#0d1f38;border:1px solid rgba(201,168,76,0.25);border-radius:16px;padding:40px 36px;width:100%;max-width:420px;position:relative;animation:popIn 0.3s ease both}
        @keyframes popIn{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)}}
        .popup-title{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;margin-bottom:8px;text-align:center}
        .popup-sub{font-size:0.85rem;color:var(--muted);text-align:center;margin-bottom:24px;line-height:1.6}
        .popup-close{position:absolute;top:14px;right:18px;background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;line-height:1}
        .popup-close:hover{color:var(--white)}
        .popup-btn{width:100%;padding:12px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.92rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:8px;transition:all 0.2s}
        .popup-btn:hover{background:var(--gold2)}
        .popup-note{font-size:0.78rem;color:var(--muted);text-align:center;margin-top:14px;line-height:1.5}

        @media(max-width:540px){.card{padding:32px 20px}nav{padding:14px 20px}}
    </style>
</head>
<body>
<div class="logo-watermark"></div>

<nav>
    <a href="../index.php" class="nav-brand">
        <img src="../assets/images/College_of_Business_Education.jpg" alt="CBE">
        <span class="nav-logo">CBE <span>Lost & Found</span></span>
    </a>
    <a href="register.php" class="nav-link">No account? Register →</a>
</nav>

<div class="page">
    <div class="card">
        <p class="card-label">Welcome Back</p>
        <h1 class="card-title">Sign In</h1>
        <p class="card-sub">Enter your registered email and password to continue.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">✔ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       placeholder="e.g. student@gmail.com"
                       value="<?php echo isset($_POST['email'])?htmlspecialchars($_POST['email']):''; ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password" required>
                <button type="button" class="forgot-link" onclick="openReset()">Forgot password?</button>
            </div>

            <button type="submit" class="btn-submit">Sign In to My Account</button>
        </form>

        <hr class="divider">
        <div class="card-footer">
            Do not have an account? <a href="register.php">Register here</a>
        </div>
        <p style="text-align:center;font-size:0.78rem;color:var(--muted);margin-top:14px;line-height:1.6">
            Admin accounts are created by the system administrator.<br>
            Students can self-register using the link above.
        </p>
    </div>
</div>

<!-- ── FORGOT PASSWORD POPUP ── -->
<div class="overlay" id="resetOverlay">
    <div class="popup">
        <button class="popup-close" onclick="closeReset()" title="Close">&times;</button>
        <p class="popup-title">Reset Password</p>
        <p class="popup-sub">Enter your registered email address. Your password will be reset to the default password <strong style="color:var(--gold)">CBEdefault</strong> and you will be asked to change it when you log in.</p>

        <form method="POST" action="">
            <input type="hidden" name="action" value="reset">
            <div class="form-group">
                <label for="reset_email">Your Email Address</label>
                <input type="email" id="reset_email" name="reset_email" placeholder="e.g. student@gmail.com" required>
            </div>
            <button type="submit" class="popup-btn">Reset My Password</button>
        </form>
        <p class="popup-note">After logging in with <strong>CBEdefault</strong>, you will be prompted to set a new password before accessing the system.</p>
    </div>
</div>

<script>
function openReset() {
    document.getElementById('resetOverlay').classList.add('show');
    document.getElementById('reset_email').focus();
}
function closeReset() {
    document.getElementById('resetOverlay').classList.remove('show');
}
document.getElementById('resetOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeReset();
});
<?php if ($success && isset($_POST['action']) && $_POST['action']==='reset'): ?>
openReset();
<?php endif; ?>
</script>
</body>
</html>
