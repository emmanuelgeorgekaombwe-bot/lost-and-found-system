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

// Get item_id from URL
$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

if ($item_id === 0) {
    header('Location: search.php');
    exit();
}

// Fetch the item
$item = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT i.*, c.category_name, u.full_name AS reporter_name
     FROM items i
     JOIN categories c ON i.category_id = c.category_id
     JOIN users u ON i.user_id = u.user_id
     WHERE i.item_id = $item_id AND i.status = 'open'"));

// Item not found or already claimed
if (!$item) {
    header('Location: search.php');
    exit();
}

// Cannot claim your own item
if ($item['user_id'] == $user_id) {
    header('Location: search.php');
    exit();
}

// Check if already claimed by this user
$already = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT claim_id FROM claims WHERE item_id=$item_id AND claimant_id=$user_id"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proof_details = trim($_POST['proof_details']);
    $contact_phone = trim($_POST['contact_phone']);

    if (empty($proof_details)) {
        $error = 'Please describe your proof of ownership.';
    } elseif (empty($contact_phone)) {
        $error = 'Please provide your phone number so we can contact you.';
    } elseif ($already) {
        $error = 'You have already submitted a claim for this item.';
    } else {
        // Handle proof photo
        $proof_photo = null;
        if (!empty($_FILES['proof_photo']['name'])) {
            $allowed = ['jpg','jpeg','png','gif'];
            $ext     = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG, and GIF images are allowed.';
            } elseif ($_FILES['proof_photo']['size'] > 2 * 1024 * 1024) {
                $error = 'Image must be smaller than 2MB.';
            } else {
                $filename   = 'proof_' . time() . '_' . $user_id . '.' . $ext;
                $upload_dir = '../uploads/items/';
                if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $upload_dir . $filename)) {
                    $proof_photo = $filename;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }

        if (empty($error)) {
            $photo_val = $proof_photo ? "'".mysqli_real_escape_string($conn,$proof_photo)."'" : "NULL";
            $sql = "INSERT INTO claims (item_id, claimant_id, proof_details, proof_photo, contact_phone)
                    VALUES ($item_id, $user_id,
                            '".mysqli_real_escape_string($conn,$proof_details)."',
                            $photo_val,
                            '".mysqli_real_escape_string($conn,$contact_phone)."')";
            if (mysqli_query($conn, $sql)) {
                // Notify the item reporter
                $notif_msg = "Someone has submitted a claim for your reported item: '{$item['item_name']}'. Admin will review it shortly.";
                mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES ({$item['user_id']}, '".mysqli_real_escape_string($conn,$notif_msg)."')");
                $success = 'Your claim has been submitted successfully. The admin will review it and notify you of the decision.';
                $already = true;
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
    <title>Claim Item — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5;--card:rgba(255,255,255,0.04);--border:rgba(255,255,255,0.08)}
        html,body{min-height:100%;font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white)}
        body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 50% at 10% 5%,rgba(26,58,110,0.5) 0%,transparent 60%);pointer-events:none;z-index:0}
        body::after{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(201,168,76,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,0.04) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0}

        /* Sidebar */
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:240px;background:rgba(10,22,40,0.95);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;z-index:100}
        .sidebar-logo{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:var(--gold);padding:0 28px 32px;border-bottom:1px solid var(--border)}
        .sidebar-logo span{color:var(--white);font-weight:400}
        .sidebar-menu{flex:1;padding:24px 0}
        .menu-label{font-size:0.7rem;font-weight:600;color:var(--muted);letter-spacing:0.1em;text-transform:uppercase;padding:0 28px;margin-bottom:8px;margin-top:20px}
        .menu-item{display:flex;align-items:center;gap:12px;padding:11px 28px;text-decoration:none;font-size:0.9rem;color:var(--muted);transition:all 0.2s;border-left:3px solid transparent}
        .menu-item:hover{color:var(--white);background:rgba(255,255,255,0.04)}
        .menu-item.active{color:var(--gold);border-left-color:var(--gold);background:rgba(201,168,76,0.06)}
        .menu-icon{font-size:1rem;width:20px;text-align:center}
        .sidebar-footer{padding:20px 28px;border-top:1px solid var(--border)}
        .user-info{font-size:0.82rem;color:var(--muted);margin-bottom:12px;line-height:1.5}
        .user-info strong{color:var(--white);display:block;font-size:0.9rem}
        .btn-logout{display:block;text-align:center;padding:9px;background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.2);border-radius:6px;color:#f08080;text-decoration:none;font-size:0.85rem;font-weight:500;transition:all 0.2s}
        .btn-logout:hover{background:rgba(224,92,92,0.2)}

        /* Main */
        .main{margin-left:240px;padding:40px 40px 60px;position:relative;z-index:1}
        .back-link{display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:var(--muted);font-size:0.85rem;margin-bottom:16px;transition:color 0.2s}
        .back-link:hover{color:var(--white)}
        .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;margin-bottom:4px}
        .page-sub{font-size:0.88rem;color:var(--muted);font-weight:300;margin-bottom:32px}

        /* Layout */
        .two-col{display:grid;grid-template-columns:1fr 380px;gap:24px}

        /* Item preview card */
        .item-preview{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;position:sticky;top:24px}
        .preview-photo{width:100%;height:200px;object-fit:cover}
        .preview-placeholder{width:100%;height:200px;background:rgba(255,255,255,0.03);display:flex;align-items:center;justify-content:center;font-size:4rem;border-bottom:1px solid var(--border)}
        .preview-body{padding:20px}
        .preview-label{font-size:0.72rem;font-weight:600;color:var(--muted);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:6px}
        .preview-name{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;margin-bottom:14px}
        .preview-row{display:flex;gap:8px;align-items:center;font-size:0.84rem;color:var(--muted);margin-bottom:6px}
        .badge{display:inline-block;padding:3px 9px;border-radius:100px;font-size:0.72rem;font-weight:600}
        .badge-lost{background:rgba(224,92,92,0.15);color:#f08080;border:1px solid rgba(224,92,92,0.2)}
        .badge-found{background:rgba(76,175,130,0.15);color:#6fcf97;border:1px solid rgba(76,175,130,0.2)}
        .badge-cat{background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.15)}
        .preview-desc{font-size:0.84rem;color:var(--muted);line-height:1.7;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)}

        /* Form card */
        .form-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:36px}

        .alert{padding:14px 18px;border-radius:8px;font-size:0.9rem;margin-bottom:24px}
        .alert-error{background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.3);color:#f08080}
        .alert-success{background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97}
        .alert-info{background:rgba(201,168,76,0.08);border:1px solid rgba(201,168,76,0.2);color:var(--gold)}

        .form-group{margin-bottom:22px}
        label{display:block;font-size:0.8rem;font-weight:600;color:var(--muted);margin-bottom:8px;letter-spacing:0.05em;text-transform:uppercase}
        .required{color:var(--gold);margin-left:3px}
        textarea,input[type="text"]{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px 16px;font-family:'DM Sans',sans-serif;font-size:0.92rem;color:var(--white);outline:none;transition:border-color 0.2s}
        textarea{resize:vertical;min-height:130px;line-height:1.6}
        textarea::placeholder,input[type="text"]::placeholder{color:rgba(138,155,181,0.5)}
        textarea:focus,input[type="text"]:focus{border-color:rgba(201,168,76,0.5);background:rgba(255,255,255,0.07)}
        .hint{font-size:0.78rem;color:var(--muted);margin-top:5px;font-weight:300}

        .file-upload{border:2px dashed rgba(255,255,255,0.1);border-radius:8px;padding:24px;text-align:center;cursor:pointer;transition:border-color 0.2s}
        .file-upload:hover{border-color:rgba(201,168,76,0.4)}
        .file-upload input{display:none}
        .file-upload-icon{font-size:1.8rem;margin-bottom:8px}
        .file-upload-text{font-size:0.85rem;color:var(--muted)}
        .file-upload-text strong{color:var(--gold)}

        .section-divider{border:none;border-top:1px solid var(--border);margin:24px 0}
        .section-heading{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:16px}

        .btn-submit{width:100%;padding:14px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.95rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:all 0.2s}
        .btn-submit:hover{background:var(--gold2);transform:translateY(-1px)}

        @media(max-width:1024px){.two-col{grid-template-columns:1fr}.item-preview{position:static}}
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:24px 20px}}
    .logo-watermark{position:fixed;inset:0;background-image:url('../assets/images/College_of_Business_Education.jpg');background-repeat:no-repeat;background-position:center center;background-size:50%;opacity:0.04;pointer-events:none;z-index:0;}
        </style>
