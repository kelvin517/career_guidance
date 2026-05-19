<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];
$error = '';
$success = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int)$_POST['receiver_id'];
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if (empty($receiverId)) {
        $error = 'Please select a recipient.';
    } elseif (empty($subject)) {
        $error = 'Please enter a subject.';
    } elseif (empty($message)) {
        $error = 'Please enter a message.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iiss', $userId, $receiverId, $subject, $message);
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Message sent successfully!';
            // Clear form after sending
            $_POST = [];
        } else {
            $error = 'Failed to send message. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Get users for message composition
$userList = [];
if ($role === 'student') {
    $userQuery = "SELECT id, full_name, role FROM users WHERE role IN ('counselor', 'teacher') AND is_active = 1 ORDER BY full_name";
} elseif ($role === 'teacher' || $role === 'counselor') {
    $userQuery = "SELECT id, full_name, role FROM users WHERE role IN ('student', 'teacher', 'counselor') AND id != $userId AND is_active = 1 ORDER BY full_name";
} else {
    $userQuery = "SELECT id, full_name, role FROM users WHERE id != $userId AND is_active = 1 ORDER BY full_name";
}
$userResult = mysqli_query($conn, $userQuery);
while ($user = mysqli_fetch_assoc($userResult)) {
    $userList[] = $user;
}

// Get unread count for badge
$unreadCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE receiver_id = $userId AND is_read = 0"))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Message — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
            --green:#1f7a5c;--red:#c0392b;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

        .sidebar{
            position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
            background:#1a2235;display:flex;flex-direction:column;z-index:100;
            overflow-y:auto;
        }
        .sb-brand{padding:28px 24px 24px;background:linear-gradient(135deg,#1e3a5f 0%,#1a2235 100%);border-bottom:1px solid rgba(255,255,255,.06);}
        .sb-mark{width:40px;height:40px;background:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
        .sb-mark svg{width:22px;height:22px;fill:#fff}
        .sb-name{font-family:'DM Serif Display',serif;font-size:1.15rem;color:#fff;}
        .sb-role{font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);margin-top:3px}
        .sb-section{padding:22px 20px 6px;font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.22)}
        .sb-nav{list-style:none;padding:2px 10px}
        .sb-nav li a{
            display:flex;align-items:center;gap:11px;padding:10px 14px;
            border-radius:9px;font-size:.875rem;color:rgba(255,255,255,.5);
            text-decoration:none;transition:background .2s,color .2s;
        }
        .sb-nav li a:hover{background:rgba(255,255,255,.06);color:#fff}
        .sb-nav li a.active{background:var(--accent);color:#fff}
        .sb-nav li a .nav-icon{font-size:.95rem;width:18px;text-align:center}
        .sb-badge{margin-left:auto;background:#c8622a;color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px}

        .sb-footer{margin-top:auto;padding:16px 10px;border-top:1px solid rgba(255,255,255,.06)}
        .sb-user{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:9px;}
        .sb-avatar{width:36px;height:36px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;color:#fff;flex-shrink:0}
        .sb-user-name{font-size:.85rem;font-weight:600;color:#fff}
        .sb-user-sub{font-size:.7rem;color:rgba(255,255,255,.3)}
        .sb-logout{color:rgba(255,255,255,.3);text-decoration:none;font-size:.8rem;padding:8px 14px;display:block;border-radius:8px;margin-top:8px}
        .sb-logout:hover{color:#fff}

        .main{margin-left:var(--sidebar);min-height:100vh}
        .topbar{background:var(--white);border-bottom:1px solid var(--border);padding:16px 36px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .inbox-link{background:var(--canvas);color:var(--ink-soft);padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:600;border:1.5px solid var(--border)}

        .body{padding:32px 36px}
        .form-container{max-width:700px;margin:0 auto}
        .form-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:32px}
        .form-header{margin-bottom:24px}
        .form-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:8px}
        .form-header p{color:var(--ink-faint)}
        .form-group{margin-bottom:20px}
        label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .form-control{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--canvas);transition:border-color .2s}
        .form-control:focus{outline:none;border-color:var(--accent)}
        textarea.form-control{resize:vertical;min-height:150px}
        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}
        .btn-send{background:var(--accent);color:#fff;border:none;padding:14px;border-radius:10px;font-weight:600;cursor:pointer;width:100%}
        .btn-send:hover{background:#1a4f8f}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Messages</div></div>
    <div class="sb-section">Communication</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="inbox.php"><span class="nav-icon">✉</span> Inbox<?php if ($unreadCount > 0): ?><span class="sb-badge"><?php echo $unreadCount; ?></span><?php endif; ?></a></li>
        <li><a href="send_message.php" class="active"><span class="nav-icon">📝</span> Compose</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Messages / <span>Compose</span></div>
        <a href="inbox.php" class="inbox-link">📥 Go to Inbox</a>
    </div>
    <div class="body">
        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <h1>New Message</h1>
                    <p>Send a message to a student, teacher, or counselor</p>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?> <a href="inbox.php" style="color:var(--accent);">View your inbox →</a></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>To:</label>
                        <select name="receiver_id" class="form-control" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($userList as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['receiver_id']) && $_POST['receiver_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Subject:</label>
                        <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Message:</label>
                        <textarea name="message" class="form-control" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn-send">📤 Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>