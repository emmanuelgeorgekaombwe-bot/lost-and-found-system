<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_name    = $_SESSION['full_name'];
$total_users   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM users WHERE role='student'"))['t'];
$total_lost    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM items WHERE type='lost'"))['t'];
$total_found   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM items WHERE type='found'"))['t'];
$total_claims  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM claims WHERE status='pending'"))['t'];
$total_claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM items WHERE status='claimed'"))['t'];
$total_open    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM items WHERE status='open'"))['t'];

$recent_items = mysqli_query($conn,
    "SELECT i.*, c.category_name, u.full_name AS reporter
     FROM items i
     JOIN categories c ON i.category_id = c.category_id
     JOIN users u ON i.user_id = u.user_id
     ORDER BY i.created_at DESC LIMIT 6");

$pending_claims = mysqli_query($conn,
    "SELECT cl.*, i.item_name,
            u.full_name AS claimant_name, u.reg_number
     FROM claims cl
     JOIN items i ON cl.item_id = i.item_id
     JOIN users u ON cl.claimant_id = u.user_id
     WHERE cl.status = 'pending'
     ORDER BY cl.claimed_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5;--card:rgba(255,255,255,0.04);--border:rgba(255,255,255,0.08);--red:#e05c5c;--green:#4caf82}
        html,body{min-height:100%;font-family:'DM Sans',sans-serif;background:var(--navy);color:var(--white)}
        body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 50% at 5% 5%,rgba(26,58,110,0.55) 0%,transparent 55%);pointer-events:none;z-index:0}
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
        .menu-badge{margin-left:auto;background:var(--red);color:#fff;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:100px}
        .sidebar-footer{padding:20px 28px;border-top:1px solid var(--border)}
        .admin-info{font-size:0.8rem;color:var(--muted);margin-bottom:10px;line-height:1.5}
        .admin-info strong{color:var(--gold);display:block;font-size:0.85rem}
        .btn-logout{display:block;text-align:center;padding:9px;background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.2);border-radius:6px;color:#f08080;text-decoration:none;font-size:0.85rem;font-weight:500;transition:all 0.2s}
        .btn-logout:hover{background:rgba(224,92,92,0.2)}

        .main{margin-left:240px;padding:40px 40px 60px;position:relative;z-index:1}
        .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:36px}
        .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700}
        .page-sub{font-size:0.88rem;color:var(--muted);margin-top:4px;font-weight:300}
        .admin-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(201,168,76,0.1);border:1px solid rgba(201,168,76,0.25);border-radius:100px;padding:6px 14px;font-size:0.78rem;font-weight:600;color:var(--gold)}

        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:32px}
        .stat-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:22px 24px;position:relative;overflow:hidden;transition:transform 0.2s}
        .stat-card:hover{transform:translateY(-2px)}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
        .stat-card.gold::before{background:linear-gradient(90deg,transparent,var(--gold),transparent)}
        .stat-card.red::before{background:linear-gradient(90deg,transparent,var(--red),transparent)}
        .stat-card.green::before{background:linear-gradient(90deg,transparent,var(--green),transparent)}
        .stat-card.blue::before{background:linear-gradient(90deg,transparent,#7ec8e3,transparent)}
        .stat-card.purple::before{background:linear-gradient(90deg,transparent,#b39ddb,transparent)}
        .stat-card.teal::before{background:linear-gradient(90deg,transparent,#4dd0e1,transparent)}
        .stat-icon{font-size:1.4rem;margin-bottom:14px;display:block}
        .stat-value{font-family:'Playfair Display',serif;font-size:2.4rem;font-weight:700;line-height:1;margin-bottom:4px}
        .stat-card.gold .stat-value{color:var(--gold)}
        .stat-card.red .stat-value{color:#f08080}
        .stat-card.green .stat-value{color:#6fcf97}
        .stat-card.blue .stat-value{color:#7ec8e3}
        .stat-card.purple .stat-value{color:#b39ddb}
        .stat-card.teal .stat-value{color:#4dd0e1}
        .stat-label{font-size:0.82rem;color:var(--muted)}

        .two-col{display:grid;grid-template-columns:1fr 360px;gap:24px}
        .section-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
        .section-header{display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border)}
        .section-title{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700}
        .section-link{font-size:0.82rem;color:var(--gold);text-decoration:none}
        .section-link:hover{text-decoration:underline}

        table{width:100%;border-collapse:collapse;font-size:0.86rem}
        th{text-align:left;padding:12px 20px;font-size:0.72rem;font-weight:600;color:var(--muted);letter-spacing:0.06em;text-transform:uppercase;border-bottom:1px solid var(--border);background:rgba(255,255,255,0.02)}
        td{padding:13px 20px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}

        .badge{display:inline-block;padding:3px 9px;border-radius:100px;font-size:0.72rem;font-weight:600}
        .badge-lost{background:rgba(224,92,92,0.15);color:#f08080;border:1px solid rgba(224,92,92,0.2)}
        .badge-found{background:rgba(76,175,130,0.15);color:#6fcf97;border:1px solid rgba(76,175,130,0.2)}
        .badge-open{background:rgba(201,168,76,0.15);color:var(--gold);border:1px solid rgba(201,168,76,0.2)}
        .badge-claimed{background:rgba(100,180,255,0.15);color:#7ec8e3;border:1px solid rgba(100,180,255,0.2)}
        .badge-pending{background:rgba(255,167,38,0.15);color:#ffb74d;border:1px solid rgba(255,167,38,0.2)}

        .btn-approve{padding:5px 12px;border-radius:5px;background:rgba(76,175,130,0.12);color:#6fcf97;border:1px solid rgba(76,175,130,0.25);font-size:0.78rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-approve:hover{background:rgba(76,175,130,0.22)}
        .btn-reject{padding:5px 12px;border-radius:5px;background:rgba(224,92,92,0.1);color:#f08080;border:1px solid rgba(224,92,92,0.2);font-size:0.78rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-reject:hover{background:rgba(224,92,92,0.2)}

        .empty{text-align:center;padding:40px 20px;color:var(--muted)}
        .empty-icon{font-size:2rem;margin-bottom:8px}
        .empty p{font-size:0.85rem;font-weight:300}

        .quick-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:20px}
        .quick-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:20px 12px;border-radius:10px;text-decoration:none;transition:all 0.2s;background:rgba(255,255,255,0.03);border:1px solid var(--border);text-align:center}
        .quick-btn:hover{background:rgba(255,255,255,0.07);transform:translateY(-2px)}
        .quick-btn-icon{font-size:1.6rem}
        .quick-btn-label{font-size:0.8rem;font-weight:500;color:var(--muted)}

        @media(max-width:1024px){.two-col{grid-template-columns:1fr}.stats-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:24px 20px}}
    .logo-watermark{position:fixed;inset:0;background-image:url('../assets/images/College_of_Business_Education.jpg');background-repeat:no-repeat;background-position:center center;background-size:50%;opacity:0.04;pointer-events:none;z-index:0;}
        </style>
</head>
<body>
<div class="logo-watermark"></div>

<aside class="sidebar">
    <div class="sidebar-logo"><img src="../assets/images/College_of_Business_Education.jpg" style="width:36px;height:36px;object-fit:contain;border-radius:4px;background:rgba(255,255,255,0.05);padding:2px;margin-bottom:8px;display:block;" alt="CBE">CBE <span>Lost &amp; Found</span><small>Admin Panel</small></div>
    <nav class="sidebar-menu">
        <p class="menu-label">Overview</p>
        <a href="dashboard.php" class="menu-item active"><span class="menu-icon">📊</span> Dashboard</a>
        <p class="menu-label">Manage</p>
        <a href="manage_items.php" class="menu-item"><span class="menu-icon">📦</span> All Items</a>
        <a href="manage_claims.php" class="menu-item">
            <span class="menu-icon">✅</span> Claims
            <?php if ($total_claims > 0): ?><span class="menu-badge"><?php echo $total_claims; ?></span><?php endif; ?>
        </a>
        <a href="manage_users.php" class="menu-item"><span class="menu-icon">👥</span> Students</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info"><strong><?php echo htmlspecialchars($admin_name); ?></strong>Administrator</div>
        <a href="../auth/logout.php" class="btn-logout">Sign Out</a>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <div>
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="page-sub">Overview of the CBE Lost & Found system.</p>
        </div>
        <div class="admin-badge">🛡️ Administrator</div>
    </div>

    <div class="stats-grid">
        <div class="stat-card gold"><span class="stat-icon">👥</span><div class="stat-value"><?php echo $total_users; ?></div><div class="stat-label">Registered Students</div></div>
        <div class="stat-card red"><span class="stat-icon">📋</span><div class="stat-value"><?php echo $total_lost; ?></div><div class="stat-label">Lost Item Reports</div></div>
        <div class="stat-card green"><span class="stat-icon">📦</span><div class="stat-value"><?php echo $total_found; ?></div><div class="stat-label">Found Item Reports</div></div>
        <div class="stat-card blue"><span class="stat-icon">⏳</span><div class="stat-value"><?php echo $total_claims; ?></div><div class="stat-label">Pending Claims</div></div>
        <div class="stat-card purple"><span class="stat-icon">✅</span><div class="stat-value"><?php echo $total_claimed; ?></div><div class="stat-label">Successfully Claimed</div></div>
        <div class="stat-card teal"><span class="stat-icon">🔓</span><div class="stat-value"><?php echo $total_open; ?></div><div class="stat-label">Open Reports</div></div>
    </div>

    <div class="two-col">
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title">Recent Item Reports</h2>
                <a href="manage_items.php" class="section-link">View all →</a>
            </div>
            <?php if (mysqli_num_rows($recent_items) > 0): ?>
            <table>
                <thead><tr><th>Item</th><th>Type</th><th>Status</th><th>Reporter</th><th>Date</th></tr></thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($recent_items)): ?>
                <tr>
                    <td><div style="font-weight:500"><?php echo htmlspecialchars($row['item_name']); ?></div><div style="font-size:0.78rem;color:var(--muted)"><?php echo htmlspecialchars($row['category_name']); ?></div></td>
                    <td><span class="badge badge-<?php echo $row['type']; ?>"><?php echo ucfirst($row['type']); ?></span></td>
                    <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    <td style="font-size:0.82rem"><?php echo htmlspecialchars(explode(' ',$row['reporter'])[0]); ?></td>
                    <td style="font-size:0.82rem;color:var(--muted)"><?php echo date('d M Y',strtotime($row['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty"><div class="empty-icon">📭</div><p>No items reported yet.</p></div>
            <?php endif; ?>
        </div>

        <div style="display:flex;flex-direction:column;gap:24px">
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">Pending Claims</h2>
                    <a href="manage_claims.php" class="section-link">View all →</a>
                </div>
                <?php if (mysqli_num_rows($pending_claims) > 0): ?>
                <?php while ($cl = mysqli_fetch_assoc($pending_claims)): ?>
                <div style="padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04)">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                        <div>
                            <div style="font-size:0.88rem;font-weight:500"><?php echo htmlspecialchars($cl['item_name']); ?></div>
                            <div style="font-size:0.78rem;color:var(--muted);margin-top:2px">by <?php echo htmlspecialchars($cl['claimant_name']); ?> &bull; <?php echo htmlspecialchars($cl['reg_number']); ?></div>
                        </div>
                        <span class="badge badge-pending">Pending</span>
                    </div>
                    <div style="display:flex;gap:6px;margin-top:10px">
                        <a href="manage_claims.php?approve=<?php echo $cl['claim_id']; ?>" class="btn-approve">✔ Approve</a>
                        <a href="manage_claims.php?reject=<?php echo $cl['claim_id']; ?>" class="btn-reject">✖ Reject</a>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty"><div class="empty-icon">✅</div><p>No pending claims.</p></div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <div class="section-header"><h2 class="section-title">Quick Actions</h2></div>
                <div class="quick-actions">
                    <a href="manage_items.php" class="quick-btn"><span class="quick-btn-icon">📦</span><span class="quick-btn-label">Manage Items</span></a>
                    <a href="manage_claims.php" class="quick-btn"><span class="quick-btn-icon">✅</span><span class="quick-btn-label">Review Claims</span></a>
                    <a href="manage_users.php" class="quick-btn"><span class="quick-btn-icon">👥</span><span class="quick-btn-label">View Students</span></a>
                    <a href="../auth/logout.php" class="quick-btn"><span class="quick-btn-icon">🚪</span><span class="quick-btn-label">Sign Out</span></a>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
