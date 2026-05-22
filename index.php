<?php session_start(); if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteSwap — Share Notes, Ace Your Semester</title>
    <!-- ANTI-FLASH: runs before page renders -->
    <script>
        const t = localStorage.getItem('theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    </script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .landing { background: var(--bg-color); min-height: 100vh; }

        .land-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 48px;
            border-bottom: 1.5px solid var(--border);
            background: var(--bg-color);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
        }

        .land-nav-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 22px;
    font-weight: 800;
    text-decoration: none;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #7c3aed, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

        .land-nav-links { display: flex; gap: 12px; align-items: center; }
        .land-nav-links .btn { width: auto; padding: 9px 22px; }

        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 90px 20px 60px;
            position: relative;
            overflow: hidden;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
            padding: 6px 16px;
            border-radius: 50px;
            margin-bottom: 24px;
            animation: slideUp 0.5s ease;
        }

        .hero h1 {
            font-size: clamp(32px, 6vw, 58px);
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.15;
            max-width: 700px;
            margin-bottom: 20px;
            animation: slideUp 0.5s ease 0.1s both;
        }

        .hero h1 span { color: var(--primary); }
        .hero h1 em { color: var(--accent); font-style: normal; }

        .hero p {
            font-size: 17px;
            color: var(--text-mid);
            max-width: 520px;
            margin-bottom: 36px;
            line-height: 1.7;
            animation: slideUp 0.5s ease 0.2s both;
        }

        .hero-btns {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: center;
            animation: slideUp 0.5s ease 0.3s both;
        }

        .hero-btns .btn {
            width: auto;
            padding: 14px 32px;
            font-size: 15px;
        }

        .hero-img {
            margin-top: 64px;
            width: 100%;
            max-width: 800px;
            background: var(--bg-secondary);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.6s ease 0.4s both;
        }

        .mock-bar {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
        }

        .mock-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
        }

        .mock-dot:nth-child(1) { background: #fc5c65; }
        .mock-dot:nth-child(2) { background: #fed330; }
        .mock-dot:nth-child(3) { background: #26de81; }

        .mock-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .mock-card {
            background: var(--bg-color);
            border-radius: var(--radius-sm);
            padding: 14px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .mock-tag {
            height: 8px;
            background: var(--primary-light);
            border-radius: 4px;
            width: 60%;
            margin-bottom: 8px;
        }

        .mock-line {
            height: 6px;
            background: var(--border);
            border-radius: 4px;
            margin-bottom: 6px;
        }

        .mock-line.short { width: 70%; }

        .mock-btn {
            height: 8px;
            background: var(--accent-light);
            border-radius: 4px;
            width: 40%;
            margin-top: 10px;
        }

        /* FEATURES */
        .features {
            padding: 80px 20px;
            background: var(--bg-secondary);
        }

        .features-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        .section-label {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .section-title {
            text-align: center;
            font-size: clamp(22px, 4vw, 34px);
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 48px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--bg-color);
            border-radius: var(--radius-md);
            padding: 28px;
            border: 1.5px solid var(--border);
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .feature-icon {
            font-size: 32px;
            margin-bottom: 14px;
            display: block;
        }

        .feature-card h3 {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 14px;
            color: var(--text-mid);
            line-height: 1.6;
        }

        /* SECTIONS STRIP */
        .sections-strip {
            padding: 60px 20px;
            text-align: center;
        }

        .sections-strip .section-title { margin-bottom: 32px; }

        .sections-badges {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .section-badge {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            font-size: 15px;
            padding: 12px 28px;
            border-radius: 50px;
            border: 2px solid var(--primary);
            transition: var(--transition);
        }

        .section-badge:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* CTA */
        .cta {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 80px 20px;
            text-align: center;
            color: white;
        }

        .cta h2 {
            font-size: clamp(24px, 4vw, 36px);
            font-weight: 700;
            margin-bottom: 14px;
        }

        .cta p {
            font-size: 16px;
            opacity: 0.85;
            margin-bottom: 32px;
        }

        .cta .btn {
            width: auto;
            padding: 14px 36px;
            font-size: 15px;
            background: var(--accent);
            color: white;
        }

        .cta .btn:hover { background: var(--accent-dark); }

        /* FOOTER */
        .land-footer {
            background: var(--bg-secondary);
            border-top: 1.5px solid var(--border);
            padding: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--text-light);
        }

        @media (max-width: 768px) {
            .land-nav { padding: 14px 16px; }
            .hero { padding: 60px 16px 40px; }
            .mock-grid { grid-template-columns: 1fr 1fr; }
            .land-nav-links .btn { padding: 8px 14px; font-size: 13px; }
        }
    </style>
</head>
<body class="landing">


<!-- NAV -->
<nav class="land-nav">
    <a href="index.php" class="land-nav-brand">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="3" y="2" width="16" height="20" rx="3" stroke="#7c3aed" stroke-width="2" fill="none"/>
    <path d="M7 8h8M7 12h6" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
    <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
    <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
</svg>
<span>NoteSwap</span>
    </a>
    <div class="land-nav-links">
        <a href="login.php" class="btn btn-secondary">Log In</a>
        <a href="register.php" class="btn btn-primary">Sign Up Free</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-badge">🎓 Built for Arid Agriculture University — Gujrat Campus</div>
    <h1>Share Notes.<br><span>Ace Every</span> <em>Semester.</em></h1>
    <p>Upload and access study notes filtered by your section and semester. Chat with classmates and never miss important material again.</p>
    <div class="hero-btns">
        <a href="register.php" class="btn btn-primary">Get Started Free →</a>
        <a href="login.php" class="btn btn-secondary">Log In</a>
    </div>
    <div class="hero-img">
        <div class="mock-bar">
            <div class="mock-dot"></div>
            <div class="mock-dot"></div>
            <div class="mock-dot"></div>
        </div>
        <div class="mock-grid">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="mock-card">
                <div class="mock-tag"></div>
                <div class="mock-line"></div>
                <div class="mock-line short"></div>
                <div class="mock-btn"></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features">
    <div class="features-inner">
        <p class="section-label">Why NoteSwap?</p>
        <h2 class="section-title">Everything you need to study smarter</h2>
        <div class="features-grid">
            <div class="feature-card">
                <span class="feature-icon">📂</span>
                <h3>Smart Note Filtering</h3>
                <p>Filter notes by your exact section and semester. See only what's relevant to you — no clutter.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">💬</span>
                <h3>Student Chat</h3>
                <p>Message classmates directly, share files and images, and collaborate without leaving the platform.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">📤</span>
                <h3>Easy Uploads</h3>
                <p>Share PDFs, Word documents and images. Help your classmates and build a shared knowledge base.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🔒</span>
                <h3>Secure Accounts</h3>
                <p>Your account is protected with encrypted passwords and session management.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">📱</span>
                <h3>Works on Mobile</h3>
                <p>Access your notes from any device — phone, tablet or laptop. Study anywhere.</p>
            </div>
            <div class="feature-card">
                <span class="feature-icon">🆓</span>
                <h3>Completely Free</h3>
                <p>No subscriptions, no paywalls. NoteSwap is free for every student at the university.</p>
            </div>
        </div>
    </div>
</section>

<!-- SECTIONS STRIP -->
<section class="sections-strip">
    <h2 class="section-title">Available for all CS Sections</h2>
    <div class="sections-badges">
        <div class="section-badge">BSCS</div>
        <div class="section-badge">BSAI</div>
        <div class="section-badge">BSSE</div>
        <div class="section-badge">Semesters 1–8</div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <h2>Ready to study smarter?</h2>
    <p>Join your classmates on NoteSwap today. It takes 30 seconds to sign up.</p>
    <a href="register.php" class="btn">Create Your Free Account ⭐</a>
</section>

<!-- FOOTER -->
<footer class="land-footer">
    <p>© 2025 NoteSwap — Arid Agriculture University, Gujrat Campus &nbsp;·&nbsp; Built with ❤️ for CS students</p>
</footer>

</body>
</html>