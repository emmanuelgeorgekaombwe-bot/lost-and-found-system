<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_name = $_SESSION['full_name'];
$admin_id   = $_SESSION['user_id'];

// Handle delete student
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id !== $admin_id) {
        mysqli_query($conn, "DELETE FROM users WHERE user_id=$del_id AND role='student'");
        $log = "Deleted student user #$del_id";
        mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES ($admin_id, '".mysqli_real_escape_string($conn,$log)."', 'users', $del_id)");
    }
    header('Location: manage_users.php?msg=deleted');
    exit();
}

// Search
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$where   = "WHERE role='student'";
if (!empty($keyword)) {
    $kw     = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (full_name LIKE '%$kw%' OR email LIKE '%$kw%' OR reg_number LIKE '%$kw%')";
}

$users = mysqli_query($conn,
    "SELECT u.*,
        (SELECT COUNT(*) FROM items WHERE user_id=u.user_id AND type='lost')  AS lost_count,
        (SELECT COUNT(*) FROM items WHERE user_id=u.user_id AND type='found') AS found_count,
        (SELECT COUNT(*) FROM claims WHERE claimant_id=u.user_id)             AS claims_count
     FROM users u
     $where
     ORDER BY u.created_at DESC");

$total        = mysqli_num_rows($users);
$total_students = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM users WHERE role='student'"))['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5;--card:rgba(255,255,255,0.04);--border:rgba(255,255,255,0.08);--red:#e05c5c}
        html,body{min-height:100%;font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white)}
        body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 50% at 5% 5%,rgba(26,58,110,0.5) 0%,transparent 55%);pointer-events:none;z-index:0}
        body::after{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(201,168,76,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,0.04) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;z-index:0}

        .sidebar{position:fixed;top:0;left:0;bottom:0;width:240px;background:rgba(10,22,40,0.97);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;z-index:100}
        .sidebar-logo{font-family:'Playfair Display',serif;font-size:1.05rem;font-weight:700;color:var(--gold);padding:0 28px 28px;border-bottom:1px solid var(--border);line-height:1.4}
        .sidebar-logo span{color:var(--white);font-weight:400}
        .sidebar-logo small{display:block;font-family:'DM Sans',sans-serif;font-size:0.7rem;color:var(--muted);font-weight:500;letter-spacing:0.08em;text-transform:uppercase;margin-top:4px}
        .sidebar-menu{flex:1;padding:20px 0}
        .menu-label{font-size:0.68rem;font-weight:600;color:var(--muted);letter-spacing:0.1em;text-transform:uppercase;padding:0 28px;margin-bottom:6px;margin-top:18px}
        .menu-item{display:flex;align-items:center;gap:12px;padding:10px 28px;text-decoration:none;font-size:0.88rem;color:var(--muted);transition:all 0.2s;border-left:3px solid transparent}
        .menu-item:hover{color:var(--white);background:rgba(255,255,255,0.04)}
        .menu-item.active{color:var(--gold);border-left-color:var(--gold);background:rgba(201,168,76,0.06)}
        .menu-icon{font-size:1rem;width:20px;text-align:center}
        .sidebar-footer{padding:20px 28px;border-top:1px solid var(--border)}
        .admin-info{font-size:0.8rem;color:var(--muted);margin-bottom:10px;line-height:1.5}
        .admin-info strong{color:var(--gold);display:block;font-size:0.85rem}
        .btn-logout{display:block;text-align:center;padding:9px;background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.2);border-radius:6px;color:#f08080;text-decoration:none;font-size:0.85rem;font-weight:500;transition:all 0.2s}
        .btn-logout:hover{background:rgba(224,92,92,0.2)}

        .main{margin-left:240px;padding:40px 40px 60px;position:relative;z-index:1}
        .topbar{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:16px}
        .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700}
        .page-sub{font-size:0.88rem;color:var(--muted);margin-top:4px;font-weight:300}

        .alert-success{padding:12px 18px;border-radius:8px;background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97;font-size:0.88rem;margin-bottom:20px}

        .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
        .sum-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px 22px;display:flex;align-items:center;gap:14px}
        .sum-icon{font-size:1.5rem}
        .sum-value{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--gold);line-height:1}
        .sum-label{font-size:0.78rem;color:var(--muted);margin-top:2px}

        .search-bar{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px 20px;margin-bottom:20px}
        .search-row{display:flex;gap:10px;align-items:flex-end}
        .search-input{flex:1;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;padding:10px 16px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--white);outline:none;transition:border-color 0.2s}
        .search-input::placeholder{color:rgba(138,155,181,0.5)}
        .search-input:focus{border-color:rgba(201,168,76,0.5)}
        .btn-search{padding:10px 22px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;border:none;border-radius:7px;cursor:pointer;transition:all 0.2s}
        .btn-search:hover{background:var(--gold2)}
        .btn-reset{padding:10px 16px;background:transparent;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.88rem;border:1px solid var(--border);border-radius:7px;text-decoration:none;transition:all 0.2s}
        .btn-reset:hover{color:var(--white)}

        .results-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
        .results-count{font-size:0.85rem;color:var(--muted)}
        .results-count strong{color:var(--gold)}

        .table-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:0.86rem}
        th{text-align:left;padding:12px 20px;font-size:0.72rem;font-weight:600;color:var(--muted);letter-spacing:0.06em;text-transform:uppercase;border-bottom:1px solid var(--border);background:rgba(255,255,255,0.02)}
        td{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}

        .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--blue,#1a3a6e),var(--gold));display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:700;font-size:0.9rem;color:var(--navy,#0a1628);flex-shrink:0}

        .stat-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:100px;font-size:0.75rem;font-weight:600;background:rgba(255,255,255,0.04);border:1px solid var(--border);color:var(--muted)}

        .btn-delete{padding:5px 12px;border-radius:5px;background:rgba(224,92,92,0.1);color:#f08080;border:1px solid rgba(224,92,92,0.2);font-size:0.75rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-delete:hover{background:rgba(224,92,92,0.2)}

        .empty{text-align:center;padding:56px 24px;color:var(--muted)}
        .empty-icon{font-size:2.5rem;margin-bottom:12px}
        .empty p{font-size:0.88rem;font-weight:300}

        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:24px 20px}.summary{grid-template-columns:1fr 1fr}}
    .logo-watermark{position:fixed;inset:0;background-image:url('../assets/images/College_of_Business_Education.jpg');background-repeat:no-repeat;background-position:center center;background-size:50%;opacity:0.04;pointer-events:none;z-index:0;}
        </style>
