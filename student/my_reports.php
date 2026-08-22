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

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where = "WHERE i.user_id = $user_id";
if ($filter === 'lost')  $where .= " AND i.type = 'lost'";
if ($filter === 'found') $where .= " AND i.type = 'found'";

$reports = mysqli_query($conn,
    "SELECT i.*, c.category_name FROM items i
     JOIN categories c ON i.category_id = c.category_id
     $where
     ORDER BY i.created_at DESC");

$total      = mysqli_num_rows($reports);
$lost_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM items WHERE user_id=$user_id AND type='lost'"))['t'];
$found_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM items WHERE user_id=$user_id AND type='found'"))['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports — CBE Lost & Found</title>
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
            font-size: 1.1rem; font-weight: 700; color: var(--gold);
            padding: 0 28px 32px; border-bottom: 1px solid var(--border);
        }
        .sidebar-logo span { color: var(--white); font-weight: 400; }
        .sidebar-menu { flex: 1; padding: 24px 0; }
        .menu-label {
            font-size: 0.7rem; font-weight: 600; color: var(--muted);
            letter-spacing: 0.1em; text-transform: uppercase;
            padding: 0 28px; margin-bottom: 8px; margin-top: 20px;
        }
        .menu-item {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 28px; text-decoration: none;
            font-size: 0.9rem; color: var(--muted);
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .menu-item:hover { color: var(--white); background: rgba(255,255,255,0.04); }
        .menu-item.active { color: var(--gold); border-left-color: var(--gold); background: rgba(201,168,76,0.06); }
        .menu-icon { font-size: 1rem; width: 20px; text-align: center; }
        .sidebar-footer { padding: 20px 28px; border-top: 1px solid var(--border); }
        .user-info { font-size: 0.82rem; color: var(--muted); margin-bottom: 12px; line-height: 1.5; }
        .user-info strong { color: var(--white); display: block; font-size: 0.9rem; }
        .btn-logout {
            display: block; text-align: center; padding: 9px;
            background: rgba(224,92,92,0.1); border: 1px solid rgba(224,92,92,0.2);
            border-radius: 6px; color: #f08080; text-decoration: none;
            font-size: 0.85rem; font-weight: 500; transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(224,92,92,0.2); }

        /* ── Main ── */
        .main {
            margin-left: 240px;
            padding: 40px 40px 60px;
            position: relative; z-index: 1;
        }

        .topbar {
            display: flex; align-items: flex-start;
            justify-content: space-between; margin-bottom: 32px;
            flex-wrap: wrap; gap: 16px;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; font-weight: 700;
        }
        .page-sub { font-size: 0.88rem; color: var(--muted); margin-top: 4px; font-weight: 300; }

        .btn-new {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px; background: var(--gold); color: var(--navy);
            font-family: 'DM Sans', sans-serif; font-size: 0.88rem; font-weight: 700;
            border-radius: 7px; text-decoration: none; transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-new:hover { background: var(--gold2); transform: translateY(-1px); }

        /* ── Summary Cards ── */
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px; margin-bottom: 28px;
        }

        .sum-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px; padding: 20px 24px;
            display: flex; align-items: center; gap: 16px;
        }

        .sum-icon { font-size: 1.6rem; }
        .sum-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; font-weight: 700;
            color: var(--gold); line-height: 1;
        }
        .sum-label { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }

        /* ── Filter Tabs ── */
        .filter-bar {
            display: flex; gap: 8px; margin-bottom: 24px;
        }

        .filter-tab {
            padding: 8px 20px; border-radius: 6px;
            text-decoration: none; font-size: 0.85rem; font-weight: 500;
            transition: all 0.2s;
            border: 1px solid var(--border);
            color: var(--muted);
        }
        .filter-tab:hover { color: var(--white); border-color: rgba(255,255,255,0.2); }
        .filter-tab.active {
            background: rgba(201,168,76,0.12);
            border-color: rgba(201,168,76,0.3);
            color: var(--gold);
        }

        /* ── Table Card ── */
        .table-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%; border-collapse: collapse; font-size: 0.88rem;
        }

        th {
            text-align: left; padding: 14px 20px;
            font-size: 0.75rem; font-weight: 600;
            color: var(--muted); letter-spacing: 0.06em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
            background: rgba(255,255,255,0.02);
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: var(--white); vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .item-name { font-weight: 500; }
        .item-location { font-size: 0.82rem; color: var(--muted); margin-top: 2px; }

        /* ── Badges ── */
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 100px; font-size: 0.75rem; font-weight: 600;
        }
        .badge-lost { background: rgba(224,92,92,0.15); color: #f08080; border: 1px solid rgba(224,92,92,0.2); }
        .badge-found { background: rgba(76,175,130,0.15); color: #6fcf97; border: 1px solid rgba(76,175,130,0.2); }
        .badge-open { background: rgba(201,168,76,0.15); color: var(--gold); border: 1px solid rgba(201,168,76,0.2); }
        .badge-claimed { background: rgba(100,180,255,0.15); color: #7ec8e3; border: 1px solid rgba(100,180,255,0.2); }
        .badge-closed { background: rgba(138,155,181,0.15); color: var(--muted); border: 1px solid rgba(138,155,181,0.2); }

        /* ── Item photo thumbnail ── */
        .thumb {
            width: 44px; height: 44px; border-radius: 6px;
            object-fit: cover; border: 1px solid var(--border);
        }
        .no-thumb {
            width: 44px; height: 44px; border-radius: 6px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: var(--muted);
        }

        /* ── Empty State ── */
        .empty {
            text-align: center; padding: 64px 24px; color: var(--muted);
        }
        .empty-icon { font-size: 3rem; margin-bottom: 16px; }
        .empty h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem; color: var(--white); margin-bottom: 8px;
        }
        .empty p { font-size: 0.9rem; font-weight: 300; margin-bottom: 24px; }
        .empty a {
            display: inline-block; padding: 11px 24px;
            background: var(--gold); color: var(--navy);
            border-radius: 7px; text-decoration: none;
            font-weight: 700; font-size: 0.88rem;
        }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 24px 20px; }
            .summary { grid-template-columns: 1fr 1fr; }
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
        <a href="report_found.php" class="menu-item"><span class="menu-icon">📦</span> Report Found Item</a>
        <a href="my_reports.php" class="menu-item active"><span class="menu-icon">📁</span> My Reports</a>
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

    <div class="topbar">
        <div>
            <h1 class="page-title">My Reports</h1>
            <p class="page-sub">All items you have reported as lost or found.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="report_lost.php" class="btn-new">📋 Report Lost</a>
            <a href="report_found.php" class="btn-new" style="background:var(--card);color:var(--white);border:1px solid var(--border);">📦 Report Found</a>
        </div>
    </div>

    <!-- Summary -->
    <div class="summary">
        <div class="sum-card">
            <span class="sum-icon">📊</span>
            <div>
                <div class="sum-value"><?php echo $lost_count + $found_count; ?></div>
                <div class="sum-label">Total Reports</div>
            </div>
        </div>
        <div class="sum-card">
            <span class="sum-icon">📋</span>
            <div>
                <div class="sum-value"><?php echo $lost_count; ?></div>
                <div class="sum-label">Lost Reports</div>
            </div>
        </div>
        <div class="sum-card">
            <span class="sum-icon">📦</span>
            <div>
                <div class="sum-value"><?php echo $found_count; ?></div>
                <div class="sum-label">Found Reports</div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <a href="my_reports.php?filter=all"   class="filter-tab <?php echo $filter==='all'   ? 'active':'' ?>">All</a>
        <a href="my_reports.php?filter=lost"  class="filter-tab <?php echo $filter==='lost'  ? 'active':'' ?>">Lost Only</a>
        <a href="my_reports.php?filter=found" class="filter-tab <?php echo $filter==='found' ? 'active':'' ?>">Found Only</a>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if ($total > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date Reported</th>
                        <th>Date Occurred</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($reports)): ?>
                    <tr>
                        <td>
                            <?php if ($row['item_photo']): ?>
                                <img src="../uploads/items/<?php echo htmlspecialchars($row['item_photo']); ?>"
                                     class="thumb" alt="Item photo">
                            <?php else: ?>
                                <div class="no-thumb">📦</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></div>
                            <div class="item-location">📍 <?php echo htmlspecialchars($row['location']); ?></div>
                        </td>
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
                        <td><?php echo date('d M Y', strtotime($row['date_occurred'])); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <h3>No reports found</h3>
            <p>You have not submitted any <?php echo $filter !== 'all' ? $filter : ''; ?> reports yet.</p>
            <a href="report_lost.php">Report a Lost Item</a>
        </div>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
