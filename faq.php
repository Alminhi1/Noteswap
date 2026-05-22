<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs — NoteSwap</title>
    <script>const t=localStorage.getItem('theme')||'dark';document.documentElement.setAttribute('data-theme',t);</script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        [data-theme="dark"]  body { background:#0f0e17; color:#f3f0ff; }
        [data-theme="light"] body { background:#f0f4f8; color:#111827; }

        .faq-container { max-width:760px; margin:0 auto; padding:40px 20px; }

        .faq-title {
            font-size:28px; font-weight:700; margin-bottom:8px;
            background:linear-gradient(135deg,#7c3aed,#f59e0b);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        }

        .faq-subtitle { font-size:14px; margin-bottom:36px; }
        [data-theme="dark"]  .faq-subtitle { color:#8b82a7; }
        [data-theme="light"] .faq-subtitle { color:#6b7280; }

        .faq-item {
            border-radius:12px; margin-bottom:12px;
            border:1px solid; overflow:hidden; transition:border-color 0.2s;
        }
        [data-theme="dark"]  .faq-item { background:#1a1825; border-color:#2e2a42; }
        [data-theme="light"] .faq-item { background:#ffffff;  border-color:#e5e7eb; }
        .faq-item:hover { border-color:#7c3aed; }

        .faq-question {
            width:100%; text-align:left; padding:18px 20px;
            font-size:15px; font-weight:600; cursor:pointer;
            background:none; border:none;
            display:flex; justify-content:space-between; align-items:center;
            gap:12px; transition:color 0.2s;
        }
        [data-theme="dark"]  .faq-question { color:#f3f0ff; }
        [data-theme="light"] .faq-question { color:#111827; }
        .faq-question:hover { color:#7c3aed; }

        .faq-arrow {
            font-size:12px; transition:transform 0.3s; flex-shrink:0; color:#8b82a7;
        }
        .faq-item.open .faq-arrow { transform:rotate(180deg); color:#7c3aed; }
        .faq-item.open .faq-question { color:#7c3aed; }

        .faq-answer {
            max-height:0; overflow:hidden; transition:max-height 0.35s ease, padding 0.2s;
            padding:0 20px;
            font-size:14px; line-height:1.7;
        }
        [data-theme="dark"]  .faq-answer { color:#8b82a7; }
        [data-theme="light"] .faq-answer { color:#6b7280; }
        .faq-item.open .faq-answer { max-height:400px; padding:0 20px 18px; }

        .faq-answer a { color:#7c3aed; text-decoration:underline; }

        .faq-category {
            font-size:11px; font-weight:700; text-transform:uppercase;
            letter-spacing:1px; color:#8b82a7; margin:28px 0 12px;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="nav-brand">
        <span class="brand-icon">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                <rect x="3" y="2" width="16" height="20" rx="3" stroke="#7c3aed" stroke-width="2" fill="none"/>
                <path d="M7 8h8M7 12h6" stroke="#7c3aed" stroke-width="2" stroke-linecap="round"/>
                <path d="M14 18c0-2.2 1.8-4 4-4s4 1.8 4 4-1.8 4-4 4-4-1.8-4-4z" stroke="#f59e0b" stroke-width="2" fill="none"/>
                <path d="M20.5 21.5l2.5 2.5" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </span>
        NoteSwap
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="btn btn-secondary">← Dashboard</a>
        <button class="btn btn-secondary" onclick="toggleDark()" id="theme-btn">🌙</button>
        <a href="profile.php" class="nav-avatar"><?= strtoupper(substr($user_name,0,1)) ?></a>
    </div>
</nav>

<div class="faq-container">
    <h1 class="faq-title">Frequently Asked Questions</h1>
    <p class="faq-subtitle">Everything you need to know about NoteSwap.</p>

    <p class="faq-category">Getting Started</p>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            How do I create an account?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Go to the <a href="register.php">Register page</a>, enter your full name, email and a password of at least 6 characters. Once registered you can log in and start using NoteSwap immediately.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            How do I set my section and semester?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Go to your <a href="profile.php">Profile page</a> and select your section (BSCS, BSAI, or BSSE) and your current semester. Once saved, your dashboard will automatically show notes relevant to your subjects.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            Is NoteSwap free to use?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Yes — NoteSwap is completely free for all students at Arid Agriculture University, Gujrat Campus. There are no subscriptions or hidden fees.
        </div>
    </div>

    <p class="faq-category">Uploading Notes</p>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            What file types can I upload?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            You can upload PDF files, Word documents (.docx), and images (JPG, PNG). Each file must be under 5MB. You can attach multiple files to a single note.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            My file is too large — what should I do?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Use <a href="https://www.ilovepdf.com" target="_blank">ilovepdf.com</a> to compress PDFs, or <a href="https://squoosh.app" target="_blank">squoosh.app</a> to reduce image sizes. Both are free and work in your browser.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            Can I delete a note I uploaded?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Yes. Go to your <a href="profile.php">Profile page</a> and scroll down to "My Uploaded Notes". Each note has a delete button. Deleting a note permanently removes it and all attached files.
        </div>
    </div>

    <p class="faq-category">Notes and Filtering</p>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            Why don't I see notes for my subject?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Make sure your section and semester are set correctly in your profile. The dashboard filters notes by your curriculum — only subjects in your section and semester appear. If a subject is missing, contact an admin.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            Can I see notes from other sections?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Yes. Use the Section and Semester dropdowns on the dashboard to switch to any section or semester. You can also select "All Sections" to browse everything on the platform.
        </div>
    </div>

    <p class="faq-category">Chat</p>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            How do I start chatting with someone?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Go to the <a href="messages.php">Chat page</a>. Ask your classmate for their User ID (shown at the top of their chat page). Enter their ID in the "Add by ID" box and send a request. Once they accept, you can start chatting.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            Can I send files in chat?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Yes. Click the 📎 attachment icon in the chat input area to attach a PDF, Word document, or image. Files must be under 5MB.
        </div>
    </div>

    <p class="faq-category">Account</p>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            How do I change my password?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Go to your <a href="profile.php">Profile page</a> and scroll to the password section. Enter your new password and confirm it, then click Save Changes.
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question" onclick="toggleFaq(this)">
            How do I log out?
            <span class="faq-arrow">▼</span>
        </button>
        <div class="faq-answer">
            Click the hamburger menu (☰) on the top left of any page and click Logout at the bottom of the menu. You can also log out from your Profile page.
        </div>
    </div>

</div>

<script>
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
}

function toggleDark() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.getElementById('theme-btn').textContent = next === 'dark' ? '☀️' : '🌙';
    localStorage.setItem('theme', next);
}
const saved = localStorage.getItem('theme') || 'dark';
document.documentElement.setAttribute('data-theme', saved);
document.getElementById('theme-btn').textContent = saved === 'dark' ? '☀️' : '🌙';
</script>
</body>
</html>