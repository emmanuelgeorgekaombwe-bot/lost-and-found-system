<?php
session_start();
require_once '../config/db.php';

// Protect page - must be logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Count user's lost reports
$lost_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM items WHERE user_id=$user_id AND type='lost'"))['total'];

// Count user's found reports
$found_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM items WHERE user_id=$user_id AND type='found'"))['total'];

// Count user's pending claims
$claims_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM claims WHERE claimant_id=$user_id AND status='pending'"))['total'];

// Count unread notifications
$notif_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM notifications WHERE user_id=$user_id AND is_read=0"))['total'];

// Get recent 5 items reported by this user
$recent = mysqli_query($conn,
    "SELECT i.*, c.category_name FROM items i
     JOIN categories c ON i.category_id = c.category_id
     WHERE i.user_id = $user_id
     ORDER BY i.created_at DESC LIMIT 5");

// Get latest 3 notifications
$notifs = mysqli_query($conn,
    "SELECT * FROM notifications WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --navy:   #0a1628;
            --blue:   #1a3a6e;
            --gold:   #c9a84c;
            --gold2:  #e8c97a;
            --white:  #f7f4ef;
            --muted:  #8a9bb5;
            --card:   rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.08);
        }

        html, body {
            min-height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--white);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 10% 5%, rgba(26,58,110,0.5) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 90% 90%, rgba(201,168,76,0.08) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: 240px;
            background: rgba(10,22,40,0.95);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 32px 0;
            z-index: 100;
        }

        .sidebar-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gold);
            padding: 0 28px 32px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-logo span { color: var(--white); font-weight: 400; }

        .sidebar-menu {
            flex: 1;
            padding: 24px 0;
        }

        .menu-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0 28px;
            margin-bottom: 8px;
            margin-top: 20px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 28px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 400;
            color: var(--muted);
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            color: var(--white);
            background: rgba(255,255,255,0.04);
        }

        .menu-item.active {
            color: var(--gold);
            border-left-color: var(--gold);
            background: rgba(201,168,76,0.06);
        }

        .menu-icon { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
        }

        .user-info {
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .user-info strong {
            color: var(--white);
            display: block;
            font-size: 0.9rem;
        }

        .btn-logout {
            display: block;
            text-align: center;
            padding: 9px;
            background: rgba(224,92,92,0.1);
            border: 1px solid rgba(224,92,92,0.2);
            border-radius: 6px;
            color: #f08080;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            background: rgba(224,92,92,0.2);
        }

        /* ── Main Content ── */
        .main {
            margin-left: 240px;
            padding: 40px 40px 60px;
            position: relative;
            z-index: 1;
            min-height: 100vh;
        }

        /* ── Top Bar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .page-sub {
            font-size: 0.88rem;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 300;
        }

        .notif-btn {
            position: relative;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            color: var(--white);
            transition: background 0.2s;
        }
        .notif-btn:hover { background: rgba(255,255,255,0.08); }

        .notif-badge {
            position: absolute;
            top: -5px; right: -5px;
            background: var(--gold);
            color: var(--navy);
            font-size: 0.65rem;
            font-weight: 700;
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); }

        .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 12px;
            display: block;
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--gold);
            display: block;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 400;
        }

        /* ── Action Buttons ── */
        .actions {
            display: flex;
            gap: 16px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-primary {
            background: var(--gold);
            color: var(--navy);
        }
        .action-primary:hover {
            background: var(--gold2);
            transform: translateY(-1px);
        }

        .action-secondary {
            background: var(--card);
            color: var(--white);
            border: 1px solid var(--border);
        }
        .action-secondary:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-1px);
        }

        /* ── Two Column Layout ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
        }

        /* ── Section Card ── */
        .section-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .section-link {
            font-size: 0.82rem;
            color: var(--gold);
            text-decoration: none;
        }
        .section-link:hover { text-decoration: underline; }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        th {
            text-align: left;
            padding: 12px 24px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 14px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: var(--white);
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-lost {
            background: rgba(224,92,92,0.15);
            color: #f08080;
            border: 1px solid rgba(224,92,92,0.2);
        }

        .badge-found {
            background: rgba(76,175,130,0.15);
            color: #6fcf97;
            border: 1px solid rgba(76,175,130,0.2);
        }

        .badge-open {
            background: rgba(201,168,76,0.15);
            color: var(--gold);
            border: 1px solid rgba(201,168,76,0.2);
        }

        .badge-claimed {
            background: rgba(100,180,255,0.15);
            color: #7ec8e3;
            border: 1px solid rgba(100,180,255,0.2);
        }

        .badge-closed {
            background: rgba(138,155,181,0.15);
            color: var(--muted);
            border: 1px solid rgba(138,155,181,0.2);
        }

        /* ── Empty State ── */
        .empty {
            text-align: center;
            padding: 48px 24px;
            color: var(--muted);
        }

        .empty-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .empty p { font-size: 0.9rem; font-weight: 300; }

        /* ── Notifications ── */
        .notif-item {
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .notif-item:last-child { border-bottom: none; }

        .notif-item.unread {
            background: rgba(201,168,76,0.04);
            border-left: 3px solid var(--gold);
        }

        .notif-time {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 24px 20px; }
            .two-col { grid-template-columns: 1fr; }
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
        <a href="dashboard.php" class="menu-item active">
            <span class="menu-icon">🏠</span> Dashboard
        </a>
        <a href="search.php" class="menu-item">
            <span class="menu-icon">🔍</span> Search Items
        </a>

        <p class="menu-label">Reports</p>
        <a href="report_lost.php" class="menu-item">
            <span class="menu-icon">📋</span> Report Lost Item
        </a>
        <a href="report_found.php" class="menu-item">
            <span class="menu-icon">📦</span> Report Found Item
        </a>
        <a href="my_reports.php" class="menu-item">
            <span class="menu-icon">📁</span> My Reports
        </a>
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

    <!-- Top Bar -->
    <div class="topbar">
        <div>
            <h1 class="page-title">Welcome, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
            <p class="page-sub">Here is an overview of your Lost & Found activity.</p>
        </div>
        <a href="#" class="notif-btn">
            🔔
            <?php if ($notif_count > 0): ?>
                <span class="notif-badge"><?php echo $notif_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">📋</span>
            <span class="stat-value"><?php echo $lost_count; ?></span>
            <span class="stat-label">Lost Reports</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📦</span>
            <span class="stat-value"><?php echo $found_count; ?></span>
            <span class="stat-label">Found Reports</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⏳</span>
            <span class="stat-value"><?php echo $claims_count; ?></span>
            <span class="stat-label">Pending Claims</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🔔</span>
            <span class="stat-value"><?php echo $notif_count; ?></span>
            <span class="stat-label">Unread Notifications</span>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="actions">
        <a href="report_lost.php" class="action-btn action-primary">
            🔍 Report Lost Item
        </a>
        <a href="report_found.php" class="action-btn action-secondary">
            📦 Report Found Item
        </a>
        <a href="search.php" class="action-btn action-secondary">
            🔎 Search All Items
        </a>
    </div>

    <!-- Two Column -->
    <div class="two-col">

        <!-- Recent Reports -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">My Recent Reports</h2>
                <a href="my_reports.php" class="section-link">View all →</a>
            </div>

            <?php if (mysqli_num_rows($recent) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $row['type']; ?>">
                                    <?php echo ucfirst($row['type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty">
                <div class="empty-icon">📭</div>
                <p>You have not made any reports yet.<br>Use the buttons above to get started.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notifications -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Notifications</h2>
            </div>

            <?php if (mysqli_num_rows($notifs) > 0): ?>
                <?php while ($n = mysqli_fetch_assoc($notifs)): ?>
                <div class="notif-item <?php echo $n['is_read'] == 0 ? 'unread' : ''; ?>">
                    <?php echo htmlspecialchars($n['message']); ?>
                    <div class="notif-time"><?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?></div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">🔕</div>
                    <p>No notifications yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ── CHANGE PASSWORD POPUP ── -->
<div class="overlay" id="changePwOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px)">
  <div style="background:#0d1f38;border:1px solid rgba(201,168,76,0.3);border-radius:16px;padding:40px 36px;width:100%;max-width:440px;position:relative;animation:popIn 0.3s ease both">
    <p style="font-family:Playfair Display,serif;font-size:1.4rem;font-weight:700;text-align:center;margin-bottom:8px;color:#f7f4ef">Change Your Password</p>
    <p style="font-size:0.85rem;color:#8a9bb5;text-align:center;margin-bottom:24px;line-height:1.6">You are currently using the default password <strong style="color:#c9a84c">CBEdefault</strong>. You must set a new password before continuing.</p>
    <div id="cpError" style="display:none;padding:12px 16px;border-radius:8px;background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.3);color:#f08080;font-size:0.87rem;margin-bottom:16px"></div>
    <div id="cpSuccess" style="display:none;padding:12px 16px;border-radius:8px;background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97;font-size:0.87rem;margin-bottom:16px"></div>
    <div style="margin-bottom:16px">
      <label style="display:block;font-size:0.78rem;font-weight:600;color:#8a9bb5;margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase">New Password</label>
      <input type="password" id="cpNewPass" placeholder="Min 8 chars, upper, lower, special" style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px 16px;font-family:DM Sans,sans-serif;font-size:0.92rem;color:#f7f4ef;outline:none" oninput="cpStrength(this.value)">
      <div style="margin-top:8px">
        <div style="display:flex;gap:4px;margin-bottom:5px"><span id="cb1" style="flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,0.1);transition:background 0.3s"></span><span id="cb2" style="flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,0.1);transition:background 0.3s"></span><span id="cb3" style="flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,0.1);transition:background 0.3s"></span><span id="cb4" style="flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,0.1);transition:background 0.3s"></span></div>
        <div id="cplabel" style="font-size:0.75rem;color:#8a9bb5">Enter a password</div>
      </div>
    </div>
    <div style="margin-bottom:20px">
      <label style="display:block;font-size:0.78rem;font-weight:600;color:#8a9bb5;margin-bottom:7px;letter-spacing:0.04em;text-transform:uppercase">Confirm New Password</label>
      <input type="password" id="cpConfirm" placeholder="Repeat your new password" style="width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:12px 16px;font-family:DM Sans,sans-serif;font-size:0.92rem;color:#f7f4ef;outline:none">
    </div>
    <button onclick="submitChangePassword()" style="width:100%;padding:13px;background:#c9a84c;color:#0a1628;font-family:DM Sans,sans-serif;font-size:0.95rem;font-weight:700;border:none;border-radius:8px;cursor:pointer;transition:all 0.2s">Update My Password</button>
  </div>
</div>

<style>
@keyframes popIn{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)}}
</style>

<script>
const mustChange = <?php echo (isset($_SESSION["must_change_password"]) && $_SESSION["must_change_password"]==1) ? "true" : "false"; ?>;
if (mustChange) {
  document.getElementById("changePwOverlay").style.display = "flex";
}

function cpStrength(val) {
  const len=val.length>=8, up=/[A-Z]/.test(val), lo=/[a-z]/.test(val), sp=/[\W_]/.test(val);
  const score=[len,up,lo,sp].filter(Boolean).length;
  const cols=["#e05c5c","#ffb74d","#c9a84c","#4caf82"];
  ["cb1","cb2","cb3","cb4"].forEach((id,i)=>{
    document.getElementById(id).style.background = i<score ? cols[score-1] : "rgba(255,255,255,0.1)";
  });
  const labs=["","Weak","Fair","Good","Strong"];
  document.getElementById("cplabel").textContent = val.length===0?"Enter a password":labs[score];
}

function submitChangePassword() {
  const np = document.getElementById("cpNewPass").value;
  const cp = document.getElementById("cpConfirm").value;
  const errEl = document.getElementById("cpError");
  const sucEl = document.getElementById("cpSuccess");
  errEl.style.display="none"; sucEl.style.display="none";

  const fd = new FormData();
  fd.append("new_password", np);
  fd.append("confirm_password", cp);

  fetch("../student/change_password.php", {method:"POST", body:fd})
    .then(r=>r.json())
    .then(data=>{
      if (data.status==="error") {
        errEl.textContent = "⚠ " + data.message;
        errEl.style.display = "block";
      } else if (data.status==="success") {
        sucEl.textContent = "✔ " + data.message;
        sucEl.style.display = "block";
        setTimeout(()=>{ document.getElementById("changePwOverlay").style.display="none"; }, 1800);
      }
    });
}
</script>

</body>
</html>
