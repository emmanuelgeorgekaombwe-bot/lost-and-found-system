<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$admin_name = $_SESSION['full_name'];
$admin_id   = $_SESSION['user_id'];

// Handle close item action
if (isset($_GET['close'])) {
    $item_id = intval($_GET['close']);
    mysqli_query($conn, "UPDATE items SET status='closed' WHERE item_id=$item_id");
    $log = "Closed item #$item_id";
    mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES ($admin_id, '".mysqli_real_escape_string($conn,$log)."', 'items', $item_id)");
    header('Location: manage_items.php?msg=closed');
    exit();
}

// Handle delete item action
if (isset($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM items WHERE item_id=$item_id");
    $log = "Deleted item #$item_id";
    mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_table, target_id) VALUES ($admin_id, '".mysqli_real_escape_string($conn,$log)."', 'items', $item_id)");
    header('Location: manage_items.php?msg=deleted');
    exit();
}

// Filters
$filter_type   = isset($_GET['type'])     ? $_GET['type']          : 'all';
$filter_status = isset($_GET['status'])   ? $_GET['status']        : 'all';
$keyword       = isset($_GET['keyword'])  ? trim($_GET['keyword'])  : '';

$where = "WHERE 1=1";
if ($filter_type   === 'lost')   $where .= " AND i.type='lost'";
if ($filter_type   === 'found')  $where .= " AND i.type='found'";
if ($filter_status === 'open')   $where .= " AND i.status='open'";
if ($filter_status === 'claimed')$where .= " AND i.status='claimed'";
if ($filter_status === 'closed') $where .= " AND i.status='closed'";
if (!empty($keyword)) {
    $kw = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (i.item_name LIKE '%$kw%' OR i.description LIKE '%$kw%' OR i.location LIKE '%$kw%')";
}

