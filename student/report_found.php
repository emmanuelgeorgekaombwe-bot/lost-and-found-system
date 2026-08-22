<?php
session_start();
require_once '../config/db.php';

// Protect page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$error     = '';
$success   = '';

// Fetch categories
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name     = trim($_POST['item_name']);
    $category_id   = intval($_POST['category_id']);
    $description   = trim($_POST['description']);
    $color         = trim($_POST['color']);
    $brand         = trim($_POST['brand']);
    $location      = trim($_POST['location']);
    $date_occurred = $_POST['date_occurred'];
    $contact_phone = trim($_POST['contact_phone']);

    if (empty($item_name) || empty($category_id) || empty($description) || empty($location) || empty($date_occurred) || empty($contact_phone)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Handle photo upload
        $item_photo = null;
        if (!empty($_FILES['item_photo']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext     = strtolower(pathinfo($_FILES['item_photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG, and GIF images are allowed.';
            } elseif ($_FILES['item_photo']['size'] > 2 * 1024 * 1024) {
                $error = 'Image must be smaller than 2MB.';
            } else {
                $filename   = 'found_' . time() . '_' . $user_id . '.' . $ext;
                $upload_dir = '../uploads/items/';
                if (move_uploaded_file($_FILES['item_photo']['tmp_name'], $upload_dir . $filename)) {
                    $item_photo = $filename;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (empty($error)) {
            $photo_val = $item_photo ? "'".mysqli_real_escape_string($conn,$item_photo)."'" : "NULL";
            $sql = "INSERT INTO items (user_id, category_id, item_name, description, color, brand, location, date_occurred, item_photo, contact_phone, type)
                    VALUES (
                        $user_id,
                        $category_id,
                        '".mysqli_real_escape_string($conn,$item_name)."',
                        '".mysqli_real_escape_string($conn,$description)."',
                        '".mysqli_real_escape_string($conn,$color)."',
                        '".mysqli_real_escape_string($conn,$brand)."',
                        '".mysqli_real_escape_string($conn,$location)."',
                        '".mysqli_real_escape_string($conn,$date_occurred)."',
                        $photo_val,
                        '".mysqli_real_escape_string($conn,$contact_phone)."',
                        'found'
                    )";
            if (mysqli_query($conn, $sql)) {
                $success = 'Thank you! The found item has been posted. The owner will be notified if they have a matching report.';
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
    <title>Report Found Item — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy:   #0a1628;
            --gold:   #c9a84c;
            --gold2:  #e8c97a;
            --white:  #f7f4ef;
            --muted:  #8a9bb5;
            --card:   rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
            --green:  #4caf82;
        }

        html, body {
            min-height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--white);
        }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background: radial-gradient(ellipse 70% 50% at 10% 5%, rgba(26,58,110,0.5) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }

        body::after {
            content: '';
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none; z-index: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0; width: 240px;
            background: rgba(10,22,40,0.95);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            padding: 32px 0; z-index: 100;
        }

        .sidebar-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem; font-weight: 700;
            color: var(--gold);
            padding: 0 28px 32px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo span { color: var(--white); font-weight: 400; }

        .sidebar-menu { flex: 1; padding: 24px 0; }

        .menu-label {
            font-size: 0.7rem; font-weight: 600;
            color: var(--muted); letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0 28px; margin-bottom: 8px; margin-top: 20px;
        }

        .menu-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 28px; text-decoration: none;
            font-size: 0.9rem; color: var(--muted);
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .menu-item:hover { color: var(--white); background: rgba(255,255,255,0.04); }
        .menu-item.active { color: var(--gold); border-left-color: var(--gold); background: rgba(201,168,76,0.06); }
        .menu-icon { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
        }
        .user-info { font-size: 0.82rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
        .user-info strong { color: var(--white); display: block; font-size: 0.9rem; }

        .btn-logout {
            display: block; text-align: center; padding: 9px;
            background: rgba(224,92,92,0.1);
            border: 1px solid rgba(224,92,92,0.2);
            border-radius: 6px; color: #f08080;
            text-decoration: none; font-size: 0.85rem; font-weight: 500;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(224,92,92,0.2); }

        /* ── Main ── */
        .main {
            margin-left: 240px;
            padding: 40px 40px 60px;
            position: relative; z-index: 1;
        }

        .page-header { margin-bottom: 32px; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            text-decoration: none; color: var(--muted);
            font-size: 0.85rem; margin-bottom: 16px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--white); }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; font-weight: 700;
        }
        .page-sub { font-size: 0.88rem; color: var(--muted); margin-top: 4px; font-weight: 300; }

        /* ── Info Banner ── */
        .info-banner {
            background: rgba(76,175,130,0.08);
            border: 1px solid rgba(76,175,130,0.2);
            border-radius: 10px;
            padding: 16px 20px;
            font-size: 0.88rem;
            color: #6fcf97;
            margin-bottom: 28px;
            line-height: 1.6;
        }

        /* ── Form Card ── */
        .form-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
            max-width: 700px;
        }

        /* ── Alerts ── */
        .alert {
            padding: 14px 18px; border-radius: 8px;
            font-size: 0.9rem; margin-bottom: 28px;
        }
        .alert-error {
            background: rgba(224,92,92,0.1);
            border: 1px solid rgba(224,92,92,0.3);
            color: #f08080;
        }
        .alert-success {
            background: rgba(76,175,130,0.1);
            border: 1px solid rgba(76,175,130,0.3);
            color: #6fcf97;
        }

        /* ── Form Elements ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group { margin-bottom: 22px; }

        label {
            display: block;
            font-size: 0.8rem; font-weight: 600;
            color: var(--muted); margin-bottom: 8px;
            letter-spacing: 0.05em; text-transform: uppercase;
        }

        label .required { color: var(--gold); margin-left: 3px; }

        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.92rem;
            color: var(--white);
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        input[type="text"]::placeholder,
        textarea::placeholder { color: rgba(138,155,181,0.5); }

        input[type="text"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            border-color: rgba(76,175,130,0.5);
            background: rgba(255,255,255,0.07);
        }

        select option { background: #0a1628; color: var(--white); }
        textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
        .hint { font-size: 0.78rem; color: var(--muted); margin-top: 5px; font-weight: 300; }

        /* ── File Upload ── */
        .file-upload {
            border: 2px dashed rgba(76,175,130,0.2);
            border-radius: 8px;
            padding: 28px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .file-upload:hover { border-color: rgba(76,175,130,0.5); }
        .file-upload input { display: none; }
        .file-upload-icon { font-size: 2rem; margin-bottom: 8px; }
        .file-upload-text { font-size: 0.88rem; color: var(--muted); }
        .file-upload-text strong { color: #6fcf97; }

        /* ── Divider ── */
        .section-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 28px 0;
        }

        .section-heading {
            font-family: 'Playfair Display', serif;
            font-size: 1rem; font-weight: 700;
            margin-bottom: 20px; color: var(--white);
        }

        /* ── Submit ── */
        .btn-submit {
            padding: 14px 36px;
            background: var(--green);
            color: var(--navy);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem; font-weight: 700;
            border: none; border-radius: 8px;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-submit:hover { background: #5ec99a; transform: translateY(-1px); }

        .btn-cancel {
            padding: 14px 24px;
            background: transparent;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem; font-weight: 500;
            border: 1px solid var(--border);
            border-radius: 8px; cursor: pointer;
            text-decoration: none; margin-left: 12px;
            transition: all 0.2s;
        }
        .btn-cancel:hover { color: var(--white); border-color: rgba(255,255,255,0.2); }

        .form-actions { display: flex; align-items: center; margin-top: 8px; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 24px 20px; }
            .form-row { grid-template-columns: 1fr; }
        }
    .logo-watermark{position:fixed;inset:0;background-image:url('../assets/images/College_of_Business_Education.jpg');background-repeat:no-repeat;background-position:center center;background-size:50%;opacity:0.04;pointer-events:none;z-index:0;}
        </style>
</head>
<body>
<div class="logo-watermark"></div>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo"><img src="../assets/images/College_of_Business_Education.jpg" style="width:36px;height:36px;object-fit:contain;border-radius:4px;background:rgba(255,255,255,0.05);padding:2px;margin-bottom:8px;display:block;" alt="CBE">CBE <span>Lost &amp; Found</span></div>
    <nav class="sidebar-menu">
        <p class="menu-label">Main</p>
        <a href="dashboard.php" class="menu-item"><span class="menu-icon">🏠</span> Dashboard</a>
        <a href="search.php" class="menu-item"><span class="menu-icon">🔍</span> Search Items</a>
        <p class="menu-label">Reports</p>
        <a href="report_lost.php" class="menu-item"><span class="menu-icon">📋</span> Report Lost Item</a>
        <a href="report_found.php" class="menu-item active"><span class="menu-icon">📦</span> Report Found Item</a>
        <a href="my_reports.php" class="menu-item"><span class="menu-icon">📁</span> My Reports</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <strong><?php echo htmlspecialchars($full_name); ?></strong>
            <?php echo htmlspecialchars($_SESSION['reg_number']); ?>
        </div>
        <a href="../auth/logout.php" class="btn-logout">Sign Out</a>
    </div>
</aside>

<!-- Main Content -->
<main class="main">
    <div class="page-header">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        <h1 class="page-title">Report a Found Item</h1>
        <p class="page-sub">Help a fellow CBE student recover their belongings by posting what you found.</p>
    </div>

    <div class="info-banner">
        🤝 <strong>Thank you for being honest.</strong> Please hand the item to the CBE Security Office or keep it safely until the owner claims it through this system.
    </div>

    <div class="form-card">

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✔ <?php echo htmlspecialchars($success); ?>
                <br><a href="my_reports.php" style="color:#6fcf97;font-weight:600;">View my reports →</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="" enctype="multipart/form-data">

            <h3 class="section-heading">Item Details</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" id="item_name" name="item_name"
                           placeholder="e.g. Blue student ID card"
                           value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>"
                           required>
                </div>
                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">— Select category —</option>
                        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $cat['category_id']; ?>"
                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description"
                          placeholder="Describe the item clearly — colour, condition, any markings, text, or unique features you noticed."
                          required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="color">Colour</label>
                    <input type="text" id="color" name="color"
                           placeholder="e.g. Black, Silver, Red"
                           value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="brand">Brand / Make</label>
                    <input type="text" id="brand" name="brand"
                           placeholder="e.g. Samsung, Lenovo, Nike"
                           value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>">
                </div>
            </div>

            <hr class="section-divider">
            <h3 class="section-heading">Where & When You Found It</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">Location Found <span class="required">*</span></label>
                    <input type="text" id="location" name="location"
                           placeholder="e.g. CBE Cafeteria, Main Gate, Block A"
                           value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>"
                           required>
                </div>
                <div class="form-group">
                    <label for="date_occurred">Date Found <span class="required">*</span></label>
                    <input type="date" id="date_occurred" name="date_occurred"
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo isset($_POST['date_occurred']) ? htmlspecialchars($_POST['date_occurred']) : date('Y-m-d'); ?>"
                           required>
                </div>
            </div>

            <hr class="section-divider">
            <h3 class="section-heading">Photo (Recommended)</h3>

            <div class="form-group">
                <label class="file-upload" for="item_photo">
                    <input type="file" id="item_photo" name="item_photo" accept="image/*"
                           onchange="updateFileName(this)">
                    <div class="file-upload-icon">📷</div>
                    <div class="file-upload-text" id="file-label">
                        <strong>Click to upload a photo</strong> of the found item<br>
                        JPG, PNG or GIF — max 2MB
                    </div>
                </label>
                <p class="hint">A clear photo helps the owner verify the item belongs to them and speeds up the claim process.</p>
            </div>

            <hr class="section-divider">
            <h3 class="section-heading">Contact Information</h3>

            <div class="form-group">
                <label for="contact_phone">Phone Number <span class="required">*</span></label>
                <input type="text" id="contact_phone" name="contact_phone"
                       placeholder="e.g. +255 712 345 678"
                       value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ''; ?>"
                       required>
                <p class="hint">Your number will be shared with the admin so the owner can be directed to you to collect their item.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Submit Found Report</button>
                <a href="dashboard.php" class="btn-cancel">Cancel</a>
            </div>

        </form>
        <?php endif; ?>

    </div>
</main>

<script>
function updateFileName(input) {
    const label = document.getElementById('file-label');
    if (input.files && input.files[0]) {
        label.innerHTML = '✔ <strong>' + input.files[0].name + '</strong> selected';
    }
}
</script>

</body>
</html>
