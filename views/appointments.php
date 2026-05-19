<?php
require_once '../includes/config.php';
if (!isLoggedIn()) redirect('../login.php');

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$role = $_SESSION['role'];
$message = '';
$error = '';

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Book new appointment (student)
    if (isset($_POST['book_appointment']) && $role === 'student') {
        $counselor_id = (int)$_POST['counselor_id'];
        $appointment_date = sanitize_input($_POST['appointment_date']);
        $appointment_time = sanitize_input($_POST['appointment_time']);
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        if (empty($counselor_id)) {
            $error = 'Please select a counselor.';
        } elseif (empty($appointment_date)) {
            $error = 'Please select a date.';
        } elseif (empty($appointment_time)) {
            $error = 'Please select a time.';
        } else {
            // Check if slot is available
            $check = mysqli_query($conn, "SELECT id FROM appointments WHERE counselor_id = $counselor_id AND appointment_date = '$appointment_date' AND appointment_time = '$appointment_time' AND status != 'cancelled'");
            if (mysqli_num_rows($check) > 0) {
                $error = 'This time slot is already booked. Please choose another time.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                mysqli_stmt_bind_param($stmt, 'iisss', $userId, $counselor_id, $appointment_date, $appointment_time, $notes);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Appointment request sent successfully! The counselor will confirm it soon.';
                } else {
                    $error = 'Failed to book appointment. Please try again.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Cancel appointment
    if (isset($_POST['cancel_appointment'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        if ($role === 'student') {
            $check = mysqli_query($conn, "SELECT id FROM appointments WHERE id = $appointment_id AND student_id = $userId");
        } else {
            $check = mysqli_query($conn, "SELECT id FROM appointments WHERE id = $appointment_id AND counselor_id = $userId");
        }
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id");
            $message = 'Appointment cancelled successfully.';
        } else {
            $error = 'You do not have permission to cancel this appointment.';
        }
    }
    
    // Confirm appointment (counselor only)
    if (isset($_POST['confirm_appointment']) && $role === 'counselor') {
        $appointment_id = (int)$_POST['appointment_id'];
        $check = mysqli_query($conn, "SELECT id FROM appointments WHERE id = $appointment_id AND counselor_id = $userId AND status = 'pending'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE appointments SET status = 'confirmed' WHERE id = $appointment_id");
            $message = 'Appointment confirmed successfully.';
        } else {
            $error = 'Unable to confirm this appointment.';
        }
    }
    
    // Complete appointment (counselor only)
    if (isset($_POST['complete_appointment']) && $role === 'counselor') {
        $appointment_id = (int)$_POST['appointment_id'];
        $notes = sanitize_input($_POST['session_notes'] ?? '');
        $check = mysqli_query($conn, "SELECT id FROM appointments WHERE id = $appointment_id AND counselor_id = $userId AND status = 'confirmed'");
        if (mysqli_num_rows($check) > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE appointments SET status = 'completed', notes = CONCAT(notes, '\n\n--- Session Notes ---\n', ?) WHERE id = $appointment_id");
            mysqli_stmt_bind_param($stmt, 's', $notes);
            mysqli_stmt_execute($stmt);
            $message = 'Appointment marked as completed.';
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Unable to complete this appointment.';
        }
    }
}

// Get appointments based on role
$appointments = [];
if ($role === 'student') {
    $query = mysqli_query($conn, "
        SELECT a.*, u.full_name as counselor_name, u.email as counselor_email
        FROM appointments a
        JOIN users u ON a.counselor_id = u.id
        WHERE a.student_id = $userId
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
} else if ($role === 'counselor') {
    $query = mysqli_query($conn, "
        SELECT a.*, u.full_name as student_name, u.email as student_email
        FROM appointments a
        JOIN users u ON a.student_id = u.id
        WHERE a.counselor_id = $userId
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
}
if ($query) {
    while ($row = mysqli_fetch_assoc($query)) {
        $appointments[] = $row;
    }
}

// Get upcoming appointments
$upcoming = array_filter($appointments, function($a) {
    return $a['status'] !== 'completed' && $a['status'] !== 'cancelled';
});

// Get past appointments
$past = array_filter($appointments, function($a) {
    return $a['status'] === 'completed' || $a['status'] === 'cancelled';
});

// For students: get available counselors
$counselors = [];
if ($role === 'student') {
    $counselorQuery = mysqli_query($conn, "SELECT u.id, u.full_name, u.email, cp.specialization, cp.years_experience FROM users u JOIN counselor_profiles cp ON u.id = cp.user_id WHERE u.role = 'counselor' AND u.is_active = 1 ORDER BY u.full_name");
    if ($counselorQuery) {
        while ($row = mysqli_fetch_assoc($counselorQuery)) {
            $counselors[] = $row;
        }
    }
}

$greeting = date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — Smart Learning</title>
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
            --green:#1f7a5c;--orange:#c8622a;--red:#c0392b;--amber:#b87c10;
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
        .body{padding:32px 36px}

        .page-header{margin-bottom:28px}
        .page-title{font-family:'DM Serif Display',serif;font-size:1.8rem;letter-spacing:-.02em}
        .page-sub{font-size:.85rem;color:var(--ink-faint);margin-top:4px}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}
        .alert-info{background:var(--accent-lt);border:1px solid #c5d8f0;color:var(--accent)}

        .two-col{display:grid;grid-template-columns:1fr 1.2fr;gap:28px}
        
        /* Booking Form */
        .booking-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:28px}
        .booking-card h2{font-family:'DM Serif Display',serif;font-size:1.3rem;margin-bottom:20px}
        .form-group{margin-bottom:20px}
        label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .form-control{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--canvas);transition:border-color .2s}
        .form-control:focus{outline:none;border-color:var(--accent)}
        select.form-control{cursor:pointer}
        textarea.form-control{resize:vertical;min-height:80px}
        .btn{background:var(--accent);color:#fff;border:none;padding:12px 24px;border-radius:10px;font-weight:600;cursor:pointer;width:100%}
        .btn:hover{background:#1a4f8f}

        /* Appointments List */
        .appointments-section{margin-bottom:32px}
        .section-title{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);margin-bottom:16px;padding-bottom:8px;border-bottom:1.5px solid var(--accent-lt)}
        
        .appointment-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:20px;margin-bottom:16px;transition:transform .2s}
        .appointment-card:hover{transform:translateX(4px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
        .appointment-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}
        .appointment-title{font-weight:700;font-size:1rem}
        .appointment-meta{display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;font-size:.8rem;color:var(--ink-faint)}
        .appointment-meta span{display:flex;align-items:center;gap:6px}
        .status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.7rem;font-weight:600}
        .status-pending{background:#fef3c7;color:#d97706}
        .status-confirmed{background:#d1fae5;color:#059669}
        .status-completed{background:#e0e7ff;color:#4f46e5}
        .status-cancelled{background:#fee2e2;color:#dc2626}
        .appointment-actions{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
        .btn-sm{padding:6px 14px;border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;cursor:pointer;border:none}
        .btn-confirm{background:var(--green);color:#fff}
        .btn-complete{background:#4f46e5;color:#fff}
        .btn-cancel{background:var(--red);color:#fff}
        .btn-cancel-outline{background:transparent;border:1.5px solid var(--red);color:var(--red)}

        /* Counselor Cards */
        .counselor-grid{display:grid;grid-template-columns:1fr;gap:16px;margin-top:20px}
        .counselor-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:16px;display:flex;gap:16px;align-items:center}
        .counselor-avatar{width:50px;height:50px;background:var(--accent-dim);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
        .counselor-info{flex:1}
        .counselor-name{font-weight:600;margin-bottom:4px}
        .counselor-spec{font-size:.75rem;color:var(--ink-faint)}
        .select-counselor{background:var(--accent);color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer}

        .empty-state{text-align:center;padding:40px 20px;color:var(--ink-faint)}
        .empty-state .icon{font-size:2.5rem;margin-bottom:12px}

        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .body{padding:20px}
            .two-col{grid-template-columns:1fr}
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand"><div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div class="sb-name">Smart Learning</div><div class="sb-role">Appointments</div></div>
    <ul class="sb-nav">
        <li><a href="dashboard/<?php echo $role; ?>_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="appointments.php" class="active"><span class="nav-icon">📅</span> Appointments</a></li>
        <?php if ($role === 'student'): ?>
            <li><a href="career/recommendations.php"><span class="nav-icon">🧭</span> Career Matches</a></li>
            <li><a href="assessment/interest_questionnaire.php"><span class="nav-icon">📋</span> Take Assessment</a></li>
        <?php endif; ?>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?php echo $avatarLetter; ?></div><div><div class="sb-user-name"><?php echo htmlspecialchars($fullName); ?></div><div class="sb-user-sub"><?php echo ucfirst($role); ?></div></div></div>
        <a href="../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb"><?php echo ucfirst($role); ?> / <span>Appointments</span></div>
    </div>
    <div class="body">
        <div class="page-header">
            <h1 class="page-title">Appointments</h1>
            <p class="page-sub"><?php echo $role === 'student' ? 'Book sessions with career counselors' : 'Manage student appointments and guidance sessions'; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
            <div class="two-col">
                <!-- Booking Form -->
                <div class="booking-card">
                    <h2>📅 Book a Session</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Counselor</label>
                            <select name="counselor_id" class="form-control" required>
                                <option value="">-- Choose a counselor --</option>
                                <?php foreach ($counselors as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?> (<?php echo htmlspecialchars($c['specialization']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <select name="appointment_time" class="form-control" required>
                                <option value="">-- Select time --</option>
                                <option value="09:00:00">9:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="14:00:00">2:00 PM</option>
                                <option value="15:00:00">3:00 PM</option>
                                <option value="16:00:00">4:00 PM</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Additional Notes (Optional)</label>
                            <textarea name="notes" class="form-control" placeholder="What would you like to discuss?"></textarea>
                        </div>
                        <button type="submit" name="book_appointment" class="btn">📅 Request Appointment</button>
                    </form>
                </div>

                <!-- Available Counselors -->
                <div>
                    <div class="section-title">👥 Available Counselors</div>
                    <div class="counselor-grid">
                        <?php foreach ($counselors as $c): ?>
                            <div class="counselor-card">
                                <div class="counselor-avatar">💼</div>
                                <div class="counselor-info">
                                    <div class="counselor-name"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                    <div class="counselor-spec"><?php echo htmlspecialchars($c['specialization']); ?> · <?php echo $c['years_experience']; ?> years exp.</div>
                                    <div class="counselor-spec">📧 <?php echo htmlspecialchars($c['email']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upcoming Appointments -->
        <div class="appointments-section">
            <div class="section-title">📋 <?php echo $role === 'student' ? 'My Upcoming Sessions' : 'Pending & Upcoming Appointments'; ?></div>
            <?php if (!empty($upcoming)): ?>
                <?php foreach ($upcoming as $a): ?>
                    <div class="appointment-card">
                        <div class="appointment-header">
                            <div>
                                <div class="appointment-title"><?php echo $role === 'student' ? 'with ' . htmlspecialchars($a['counselor_name']) : 'with ' . htmlspecialchars($a['student_name']); ?></div>
                                <div class="appointment-meta">
                                    <span>📅 <?php echo date('l, F j, Y', strtotime($a['appointment_date'])); ?></span>
                                    <span>⏰ <?php echo date('g:i A', strtotime($a['appointment_time'])); ?></span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $a['status']; ?>">
                                <?php echo ucfirst($a['status']); ?>
                            </span>
                        </div>
                        <?php if (!empty($a['notes'])): ?>
                            <div style="margin-top:8px; font-size:.78rem; color:var(--ink-faint);">📝 <?php echo htmlspecialchars(substr($a['notes'], 0, 100)); ?></div>
                        <?php endif; ?>
                        <div class="appointment-actions">
                            <?php if ($role === 'counselor' && $a['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="confirm_appointment" class="btn-sm btn-confirm">✓ Confirm</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="cancel_appointment" class="btn-sm btn-cancel">✗ Cancel</button>
                                </form>
                            <?php elseif ($role === 'counselor' && $a['status'] === 'confirmed'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <textarea name="session_notes" placeholder="Session notes..." style="padding:6px; border-radius:6px; border:1px solid var(--border); margin-right:8px;" rows="1"></textarea>
                                    <button type="submit" name="complete_appointment" class="btn-sm btn-complete">✓ Mark Completed</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="cancel_appointment" class="btn-sm btn-cancel-outline">✗ Cancel</button>
                                </form>
                            <?php elseif ($a['status'] !== 'completed' && $a['status'] !== 'cancelled'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this appointment?')">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" name="cancel_appointment" class="btn-sm btn-cancel-outline">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📅</div>
                    <p><?php echo $role === 'student' ? 'No upcoming appointments. Book a session with a counselor!' : 'No pending appointments.'; ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Past Appointments -->
        <?php if (!empty($past)): ?>
            <div class="appointments-section">
                <div class="section-title">📜 Past Appointments</div>
                <?php foreach ($past as $a): ?>
                    <div class="appointment-card" style="opacity:0.8;">
                        <div class="appointment-header">
                            <div>
                                <div class="appointment-title"><?php echo $role === 'student' ? 'with ' . htmlspecialchars($a['counselor_name']) : 'with ' . htmlspecialchars($a['student_name']); ?></div>
                                <div class="appointment-meta">
                                    <span>📅 <?php echo date('l, F j, Y', strtotime($a['appointment_date'])); ?></span>
                                    <span>⏰ <?php echo date('g:i A', strtotime($a['appointment_time'])); ?></span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $a['status']; ?>">
                                <?php echo ucfirst($a['status']); ?>
                            </span>
                        </div>
                        <?php if (!empty($a['notes'])): ?>
                            <div style="margin-top:8px; font-size:.78rem; color:var(--ink-faint);">📝 <?php echo htmlspecialchars(substr($a['notes'], 0, 150)); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Set min date for date picker
    const dateInput = document.querySelector('input[name="appointment_date"]');
    if (dateInput) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.min = yyyy + '-' + mm + '-' + dd;
    }
</script>
</body>
</html>