</head>
<body>
<div class="logo-watermark"></div>

<aside class="sidebar">
    <div class="sidebar-logo"><img src="../assets/images/College_of_Business_Education.jpg" style="width:36px;height:36px;object-fit:contain;border-radius:4px;background:rgba(255,255,255,0.05);padding:2px;margin-bottom:8px;display:block;" alt="CBE">CBE <span>Lost &amp; Found</span><small>Admin Panel</small></div>
    <nav class="sidebar-menu">
        <p class="menu-label">Overview</p>
        <a href="dashboard.php" class="menu-item"><span class="menu-icon">📊</span> Dashboard</a>
        <p class="menu-label">Manage</p>
        <a href="manage_items.php" class="menu-item"><span class="menu-icon">📦</span> All Items</a>
        <a href="manage_claims.php" class="menu-item"><span class="menu-icon">✅</span> Claims</a>
        <a href="manage_users.php" class="menu-item active"><span class="menu-icon">👥</span> Students</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info"><strong><?php echo htmlspecialchars($admin_name); ?></strong>Administrator</div>
        <a href="../auth/logout.php" class="btn-logout">Sign Out</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h1 class="page-title">Manage Students</h1>
            <p class="page-sub">View all registered students and their activity in the system.</p>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert-success">✔ Student account has been removed from the system.</div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary">
        <div class="sum-card">
            <span class="sum-icon">👥</span>
            <div><div class="sum-value"><?php echo $total_students; ?></div><div class="sum-label">Total Students</div></div>
        </div>
        <div class="sum-card">
            <span class="sum-icon">📋</span>
            <div>
                <div class="sum-value"><?php echo mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM items WHERE type='lost'"))['t']; ?></div>
                <div class="sum-label">Lost Reports</div>
            </div>
        </div>
        <div class="sum-card">
            <span class="sum-icon">📦</span>
            <div>
                <div class="sum-value"><?php echo mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM items WHERE type='found'"))['t']; ?></div>
                <div class="sum-label">Found Reports</div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="search-bar">
        <form method="GET" action="">
            <div class="search-row">
                <input type="text" class="search-input" name="keyword"
                       placeholder="Search by name, email, or registration number..."
                       value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit" class="btn-search">Search</button>
                <a href="manage_users.php" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results count -->
    <div class="results-bar">
        <p class="results-count">Showing <strong><?php echo $total; ?></strong> student<?php echo $total!==1?'s':''; ?></p>
    </div>

    <!-- Table -->
    <div class="table-card">
        <?php if ($total > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Registration No.</th>
                        <th>Email</th>
                        <th>Activity</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($users)):
                    $initials = '';
                    $parts    = explode(' ', $row['full_name']);
                    foreach (array_slice($parts,0,2) as $p) $initials .= strtoupper($p[0]);
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px">
                            <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                            <div>
                                <div style="font-weight:500"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                <div style="font-size:0.75rem;color:var(--muted)">Student</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:0.85rem;color:var(--muted)"><?php echo htmlspecialchars($row['reg_number']); ?></td>
                    <td style="font-size:0.85rem;color:var(--muted)"><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <span class="stat-pill">📋 <?php echo $row['lost_count']; ?> lost</span>
                            <span class="stat-pill">📦 <?php echo $row['found_count']; ?> found</span>
                            <span class="stat-pill">✅ <?php echo $row['claims_count']; ?> claims</span>
                        </div>
                    </td>
                    <td style="font-size:0.82rem;color:var(--muted)"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                    <td>
                        <a href="manage_users.php?delete=<?php echo $row['user_id']; ?>"
                           class="btn-delete"
                           onclick="return confirm('Remove this student account? All their reports will also be deleted.')">
                            Remove
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty">
            <div class="empty-icon">👥</div>
            <p><?php echo !empty($keyword) ? 'No students match your search.' : 'No students registered yet.'; ?></p>
        </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
