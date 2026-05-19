<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark as read
    if (isset($_POST['mark_read']) && isset($_POST['message_id'])) {
        $msgId = (int)$_POST['message_id'];
        mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE id = $msgId AND receiver_id = $userId");
    }
    
    // Delete message
    if (isset($_POST['delete']) && isset($_POST['message_id'])) {
        $msgId = (int)$_POST['message_id'];
        mysqli_query($conn, "DELETE FROM messages WHERE id = $msgId AND (sender_id = $userId OR receiver_id = $userId)");
    }
    
    // Reply to message
    if (isset($_POST['reply']) && isset($_POST['message_id']) && isset($_POST['reply_message'])) {
        $parentId = (int)$_POST['message_id'];
        $replyText = sanitize_input($_POST['reply_message']);
        
        // Get original message to find receiver
        $origQuery = mysqli_query($conn, "SELECT sender_id, receiver_id FROM messages WHERE id = $parentId");
        $orig = mysqli_fetch_assoc($origQuery);
        if ($orig) {
            $toUserId = ($orig['sender_id'] == $userId) ? $orig['receiver_id'] : $orig['sender_id'];
            $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?, ?, ?, ?, ?)");
            $subject = "Re: Message";
            mysqli_stmt_bind_param($stmt, 'iissi', $userId, $toUserId, $subject, $replyText, $parentId);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'inbox';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT m.*, 
        sender.full_name as sender_name, 
        sender.role as sender_role,
        receiver.full_name as receiver_name,
        receiver.role as receiver_role
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE ";

if ($filter === 'sent') {
    $sql .= " m.sender_id = $userId";
} else {
    $sql .= " m.receiver_id = $userId";
}

if (!empty($search)) {
    $sql .= " AND (m.subject LIKE '%$search%' OR m.message LIKE '%$search%')";
}

$sql .= " ORDER BY m.sent_at DESC";

$result = mysqli_query($conn, $sql);
$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

// Get unread count
$unreadCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM messages WHERE receiver_id = $userId AND is_read = 0"))['c'];

// Get users for new message (students can message counselors, teachers can message all)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Smart Learning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ink:#0f1117;--ink-soft:#4a4d5a;--ink-faint:#9396a3;
            --canvas:#f5f7f9;--white:#fff;
            --accent:#2563a8;--accent-lt:#eef3fb;--accent-dim:rgba(37,99,168,.12);
            --border:#e4e8ed;--radius:14px;
            --sidebar:264px;
            --green:#1f7a5c;--orange:#c8622a;--red:#c0392b;
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
        .sb-badge{margin-left:auto;background:var(--orange);color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:20px}

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
        .new-msg-btn{background:var(--accent);color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:600}
        
        .body{padding:32px 36px}
        
        .message-tabs{display:flex;gap:4px;margin-bottom:24px;background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:6px}
        .tab{flex:1;text-align:center;padding:12px;border-radius:10px;text-decoration:none;font-weight:600;color:var(--ink-soft)}
        .tab.active{background:var(--accent);color:#fff}
        
        .search-bar{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:16px;margin-bottom:24px}
        .search-bar form{display:flex;gap:12px}
        .search-bar input{flex:1;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif}
        .search-bar button{background:var(--accent);color:#fff;border:none;padding:12px 24px;border-radius:10px;cursor:pointer}
        
        .messages-list{display:flex;flex-direction:column;gap:12px}
        .message-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);overflow:hidden;transition:all .2s}
        .message-card.unread{border-left:4px solid var(--accent);background:var(--accent-lt)}
        .message-header{padding:16px 20px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;background:var(--white)}
        .message-sender{display:flex;align-items:center;gap:12px}
        .sender-avatar{width:36px;height:36px;background:var(--accent-dim);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:600}
        .sender-info strong{font-size:.9rem}
        .sender-info small{font-size:.7rem;color:var(--ink-faint)}
        .message-subject{font-weight:600;margin:4px 0}
        .message-preview{font-size:.8rem;color:var(--ink-soft)}
        .message-time{font-size:.7rem;color:var(--ink-faint)}
        .message-actions{display:flex;gap:8px}
        .action-btn{background:none;border:none;cursor:pointer;padding:6px;border-radius:6px;transition:background .2s}
        .action-btn:hover{background:var(--canvas)}
        .message-body{padding:20px;border-top:1.5px solid var(--border);display:none;background:var(--canvas)}
        .message-body.show{display:block}
        .reply-box{margin-top:16px;display:flex;gap:12px}
        .reply-box textarea{flex:1;padding:12px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;resize:vertical}
        
        .empty-state{text-align:center;padding:60px 20px;color:var(--ink-faint)}
        .empty-state .icon{font-size:3rem;margin-bottom:16px}
        
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:var(--white);border-radius:var(--radius);padding:28px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;margin-bottom:20px}
        .modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer}
        
        @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.body{padding:20px}.message-header{flex-wrap:wrap}}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Messages</div></div>
    <div class="sb-section">Communication</div>
    <ul class="sb-nav">
        <li><a href="../dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="inbox.php" class="active"><span class="nav-icon">✉</span> Inbox<?php if ($unreadCount > 0): ?><span class="sb-badge"><?php echo $unreadCount; ?></span><?php endif; ?></a></li>
        <li><a href="send_message.php"><span class="nav-icon">📝</span> Compose</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Messages / <span><?php echo $filter === 'sent' ? 'Sent' : 'Inbox'; ?></span></div>
        <a href="send_message.php" class="new-msg-btn">✏ New Message</a>
    </div>
    <div class="body">
        <!-- Tabs -->
        <div class="message-tabs">
            <a href="?filter=inbox" class="tab <?php echo $filter === 'inbox' ? 'active' : ''; ?>">📥 Inbox <?php echo $unreadCount > 0 ? "($unreadCount)" : ''; ?></a>
            <a href="?filter=sent" class="tab <?php echo $filter === 'sent' ? 'active' : ''; ?>">📤 Sent</a>
        </div>
        
        <!-- Search -->
        <div class="search-bar">
            <form method="GET">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Search</button>
                <?php if (!empty($search)): ?>
                    <a href="?filter=<?php echo $filter; ?>" style="background:var(--border);padding:12px 24px;border-radius:10px;text-decoration:none;color:var(--ink);">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Messages List -->
        <div class="messages-list">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): 
                    $isUnread = ($filter !== 'sent' && $msg['is_read'] == 0);
                    $senderName = $filter === 'sent' ? $msg['receiver_name'] : $msg['sender_name'];
                    $senderRole = $filter === 'sent' ? $msg['receiver_role'] : $msg['sender_role'];
                ?>
                    <div class="message-card <?php echo $isUnread ? 'unread' : ''; ?>" data-id="<?php echo $msg['id']; ?>">
                        <div class="message-header" onclick="toggleMessage(<?php echo $msg['id']; ?>)">
                            <div style="display:flex; align-items:center; gap:12px; flex:1;">
                                <div class="sender-avatar"><?php echo strtoupper(substr($senderName, 0, 1)); ?></div>
                                <div>
                                    <div><strong><?php echo htmlspecialchars($senderName); ?></strong> <small>(<?php echo ucfirst($senderRole); ?>)</small></div>
                                    <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                    <div class="message-preview"><?php echo htmlspecialchars(substr($msg['message'], 0, 80)); ?>...</div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div class="message-time"><?php echo date('M j, H:i', strtotime($msg['sent_at'])); ?></div>
                                <div class="message-actions" onclick="event.stopPropagation()">
                                    <?php if ($filter !== 'sent' && !$msg['is_read']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <button type="submit" name="mark_read" class="action-btn" title="Mark as read">✓</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?')">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" name="delete" class="action-btn" title="Delete">🗑</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="message-body" id="message-body-<?php echo $msg['id']; ?>">
                            <div style="margin-bottom:16px;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                            <form method="POST" class="reply-box">
                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                <textarea name="reply_message" placeholder="Write your reply..." rows="2" required></textarea>
                                <button type="submit" name="reply" class="action-btn" style="background:var(--accent);color:#fff;padding:12px;">Reply</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">✉️</div>
                    <p>No messages found.</p>
                    <p><a href="send_message.php" style="color:var(--accent);">Send a message →</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Message Modal (Quick Compose) -->
<div id="composeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3>New Message</h3><button class="modal-close" onclick="closeModal()">×</button></div>
        <form method="POST" action="send_message.php">
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px;">To:</label>
                <select name="receiver_id" required style="width:100%; padding:10px; border:1.5px solid var(--border); border-radius:8px;">
                    <option value="">Select recipient...</option>
                    <?php foreach ($userList as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px;">Subject:</label>
                <input type="text" name="subject" required style="width:100%; padding:10px; border:1.5px solid var(--border); border-radius:8px;">
            </div>
            <div style="margin-bottom:16px;">
                <label style="display:block; margin-bottom:6px;">Message:</label>
                <textarea name="message" rows="5" required style="width:100%; padding:10px; border:1.5px solid var(--border); border-radius:8px; resize:vertical;"></textarea>
            </div>
            <button type="submit" name="send_message" style="background:var(--accent);color:#fff;border:none;padding:12px;border-radius:8px;width:100%;cursor:pointer;">Send Message</button>
        </form>
    </div>
</div>

<script>
    function toggleMessage(id) {
        const body = document.getElementById('message-body-' + id);
        body.classList.toggle('show');
    }
    
    function openModal() {
        document.getElementById('composeModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('composeModal').classList.remove('show');
    }
    
    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('composeModal');
        if (event.target === modal) modal.classList.remove('show');
    }
</script>
</body>
</html>