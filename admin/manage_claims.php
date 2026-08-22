<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_name = $_SESSION['full_name'];
$admin_id   = $_SESSION['user_id'];

// Handle approve
if (isset($_GET['approve'])) {
    $claim_id = intval($_GET['approve']);
    $claim    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM claims WHERE claim_id=$claim_id"));
    if ($claim) {
        mysqli_query($conn, "UPDATE claims SET status='approved', reviewed_at=NOW() WHERE claim_id=$claim_id");
        mysqli_query($conn, "UPDATE items SET status='claimed' WHERE item_id=".$claim['item_id']);
        $msg = "Claim #$claim_id approved. Item marked as claimed.";
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES (".$claim['claimant_id'].", 'Your claim has been APPROVED. Please collect your item from the CBE admin office.')");
        mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES ($admin_id, '".mysqli_real_escape_string($conn,$msg)."', 'claims', $claim_id)");
    }
    header('Location: manage_claims.php?msg=approved');
    exit();
}

// Handle reject
if (isset($_GET['reject'])) {
    $claim_id = intval($_GET['reject']);
    $claim    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM claims WHERE claim_id=$claim_id"));
    if ($claim) {
        mysqli_query($conn, "UPDATE claims SET status='rejected', reviewed_at=NOW() WHERE claim_id=$claim_id");
        $msg = "Claim #$claim_id rejected.";
        mysqli_query($conn, "INSERT INTO notifications (user_id, message) VALUES (".$claim['claimant_id'].", 'Your claim has been REJECTED. Please contact the admin office if you believe this is an error.')");
        mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES ($admin_id, '".mysqli_real_escape_string($conn,$msg)."', 'claims', $claim_id)");
    }
    header('Location: manage_claims.php?msg=rejected');
    exit();
}

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$where  = "WHERE 1=1";
if ($filter === 'pending')  $where .= " AND cl.status='pending'";
if ($filter === 'approved') $where .= " AND cl.status='approved'";
if ($filter === 'rejected') $where .= " AND cl.status='rejected'";