$items = mysqli_query($conn,
    "SELECT i.*, c.category_name, u.full_name AS reporter, u.reg_number, i.contact_phone
     FROM items i
     JOIN categories c ON i.category_id = c.category_id
     JOIN users u ON i.user_id = u.user_id
     $where
     ORDER BY i.created_at DESC");

$total = mysqli_num_rows($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items — CBE Lost & Found</title>
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

        .filter-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px 24px;margin-bottom:24px}
        .filter-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
        .fg{display:flex;flex-direction:column;gap:5px;min-width:160px}
        label{font-size:0.75rem;font-weight:600;color:var(--muted);letter-spacing:0.05em;text-transform:uppercase}
        input[type="text"],select{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:7px;padding:9px 14px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--white);outline:none;transition:border-color 0.2s;width:100%}
        input[type="text"]::placeholder{color:rgba(138,155,181,0.5)}
        input[type="text"]:focus,select:focus{border-color:rgba(201,168,76,0.5)}
        select option{background:#0a1628}
        .btn-filter{padding:9px 20px;background:var(--gold);color:var(--navy);font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;border:none;border-radius:7px;cursor:pointer;transition:all 0.2s}
        .btn-filter:hover{background:var(--gold2)}
        .btn-reset{padding:9px 16px;background:transparent;color:var(--muted);font-family:'DM Sans',sans-serif;font-size:0.88rem;border:1px solid var(--border);border-radius:7px;cursor:pointer;text-decoration:none;transition:all 0.2s}
        .btn-reset:hover{color:var(--white)}

        .results-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        .results-count{font-size:0.85rem;color:var(--muted)}
        .results-count strong{color:var(--gold)}

        .table-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:0.86rem}
        th{text-align:left;padding:12px 18px;font-size:0.72rem;font-weight:600;color:var(--muted);letter-spacing:0.06em;text-transform:uppercase;border-bottom:1px solid var(--border);background:rgba(255,255,255,0.02)}
        td{padding:13px 18px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:rgba(255,255,255,0.02)}

        .thumb{width:40px;height:40px;border-radius:6px;object-fit:cover;border:1px solid var(--border)}
        .no-thumb{width:40px;height:40px;border-radius:6px;background:rgba(255,255,255,0.04);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--muted)}

        .badge{display:inline-block;padding:3px 9px;border-radius:100px;font-size:0.72rem;font-weight:600}
        .badge-lost{background:rgba(224,92,92,0.15);color:#f08080;border:1px solid rgba(224,92,92,0.2)}
        .badge-found{background:rgba(76,175,130,0.15);color:#6fcf97;border:1px solid rgba(76,175,130,0.2)}
        .badge-open{background:rgba(201,168,76,0.15);color:var(--gold);border:1px solid rgba(201,168,76,0.2)}
        .badge-claimed{background:rgba(100,180,255,0.15);color:#7ec8e3;border:1px solid rgba(100,180,255,0.2)}
        .badge-closed{background:rgba(138,155,181,0.15);color:var(--muted);border:1px solid rgba(138,155,181,0.2)}

        .actions{display:flex;gap:6px}
        .btn-close{padding:5px 11px;border-radius:5px;background:rgba(255,167,38,0.1);color:#ffb74d;border:1px solid rgba(255,167,38,0.2);font-size:0.75rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-close:hover{background:rgba(255,167,38,0.2)}
        .btn-delete{padding:5px 11px;border-radius:5px;background:rgba(224,92,92,0.1);color:#f08080;border:1px solid rgba(224,92,92,0.2);font-size:0.75rem;font-weight:600;text-decoration:none;transition:all 0.2s}
        .btn-delete:hover{background:rgba(224,92,92,0.2)}

        .empty{text-align:center;padding:56px 24px;color:var(--muted)}
        .empty-icon{font-size:2.5rem;margin-bottom:12px}
        .empty p{font-size:0.88rem;font-weight:300}

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
        <a href="dashboard.php" class="menu-item"><span class="menu-icon">📊</span> Dashboard</a>
        <p class="menu-label">Manage</p>
        <a href="manage_items.php" class="menu-item active"><span class="menu-icon">📦</span> All Items</a>
        <a href="manage_claims.php" class="menu-item"><span class="menu-icon">✅</span> Claims</a>
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
            <h1 class="page-title">Manage Items</h1>
            <p class="page-sub">View, filter, and manage all lost and found reports.</p>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert-success">
        ✔ Item has been <?php echo $_GET['msg'] === 'closed' ? 'closed' : 'deleted'; ?> successfully.
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="fg" style="flex:1;min-width:200px">
                    <label for="keyword">Search</label>
                    <input type="text" id="keyword" name="keyword" placeholder="Item name, location..."
                           value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
                <div class="fg">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="all"  <?php echo $filter_type==='all'  ?'selected':'' ?>>All Types</option>
                        <option value="lost" <?php echo $filter_type==='lost' ?'selected':'' ?>>Lost</option>
                        <option value="found"<?php echo $filter_type==='found'?'selected':'' ?>>Found</option>
                    </select>
                </div>
                <div class="fg">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all"   <?php echo $filter_status==='all'   ?'selected':'' ?>>All Statuses</option>
                        <option value="open"  <?php echo $filter_status==='open'  ?'selected':'' ?>>Open</option>
                        <option value="claimed"<?php echo $filter_status==='claimed'?'selected':'' ?>>Claimed</option>
                        <option value="closed"<?php echo $filter_status==='closed'?'selected':'' ?>>Closed</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Filter</button>
                <a href="manage_items.php" class="btn-reset">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results -->
    <div class="results-bar">
        <p class="results-count">Showing <strong><?php echo $total; ?></strong> items</p>
    </div>

    <div class="table-card">
        <?php if ($total > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Reporter</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($items)): ?>
                <tr>
                    <td>
                        <?php if ($row['item_photo']): ?>
                            <img src="../uploads/items/<?php echo htmlspecialchars($row['item_photo']); ?>" class="thumb" alt="">
                        <?php else: ?>
                            <div class="no-thumb"><?php echo $row['type']==='lost'?'🔍':'📦'; ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:500"><?php echo htmlspecialchars($row['item_name']); ?></div>
                        <div style="font-size:0.78rem;color:var(--muted)"><?php echo htmlspecialchars($row['category_name']); ?></div>
                    </td>
                    <td><span class="badge badge-<?php echo $row['type']; ?>"><?php echo ucfirst($row['type']); ?></span></td>
                    <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    <td>
                        <div style="font-size:0.85rem"><?php echo htmlspecialchars($row['reporter']); ?></div>
                        <div style="font-size:0.75rem;color:var(--muted)"><?php echo htmlspecialchars($row['reg_number']); ?></div>
                    </td>
                    <td style="font-size:0.82rem;color:var(--muted)"><?php echo htmlspecialchars($row['location']); ?></td>
                    <td style="font-size:0.82rem;color:#c9a84c"><?php echo !empty($row['contact_phone']) ? '📞 '.htmlspecialchars($row['contact_phone']) : '<span style="color:var(--muted)">—</span>'; ?></td>
                    <td style="font-size:0.82rem;color:var(--muted)"><?php echo date('d M Y',strtotime($row['created_at'])); ?></td>
                    <td>
                        <div class="actions">
                            <?php if ($row['status'] === 'open'): ?>
                            <a href="manage_items.php?close=<?php echo $row['item_id']; ?>"
                               class="btn-close"
                               onclick="return confirm('Close this item report?')">Close</a>
                            <?php endif; ?>
                            <a href="manage_items.php?delete=<?php echo $row['item_id']; ?>"
                               class="btn-delete"
                               onclick="return confirm('Delete this item permanently?')">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty">
            <div class="empty-icon">📭</div>
            <p>No items match your filters.</p>
        </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
