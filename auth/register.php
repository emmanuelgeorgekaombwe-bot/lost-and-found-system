<?php
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../student/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $reg_number = trim($_POST['reg_number']);
    $password   = $_POST['password'];
    $confirm    = $_POST['confirm_password'];

    // Validate reg number format: 00.0000.00.00.0000
    $reg_pattern = '/^\d{2}\.\d{4}\.\d{2}\.\d{2}\.\d{4}$/';

    // Validate password strength
    $pass_upper   = preg_match('/[A-Z]/', $password);
    $pass_lower   = preg_match('/[a-z]/', $password);
    $pass_special = preg_match('/[\W_]/', $password);
    $pass_length  = strlen($password) >= 8;

    if (empty($full_name) || empty($email) || empty($reg_number) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match($reg_pattern, $reg_number)) {
        $error = 'Registration number must be in the format: 00.0000.00.00.0000 (e.g. 03.1852.01.01.2023).';
    } elseif (!$pass_length) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!$pass_upper) {
        $error = 'Password must include at least one uppercase letter (A–Z).';
    } elseif (!$pass_lower) {
        $error = 'Password must include at least one lowercase letter (a–z).';
    } elseif (!$pass_special) {
        $error = 'Password must include at least one special character (e.g. @, #, !, $).';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = mysqli_query($conn, "SELECT user_id FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."' OR reg_number='".mysqli_real_escape_string($conn,$reg_number)."'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'An account with this email or registration number already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, reg_number, password, role)
                    VALUES ('".mysqli_real_escape_string($conn,$full_name)."',
                            '".mysqli_real_escape_string($conn,$email)."',
                            '".mysqli_real_escape_string($conn,$reg_number)."',
                            '$hashed', 'student')";
            if (mysqli_query($conn, $sql)) {
                $success = 'Account created successfully! You can now sign in.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5;--green:#4caf82}
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
        .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.09);border-radius:16px;padding:48px 44px;width:100%;max-width:500px;animation:fadeUp 0.5s ease both}
        @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

        .card-label{font-size:0.75rem;font-weight:600;color:var(--gold);letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px;text-align:center}
        .card-title{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:700;margin-bottom:6px;text-align:center}
        .card-sub{font-size:0.87rem;color:var(--muted);font-weight:300;text-align:center;margin-bottom:28px}

        .alert{padding:13px 16px;border-radius:8px;font-size:0.88rem;margin-bottom:22px}
        .alert-error{background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.3);color:#f08080}
        .alert-success{background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97}

        .form-group{margin-bottom:18px}
        label{display:block;font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase}
        input{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px 16px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--white);outline:none;transition:border-color 0.2s,background 0.2s}
        input::placeholder{color:rgba(138,155,181,0.5)}
        input:focus{border-color:rgba(201,168,76,0.5);background:rgba(255,255,255,0.07)}
        .hint{font-size:0.76rem;color:var(--muted);margin-top:5px;font-weight:300}
        .hint.format{color:rgba(201,168,76,0.7)}

        /* Password strength meter */
        .strength-wrap{margin-top:10px}
        .strength-bar{display:flex;gap:4px;margin-bottom:6px}
        .strength-bar span{flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,0.1);transition:background 0.3s}
        .strength-bar span.active-1{background:#e05c5c}
        .strength-bar span.active-2{background:#ffb74d}
        .strength-bar span.active-3{background:#c9a84c}
        .strength-bar span.active-4{background:#4caf82}
        .strength-label{font-size:0.75rem;color:var(--muted)}
        .req-list{margin-top:8px;display:flex;flex-direction:column;gap:4px}
        .req{font-size:0.76rem;color:var(--muted);display:flex;align-items:center;gap:6px;transition:color 0.3s}
        .req.met{color:#6fcf97}
        .req::before{content:'○';font-size:0.7rem;transition:content 0.2s}
        .req.met::before{content:'●'}

        .divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:20px 0}
        .btn-submit{width:100%;padding:13px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;margin-top:6px;transition:all 0.2s}
        .btn-submit:hover{background:var(--gold2);transform:translateY(-1px)}
        .card-footer{text-align:center;margin-top:22px;font-size:0.87rem;color:var(--muted)}
        .card-footer a{color:var(--gold);text-decoration:none;font-weight:500}
        .card-footer a:hover{text-decoration:underline}

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
    <a href="login.php" class="nav-link">Already registered? Sign in</a>
</nav>

<div class="page">
    <div class="card">
        <p class="card-label">Student Registration</p>
        <h1 class="card-title">Create Account</h1>
        <p class="card-sub">Register with your official CBE student details.</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">✔ <?php echo htmlspecialchars($success); ?><br>
                <a href="login.php" style="color:#6fcf97;font-weight:600;">Click here to sign in →</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="" id="regForm">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" placeholder="e.g. Aneth Assa Ngonile"
                       value="<?php echo isset($_POST['full_name'])?htmlspecialchars($_POST['full_name']):''; ?>" required>
            </div>

            <div class="form-group">
                <label for="reg_number">Registration Number</label>
                <input type="text" id="reg_number" name="reg_number"
                       placeholder="00.0000.00.00.0000"
                       maxlength="19"
                       value="<?php echo isset($_POST['reg_number'])?htmlspecialchars($_POST['reg_number']):''; ?>"
                       required>
                <p class="hint format">Format: 00.0000.00.00.0000 — e.g. 03.1852.01.01.2023</p>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="e.g. student@gmail.com"
                       value="<?php echo isset($_POST['email'])?htmlspecialchars($_POST['email']):''; ?>" required>
            </div>

            <hr class="divider">

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       placeholder="Create a strong password" required oninput="checkStrength(this.value)">
                <div class="strength-wrap">
                    <div class="strength-bar">
                        <span id="b1"></span><span id="b2"></span>
                        <span id="b3"></span><span id="b4"></span>
                    </div>
                    <div class="strength-label" id="slabel">Enter a password</div>
                    <div class="req-list">
                        <div class="req" id="r-len">At least 8 characters</div>
                        <div class="req" id="r-up">At least one uppercase letter (A–Z)</div>
                        <div class="req" id="r-lo">At least one lowercase letter (a–z)</div>
                        <div class="req" id="r-sp">At least one special character (@, #, !, $, etc.)</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="Repeat your password" required>
            </div>

            <button type="submit" class="btn-submit">Create My Account</button>
        </form>
        <?php endif; ?>

        <div class="card-footer">
            Already registered? <a href="login.php">Sign in here</a>
        </div>
    </div>
</div>

<script>
// Auto-format registration number as user types
document.getElementById('reg_number').addEventListener('input', function(e) {
    let v = e.target.value.replace(/[^\d]/g, '');
    let f = '';
    if (v.length > 0)  f += v.substring(0, Math.min(2, v.length));
    if (v.length > 2)  f += '.' + v.substring(2, Math.min(6, v.length));
    if (v.length > 6)  f += '.' + v.substring(6, Math.min(8, v.length));
    if (v.length > 8)  f += '.' + v.substring(8, Math.min(10, v.length));
    if (v.length > 10) f += '.' + v.substring(10, Math.min(14, v.length));
    e.target.value = f;
});

function checkStrength(val) {
    const len = val.length >= 8;
    const up  = /[A-Z]/.test(val);
    const lo  = /[a-z]/.test(val);
    const sp  = /[\W_]/.test(val);
    const score = [len, up, lo, sp].filter(Boolean).length;

    // Update requirement indicators
    document.getElementById('r-len').className = 'req' + (len ? ' met' : '');
    document.getElementById('r-up').className  = 'req' + (up  ? ' met' : '');
    document.getElementById('r-lo').className  = 'req' + (lo  ? ' met' : '');
    document.getElementById('r-sp').className  = 'req' + (sp  ? ' met' : '');

    // Update strength bars
    const bars = ['b1','b2','b3','b4'];
    const cls  = ['active-1','active-2','active-3','active-4'];
    const labels = ['','Weak','Fair','Good','Strong'];
    bars.forEach((id, i) => {
        document.getElementById(id).className = i < score ? cls[score-1] : '';
    });
    document.getElementById('slabel').textContent = val.length === 0 ? 'Enter a password' : labels[score];
}
</script>
</body>
</html>
