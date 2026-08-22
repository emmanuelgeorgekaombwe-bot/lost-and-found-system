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

// Search parameters
$keyword  = isset($_GET['keyword'])  ? trim($_GET['keyword'])       : '';
$type     = isset($_GET['type'])     ? $_GET['type']                : 'all';
$category = isset($_GET['category']) ? intval($_GET['category'])    : 0;

// Build query
$where = "WHERE i.status = 'open'";
if (!empty($keyword)) {
    $kw     = mysqli_real_escape_string($conn, $keyword);
    $where .= " AND (i.item_name LIKE '%$kw%' OR i.description LIKE '%$kw%' OR i.location LIKE '%$kw%' OR i.color LIKE '%$kw%' OR i.brand LIKE '%$kw%')";
}
if ($type === 'lost')  $where .= " AND i.type = 'lost'";
if ($type === 'found') $where .= " AND i.type = 'found'";
if ($category > 0)     $where .= " AND i.category_id = $category";

$results = mysqli_query($conn,
    "SELECT i.*, c.category_name, u.full_name AS reporter_name
     FROM items i
     JOIN categories c ON i.category_id = c.category_id
     JOIN users u ON i.user_id = u.user_id
     $where
     ORDER BY i.created_at DESC");

$total_results = mysqli_num_rows($results);

// Fetch categories for filter
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Items — CBE Lost & Found</title>
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

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem; font-weight: 700; margin-bottom: 4px;
        }
        .page-sub { font-size: 0.88rem; color: var(--muted); font-weight: 300; margin-bottom: 28px; }

        /* ── Search Form ── */
        .search-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 28px 32px;
            margin-bottom: 28px;
        }

        .search-row {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 12px; align-items: end;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }

        label {
            font-size: 0.78rem; font-weight: 600;
            color: var(--muted); letter-spacing: 0.05em; text-transform: uppercase;
        }

        input[type="text"], select {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; padding: 11px 16px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.92rem; color: var(--white);
            outline: none; transition: border-color 0.2s;
            width: 100%;
        }
        input[type="text"]::placeholder { color: rgba(138,155,181,0.5); }
        input[type="text"]:focus, select:focus { border-color: rgba(201,168,76,0.5); }
        select option { background: #0a1628; }

        .btn-search {
            padding: 11px 24px; background: var(--gold); color: var(--navy);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 700;
            border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-search:hover { background: var(--gold2); }

        .btn-reset {
            padding: 11px 18px; background: transparent; color: var(--muted);
            font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
            border: 1px solid var(--border); border-radius: 8px;
            cursor: pointer; text-decoration: none; transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-reset:hover { color: var(--white); border-color: rgba(255,255,255,0.2); }

        /* ── Results header ── */
        .results-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 20px;
        }

        .results-count {
            font-size: 0.88rem; color: var(--muted);
        }
        .results-count strong { color: var(--gold); }

        /* ── Item Grid ── */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .item-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden;
            transition: all 0.25s;
        }
        .item-card:hover {
            border-color: rgba(201,168,76,0.2);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        }

        .item-photo {
            width: 100%; height: 160px; object-fit: cover;
        }

        .item-photo-placeholder {
            width: 100%; height: 160px;
            background: rgba(255,255,255,0.03);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .item-body { padding: 18px 20px; }

        .item-badges {
            display: flex; gap: 6px; margin-bottom: 10px;
        }

        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 100px; font-size: 0.72rem; font-weight: 600;
        }
        .badge-lost { background: rgba(224,92,92,0.15); color: #f08080; border: 1px solid rgba(224,92,92,0.2); }
        .badge-found { background: rgba(76,175,130,0.15); color: #6fcf97; border: 1px solid rgba(76,175,130,0.2); }
        .badge-cat { background: rgba(201,168,76,0.1); color: var(--gold); border: 1px solid rgba(201,168,76,0.15); }

        .item-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem; font-weight: 700;
            margin-bottom: 6px; color: var(--white);
        }

        .item-desc {
            font-size: 0.84rem; color: var(--muted);
            line-height: 1.6; margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-meta {
            font-size: 0.8rem; color: var(--muted);
            display: flex; flex-direction: column; gap: 3px;
        }

        .item-meta span { display: flex; align-items: center; gap: 5px; }

        .item-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }

        .reporter {
            font-size: 0.78rem; color: var(--muted);
        }

        .btn-claim {
            padding: 7px 16px;
            background: rgba(201,168,76,0.12);
            border: 1px solid rgba(201,168,76,0.25);
            color: var(--gold); border-radius: 6px;
            font-size: 0.8rem; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-claim:hover { background: rgba(201,168,76,0.2); }

        /* ── Empty State ── */
        .empty {
            text-align: center; padding: 72px 24px;
            color: var(--muted); grid-column: 1 / -1;
        }
        .empty-icon { font-size: 3rem; margin-bottom: 16px; }
        .empty h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem; color: var(--white); margin-bottom: 8px;
        }
        .empty p { font-size: 0.9rem; font-weight: 300; }

        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main { margin-left: 0; padding: 24px 20px; }
            .search-row { grid-template-columns: 1fr; }
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

<!-- Main Content -->
<main class="main">

    <h1 class="page-title">Search Items</h1>
    <p class="page-sub">Browse all open lost and found reports. Use the filters to narrow your search.</p>

    <!-- Search Form -->
    <div class="search-card">
        <form method="GET" action="">
            <div class="search-row">
                <div class="form-group">
                    <label for="keyword">Search Keyword</label>
                    <input type="text" id="keyword" name="keyword"
                           placeholder="e.g. Samsung phone, blue bag, student ID..."
                           value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="all"   <?php echo $type==='all'   ? 'selected':'' ?>>All Types</option>
                        <option value="lost"  <?php echo $type==='lost'  ? 'selected':'' ?>>Lost Only</option>
                        <option value="found" <?php echo $type==='found' ? 'selected':'' ?>>Found Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="0">All Categories</option>
                        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo $cat['category_id']; ?>"
                            <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn-search">Search</button>
                    <a href="search.php" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Results -->
    <div class="results-header">
        <p class="results-count">
            Showing <strong><?php echo $total_results; ?></strong>
            <?php echo $total_results === 1 ? 'result' : 'results'; ?>
            <?php echo !empty($keyword) ? ' for "<strong>'.htmlspecialchars($keyword).'</strong>"' : ''; ?>
        </p>
    </div>

    <div class="items-grid">
        <?php if ($total_results > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($results)): ?>
            <div class="item-card">

                <?php if ($row['item_photo']): ?>
                    <img src="../uploads/items/<?php echo htmlspecialchars($row['item_photo']); ?>"
                         class="item-photo" alt="<?php echo htmlspecialchars($row['item_name']); ?>">
                <?php else: ?>
                    <div class="item-photo-placeholder">
                        <?php echo $row['type'] === 'lost' ? '🔍' : '📦'; ?>
                    </div>
                <?php endif; ?>

                <div class="item-body">
                    <div class="item-badges">
                        <span class="badge badge-<?php echo $row['type']; ?>"><?php echo ucfirst($row['type']); ?></span>
                        <span class="badge badge-cat"><?php echo htmlspecialchars($row['category_name']); ?></span>
                    </div>
                    <div class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></div>
                    <div class="item-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                    <div class="item-meta">
                        <span>📍 <?php echo htmlspecialchars($row['location']); ?></span>
                        <span>📅 <?php echo date('d M Y', strtotime($row['date_occurred'])); ?></span>
                        <?php if ($row['color']): ?>
                        <span>🎨 <?php echo htmlspecialchars($row['color']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="item-footer">
                    <div class="reporter">
                        Reported by <?php echo htmlspecialchars(explode(' ', $row['reporter_name'])[0]); ?>
                    </div>
                    <?php if ($row['user_id'] != $user_id): ?>
                        <a href="claim.php?item_id=<?php echo $row['item_id']; ?>" class="btn-claim">
                            Claim Item
                        </a>
                    <?php else: ?>
                        <span style="font-size:0.78rem;color:var(--muted);">Your report</span>
                    <?php endif; ?>
                </div>

            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">
                <div class="empty-icon">🔎</div>
                <h3>No items found</h3>
                <p>Try adjusting your search terms or filters, or check back later as new reports are added daily.</p>
            </div>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