</head>
<body>
<div class="logo-watermark"></div>

<aside class="sidebar">
    <div class="sidebar-logo"><img src="../assets/images/College_of_Business_Education.jpg" style="width:36px;height:36px;object-fit:contain;border-radius:4px;background:rgba(255,255,255,0.05);padding:2px;margin-bottom:8px;display:block;" alt="CBE">CBE <span>Lost &amp; Found</span></div>
    <nav class="sidebar-menu">
        <p class="menu-label">Main</p>
        <a href="dashboard.php" class="menu-item"><span class="menu-icon">🏠</span> Dashboard</a>
        <a href="search.php" class="menu-item active"><span class="menu-icon">🔍</span> Search Items</a>
        <p class="menu-label">Reports</p>
        <a href="report_lost.php" class="menu-item"><span class="menu-icon">📋</span> Report Lost Item</a>
        <a href="report_found.php" class="menu-item"><span class="menu-icon">📦</span> Report Found Item</a>
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

<main class="main">
    <a href="search.php" class="back-link">← Back to Search</a>
    <h1 class="page-title">Claim This Item</h1>
    <p class="page-sub">Provide proof that this item belongs to you. Admin will review your claim.</p>

    <div class="two-col">

        <!-- Claim Form -->
        <div class="form-card">

            <?php if ($error): ?>
                <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✔ <?php echo htmlspecialchars($success); ?>
                    <br><br>
                    <a href="dashboard.php" style="color:#6fcf97;font-weight:600;">← Back to Dashboard</a>
                </div>
            <?php elseif ($already && !$success): ?>
                <div class="alert alert-info">
                    ⏳ You have already submitted a claim for this item. Please wait for the admin to review it. You will be notified of the decision.
                </div>
            <?php else: ?>

            <div class="alert alert-info" style="margin-bottom:24px">
                📌 Be honest and specific. Vague claims will be rejected. Only submit a claim if this item truly belongs to you.
            </div>

            <form method="POST" action="" enctype="multipart/form-data">

                <h3 class="section-heading">Prove Ownership</h3>

                <div class="form-group">
                    <label for="proof_details">Proof of Ownership <span class="required">*</span></label>
                    <textarea id="proof_details" name="proof_details"
                              placeholder="Describe specific details that prove this item is yours. For example: the phone has a cracked screen on the top-left corner, the bag has my initials written inside in blue pen, my student ID has a sticker on the back..."
                              required><?php echo isset($_POST['proof_details']) ? htmlspecialchars($_POST['proof_details']) : ''; ?></textarea>
                    <p class="hint">The more specific you are, the faster your claim will be approved.</p>
                </div>

                <hr class="section-divider">
                <h3 class="section-heading">Supporting Photo (Optional)</h3>

                <div class="form-group">
                    <label class="file-upload" for="proof_photo">
                        <input type="file" id="proof_photo" name="proof_photo" accept="image/*"
                               onchange="updateFileName(this)">
                        <div class="file-upload-icon">🖼️</div>
                        <div class="file-upload-text" id="file-label">
                            <strong>Upload a photo as evidence</strong><br>
                            e.g. a previous photo of the item, a receipt, or your name written on it<br>
                            JPG, PNG or GIF — max 2MB
                        </div>
                    </label>
                </div>

            <hr class="section-divider">
            <h3 class="section-heading">Contact Information</h3>

            <div class="form-group">
                <label for="contact_phone">Your Phone Number <span class="required">*</span></label>
                <input type="text" id="contact_phone" name="contact_phone"
                       placeholder="e.g. +255 712 345 678"
                       value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ''; ?>"
                       required>
                <p class="hint">The admin and finder will use this number to arrange the return of your item once your claim is approved.</p>
            </div>

                <button type="submit" class="btn-submit">Submit My Claim</button>

            </form>
            <?php endif; ?>
        </div>

        <!-- Item Preview -->
        <div class="item-preview">
            <?php if ($item['item_photo']): ?>
                <img src="../uploads/items/<?php echo htmlspecialchars($item['item_photo']); ?>"
                     class="preview-photo" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
            <?php else: ?>
                <div class="preview-placeholder">
                    <?php echo $item['type'] === 'lost' ? '🔍' : '📦'; ?>
                </div>
            <?php endif; ?>

            <div class="preview-body">
                <div class="preview-label">Item Details</div>
                <div class="preview-name"><?php echo htmlspecialchars($item['item_name']); ?></div>

                <div style="display:flex;gap:8px;margin-bottom:14px">
                    <span class="badge badge-<?php echo $item['type']; ?>"><?php echo ucfirst($item['type']); ?></span>
                    <span class="badge badge-cat"><?php echo htmlspecialchars($item['category_name']); ?></span>
                </div>

                <div class="preview-row">📍 <?php echo htmlspecialchars($item['location']); ?></div>
                <div class="preview-row">📅 <?php echo date('d M Y', strtotime($item['date_occurred'])); ?></div>
                <?php if ($item['color']): ?>
                <div class="preview-row">🎨 <?php echo htmlspecialchars($item['color']); ?></div>
                <?php endif; ?>
                <?php if ($item['brand']): ?>
                <div class="preview-row">🏷️ <?php echo htmlspecialchars($item['brand']); ?></div>
                <?php endif; ?>
                <div class="preview-row">👤 Reported by <?php echo htmlspecialchars(explode(' ',$item['reporter_name'])[0]); ?></div>

                <div class="preview-desc"><?php echo htmlspecialchars($item['description']); ?></div>
            </div>
        </div>

    </div>
</main>

<script>
function updateFileName(input) {
    const label = document.getElementById('file-label');
    if (input.files && input.files[0]) {
        label.innerHTML = '✔ <strong>' + input.files[0].name + '</strong> selected as evidence';
    }
}
</script>

</body>
</html>
