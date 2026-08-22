share<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBE Lost & Found System</title>
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
        }

        html, body {
            height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--white);
            overflow-x: hidden;
        }

        /* ── Background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(26,58,110,0.6) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 90%, rgba(201,168,76,0.12) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Grid overlay ── */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(201,168,76,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(201,168,76,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Nav ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 48px;
            background: rgba(10,22,40,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(201,168,76,0.15);
        }

        .nav-logo {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gold);
            letter-spacing: 0.04em;
        }

        .nav-logo span {
            color: var(--white);
            font-weight: 400;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            padding: 8px 20px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-ghost {
            color: var(--muted);
            border: 1px solid rgba(138,155,181,0.3);
        }
        .btn-ghost:hover { color: var(--white); border-color: var(--white); }

        .btn-gold {
            background: var(--gold);
            color: var(--navy);
            font-weight: 700;
        }
        .btn-gold:hover { background: var(--gold2); }

        /* ── Hero ── */
        .hero {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 120px 24px 80px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(201,168,76,0.1);
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 100px;
            padding: 6px 18px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--gold);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 32px;
            animation: fadeUp 0.6s ease both;
        }

        .badge::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--gold);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.8rem, 7vw, 5.5rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -0.02em;
            margin-bottom: 24px;
            animation: fadeUp 0.6s 0.1s ease both;
        }

        h1 em {
            font-style: italic;
            color: var(--gold);
        }

        .subtitle {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: var(--muted);
            font-weight: 300;
            max-width: 520px;
            line-height: 1.7;
            margin-bottom: 52px;
            animation: fadeUp 0.6s 0.2s ease both;
        }

        /* ── CTA Buttons ── */
        .cta-group {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 80px;
            animation: fadeUp 0.6s 0.3s ease both;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 16px 32px;
            border-radius: 6px;
            transition: all 0.25s;
            letter-spacing: 0.02em;
        }

        .cta-primary {
            background: var(--gold);
            color: var(--navy);
        }
        .cta-primary:hover {
            background: var(--gold2);
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(201,168,76,0.25);
        }

        .cta-secondary {
            background: rgba(255,255,255,0.06);
            color: var(--white);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .cta-secondary:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .icon { font-size: 1.1rem; }

        /* ── Stats ── */
        .stats {
            display: flex;
            gap: 48px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp 0.6s 0.4s ease both;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--gold);
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 500;
        }

        .stat-divider {
            width: 1px;
            background: rgba(255,255,255,0.1);
            align-self: stretch;
        }

        /* ── Features ── */
        .features {
            position: relative;
            z-index: 1;
            padding: 80px 48px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .section-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gold);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 16px;
            text-align: center;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 700;
            text-align: center;
            margin-bottom: 56px;
            color: var(--white);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 36px 32px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(201,168,76,0.2);
            transform: translateY(-4px);
        }

        .card:hover::before { opacity: 1; }

        .card-icon {
            font-size: 2rem;
            margin-bottom: 20px;
            display: block;
        }

        .card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--white);
        }

        .card p {
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.7;
            font-weight: 300;
        }

        /* ── How It Works ── */
        .how {
            position: relative;
            z-index: 1;
            padding: 80px 48px;
            max-width: 900px;
            margin: 0 auto;
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .step {
            display: flex;
            gap: 32px;
            align-items: flex-start;
            position: relative;
            padding-bottom: 40px;
        }

        .step:last-child { padding-bottom: 0; }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 19px;
            top: 48px;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, rgba(201,168,76,0.4), transparent);
        }

        .step-num {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(201,168,76,0.15);
            border: 1px solid rgba(201,168,76,0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            color: var(--gold);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .step-content h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--white);
        }

        .step-content p {
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.7;
            font-weight: 300;
        }

        /* ── Footer ── */
        footer {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 40px 24px;
            border-top: 1px solid rgba(255,255,255,0.06);
            color: var(--muted);
            font-size: 0.85rem;
        }

        footer strong { color: var(--gold); }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 640px) {
            nav { padding: 16px 20px; }
            .nav-logo { font-size: 1rem; }
            .features, .how { padding: 60px 20px; }
            .stats { gap: 28px; }
            .stat-divider { display: none; }
            .cta-group { flex-direction: column; align-items: center; }
            .cta-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav>
    <div class="nav-logo">CBE <span>Lost & Found</span></div>
    <div class="nav-links">
        <a href="auth/login.php" class="btn-ghost">Sign In</a>
        <a href="auth/register.php" class="btn-gold">Register</a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="badge">College of Business Education — Dar es Salaam</div>

    <h1>Lost Something?<br>We'll Help You<br><em>Find It.</em></h1>

    <p class="subtitle">
        A centralized platform for CBE students to report lost items,
        post found belongings, and reconnect what was separated.
    </p>

    <div class="cta-group">
        <a href="auth/register.php?type=lost" class="cta-btn cta-primary">
            <span class="icon">🔍</span> Report Lost Item
        </a>
        <a href="auth/register.php?type=found" class="cta-btn cta-secondary">
            <span class="icon">📦</span> Report Found Item
        </a>
    </div>

    <div class="stats">
        <div class="stat">
            <span class="stat-number">Fast</span>
            <span class="stat-label">Reporting</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
            <span class="stat-number">Secure</span>
            <span class="stat-label">& Private</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
            <span class="stat-number">Free</span>
            <span class="stat-label">For All Students</span>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features">
    <p class="section-label">What We Offer</p>
    <h2 class="section-title">Everything You Need to<br>Recover What Matters</h2>

    <div class="cards">
        <div class="card">
            <span class="card-icon">📋</span>
            <h3>Report Lost Items</h3>
            <p>Quickly submit a report for anything you have lost on campus — phones, ID cards, books, keys, and more.</p>
        </div>
        <div class="card">
            <span class="card-icon">🤝</span>
            <h3>Post Found Items</h3>
            <p>Found something that does not belong to you? Post it here so the rightful owner can claim it easily.</p>
        </div>
        <div class="card">
            <span class="card-icon">🔎</span>
            <h3>Search & Browse</h3>
            <p>Search through all reported items by name, category, or date to find what you are looking for.</p>
        </div>
        <div class="card">
            <span class="card-icon">✅</span>
            <h3>Claim Your Item</h3>
            <p>Submit proof of ownership and claim your item through a simple verified process managed by admin.</p>
        </div>
        <div class="card">
            <span class="card-icon">🔔</span>
            <h3>Get Notified</h3>
            <p>Receive instant notifications when your claim is approved, rejected, or when a matching item is found.</p>
        </div>
        <div class="card">
            <span class="card-icon">🛡️</span>
            <h3>Admin Oversight</h3>
            <p>Every claim is reviewed by a CBE administrator to ensure fairness, accuracy, and student safety.</p>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how">
    <p class="section-label">The Process</p>
    <h2 class="section-title">How It Works</h2>

    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <div class="step-content">
                <h4>Create Your Account</h4>
                <p>Register using your CBE student registration number and email address. It takes less than a minute.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div class="step-content">
                <h4>Submit a Report</h4>
                <p>Fill in the details of your lost or found item — description, location, date, and an optional photo.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div class="step-content">
                <h4>Search & Match</h4>
                <p>Browse reported items or wait for the system to notify you when a match is found for your report.</p>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div class="step-content">
                <h4>Claim & Collect</h4>
                <p>Submit your claim with proof of ownership. Once approved by admin, arrange to collect your item on campus.</p>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <p>&copy; <?php echo date('Y'); ?> <strong>CBE Lost & Found System</strong> &mdash; College of Business Education, Dar es Salaam</p>
    <p style="margin-top:8px;">Built by: Elisha Paul Lubida &bull; Emmanuel George Kaombwe &bull; Aneth Assa Ngonile</p>
</footer>

</body>
</html>