$claims = mysqli_query($conn,
    "SELECT cl.*, i.item_name, i.type AS item_type, i.location AS item_location,
            i.description AS item_desc, i.item_photo,
            u.full_name AS claimant_name, u.reg_number, u.email, cl.contact_phone
     FROM claims cl
     JOIN items i ON cl.item_id = i.item_id
     JOIN users u ON cl.claimant_id = u.user_id
     $where
     ORDER BY cl.claimed_at DESC");

$total         = mysqli_num_rows($claims);
$count_pending  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM claims WHERE status='pending'"))['t'];
$count_approved = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM claims WHERE status='approved'"))['t'];
$count_rejected = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS t FROM claims WHERE status='rejected'"))['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims — CBE Lost & Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{--navy:#0a1628;--gold:#c9a84c;--gold2:#e8c97a;--white:#f7f4ef;--muted:#8a9bb5;--card:rgba(255,255,255,0.04);--border:rgba(255,255,255,0.08);--red:#e05c5c;--green:#4caf82}
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
        .menu-badge{margin-left:auto;background:var(--red);color:#fff;font-size:0.68rem;font-weight:700;padding:2px 7px;border-radius:100px}
        .sidebar-footer{padding:20px 28px;border-top:1px solid var(--border)}
        .admin-info{font-size:0.8rem;color:var(--muted);margin-bottom:10px;line-height:1.5}
        .admin-info strong{color:var(--gold);display:block;font-size:0.85rem}
        .btn-logout{display:block;text-align:center;padding:9px;background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.2);border-radius:6px;color:#f08080;text-decoration:none;font-size:0.85rem;font-weight:500;transition:all 0.2s}
        .btn-logout:hover{background:rgba(224,92,92,0.2)}

        .main{margin-left:240px;padding:40px 40px 60px;position:relative;z-index:1}
        .page-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;margin-bottom:4px}
        .page-sub{font-size:0.88rem;color:var(--muted);font-weight:300;margin-bottom:28px}

        .alert{padding:12px 18px;border-radius:8px;font-size:0.88rem;margin-bottom:20px}
        .alert-success{background:rgba(76,175,130,0.1);border:1px solid rgba(76,175,130,0.3);color:#6fcf97}
        .alert-warning{background:rgba(224,92,92,0.1);border:1px solid rgba(224,92,92,0.3);color:#f08080}

        .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
        .sum-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:18px 22px;display:flex;align-items:center;gap:14px}
        .sum-icon{font-size:1.5rem}
        .sum-value{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;line-height:1}
        .sum-label{font-size:0.78rem;color:var(--muted);margin-top:2px}
        .sum-card.pending .sum-value{color:#ffb74d}
        .sum-card.approved .sum-value{color:#6fcf97}
        .sum-card.rejected .sum-value{color:#f08080}

        .filter-bar{display:flex;gap:8px;margin-bottom:20px}
        .filter-tab{padding:8px 20px;border-radius:6px;text-decoration:none;font-size:0.85rem;font-weight:500;transition:all 0.2s;border:1px solid var(--border);color:var(--muted)}
        .filter-tab:hover{color:var(--white)}
        .filter-tab.active{background:rgba(201,168,76,0.12);border-color:rgba(201,168,76,0.3);color:var(--gold)}

        .claims-list{display:flex;flex-direction:column;gap:16px}

        .claim-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:border-color 0.2s}
        .claim-card:hover{border-color:rgba(201,168,76,0.15)}

        .claim-header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
        .claim-id{font-size:0.75rem;color:var(--muted);font-weight:500}
        .claim-title{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-top:2px}

        .claim-body{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;padding:0}
        .claim-section{padding:18px 24px;border-right:1px solid var(--border)}
        .claim-section:last-child{border-right:none}
        .cs-label{font-size:0.72rem;font-weight:600;color:var(--muted);letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px}
        .cs-value{font-size:0.88rem;color:var(--white);line-height:1.6}
        .cs-value.muted{color:var(--muted)}

        .claim-footer{padding:14px 24px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
        .claim-time{font-size:0.78rem;color:var(--muted)}

        .action-btns{display:flex;gap:8px}
        .btn-approve{padding:8px 18px;border-radius:6px;background:rgba(76,175,130,0.12);color:#6fcf97;border:1px solid rgba(76,175,130,0.25);font-size:0.85rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-approve:hover{background:rgba(76,175,130,0.22)}
        .btn-reject{padding:8px 18px;border-radius:6px;background:rgba(224,92,92,0.1);color:#f08080;border:1px solid rgba(224,92,92,0.2);font-size:0.85rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-reject:hover{background:rgba(224,92,92,0.2)}

        .badge{display:inline-block;padding:4px 11px;border-radius:100px;font-size:0.75rem;font-weight:600}
        .badge-lost{background:rgba(224,92,92,0.15);color:#f08080;border:1px solid rgba(224,92,92,0.2)}
        .badge-found{background:rgba(76,175,130,0.15);color:#6fcf97;border:1px solid rgba(76,175,130,0.2)}
        .badge-pending{background:rgba(255,167,38,0.15);color:#ffb74d;border:1px solid rgba(255,167,38,0.2)}
        .badge-approved{background:rgba(76,175,130,0.15);color:#6fcf97;border:1px solid rgba(76,175,130,0.2)}
        .badge-rejected{background:rgba(224,92,92,0.15);color:#f08080;border:1px solid rgba(224,92,92,0.2)}

        .empty{text-align:center;padding:60px 24px;color:var(--muted);background:var(--card);border:1px solid var(--border);border-radius:12px}
        .empty-icon{font-size:2.5rem;margin-bottom:12px}
        .empty h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--white);margin-bottom:8px}
        .empty p{font-size:0.88rem;font-weight:300}

        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:24px 20px}.claim-body{grid-template-columns:1fr}.claim-section{border-right:none;border-bottom:1px solid var(--border)}.summary{grid-template-columns:1fr}}
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
        <a href="manage_claims.php" class="menu-item active">
            <span class="menu-icon">✅</span> Claims
            <?php if ($count_pending > 0): ?><span class="menu-badge"><?php echo $count_pending; ?></span><?php endif; ?>
        </a>
        <a href="manage_users.php" class="menu-item"><span class="menu-icon">👥</span> Students</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info"><strong><?php echo htmlspecialchars($admin_name); ?></strong>Administrator</div>
        <a href="../auth/logout.php" class="btn-logout">Sign Out</a>
    </div>
</aside>

<main class="main">
    <h1 class="page-title">Manage Claims</h1>
    <p class="page-sub">Review, approve, or reject student claims for lost and found items.</p>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert <?php echo $_GET['msg']==='approved' ? 'alert-success' : 'alert-warning'; ?>">
        <?php if ($_GET['msg']==='approved'): ?>
            ✔ Claim approved. The student has been notified and the item is marked as claimed.
        <?php else: ?>
            ✖ Claim rejected. The student has been notified.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary">
        <div class="sum-card pending">
            <span class="sum-icon">⏳</span>
            <div><div class="sum-value"><?php echo $count_pending; ?></div><div class="sum-label">Pending</div></div>
        </div>
        <div class="sum-card approved">
            <span class="sum-icon">✅</span>
            <div><div class="sum-value"><?php echo $count_approved; ?></div><div class="sum-label">Approved</div></div>
        </div>
        <div class="sum-card rejected">
            <span class="sum-icon">❌</span>
            <div><div class="sum-value"><?php echo $count_rejected; ?></div><div class="sum-label">Rejected</div></div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <a href="?filter=pending"  class="filter-tab <?php echo $filter==='pending' ?'active':'' ?>">⏳ Pending (<?php echo $count_pending; ?>)</a>
        <a href="?filter=approved" class="filter-tab <?php echo $filter==='approved'?'active':'' ?>">✅ Approved</a>
        <a href="?filter=rejected" class="filter-tab <?php echo $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
        <a href="?filter=all"      class="filter-tab <?php echo $filter==='all'     ?'active':'' ?>">All Claims</a>
    </div>

    <!-- Claims List -->
    <?php if ($total > 0): ?>
    <div class="claims-list">
        <?php while ($cl = mysqli_fetch_assoc($claims)): ?>
        <div class="claim-card">
            <div class="claim-header">
                <div>
                    <div class="claim-id">Claim #<?php echo $cl['claim_id']; ?> &bull; <?php echo date('d M Y, H:i', strtotime($cl['claimed_at'])); ?></div>
                    <div class="claim-title"><?php echo htmlspecialchars($cl['item_name']); ?></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span class="badge badge-<?php echo $cl['item_type']; ?>"><?php echo ucfirst($cl['item_type']); ?></span>
                    <span class="badge badge-<?php echo $cl['status']; ?>"><?php echo ucfirst($cl['status']); ?></span>
                </div>
            </div>

            <div class="claim-body">
                <div class="claim-section">
                    <div class="cs-label">Claimant</div>
                    <div class="cs-value"><?php echo htmlspecialchars($cl['claimant_name']); ?></div>
                    <div class="cs-value muted"><?php echo htmlspecialchars($cl['reg_number']); ?></div>
                    <div class="cs-value muted"><?php echo htmlspecialchars($cl['email']); ?></div>
                    <?php if (!empty($cl['contact_phone'])): ?>
                    <div class="cs-value" style="color:#c9a84c;margin-top:4px">📞 <?php echo htmlspecialchars($cl['contact_phone']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="claim-section">
                    <div class="cs-label">Item Location</div>
                    <div class="cs-value"><?php echo htmlspecialchars($cl['item_location']); ?></div>
                    <div class="cs-label" style="margin-top:12px">Item Description</div>
                    <div class="cs-value muted"><?php echo htmlspecialchars(substr($cl['item_desc'],0,120)).(strlen($cl['item_desc'])>120?'...':''); ?></div>
                </div>
                <div class="claim-section">
                    <div class="cs-label">Proof of Ownership</div>
                    <div class="cs-value"><?php echo htmlspecialchars($cl['proof_details']); ?></div>
                    <?php if ($cl['proof_photo']): ?>
                    <div style="margin-top:10px">
                        <img src="../uploads/items/<?php echo htmlspecialchars($cl['proof_photo']); ?>"
                             style="width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border)" alt="Proof">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="claim-footer">
                <div class="claim-time">Submitted <?php echo date('d M Y \a\t H:i', strtotime($cl['claimed_at'])); ?></div>
                <?php if ($cl['status'] === 'pending'): ?>
                <div class="action-btns">
                    <a href="manage_claims.php?approve=<?php echo $cl['claim_id']; ?>&filter=<?php echo $filter; ?>"
                       class="btn-approve"
                       onclick="return confirm('Approve this claim? The item will be marked as claimed.')">
                        ✔ Approve Claim
                    </a>
                    <a href="manage_claims.php?reject=<?php echo $cl['claim_id']; ?>&filter=<?php echo $filter; ?>"
                       class="btn-reject"
                       onclick="return confirm('Reject this claim?')">
                        ✖ Reject Claim
                    </a>
                </div>
                <?php else: ?>
                <div style="font-size:0.82rem;color:var(--muted)">
                    Reviewed <?php echo $cl['reviewed_at'] ? date('d M Y', strtotime($cl['reviewed_at'])) : '—'; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php else: ?>
    <div class="empty">
        <div class="empty-icon"><?php echo $filter==='pending'?'✅':'📭'; ?></div>
        <h3><?php echo $filter==='pending'?'No pending claims':'No claims found'; ?></h3>
        <p><?php echo $filter==='pending'?'All claims have been reviewed. Check back later.':'No claims match this filter.'; ?></p>
    </div>
    <?php endif; ?>

</main>

</body>
</html>
