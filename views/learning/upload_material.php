<?php
require_once '../../includes/config.php';
if (!isLoggedIn()) redirect('../../login.php');
if ($_SESSION['role'] !== 'teacher') redirect_by_role($_SESSION['role']);

$userId = $_SESSION['user_id'];
$fullName = $_SESSION['full_name'];
$firstName = explode(' ', $fullName)[0];
$avatarLetter = strtoupper(substr($fullName, 0, 1));
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $type = sanitize_input($_POST['type'] ?? 'pdf');
    $file_path = '';
    
    // Validate
    if (empty($title)) $error = 'Title is required.';
    if (empty($description)) $error = 'Description is required.';
    
    // Handle file upload if not a link
    if ($type !== 'link') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4'];
            $fileExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExt, $allowed)) {
                $error = 'Invalid file type. Allowed: ' . implode(', ', $allowed);
            } elseif ($_FILES['file']['size'] > 10 * 1024 * 1024) {
                $error = 'File too large. Max 10MB.';
            } else {
                $uploadDir = '../../uploads/materials/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['file']['name']);
                $file_path = 'uploads/materials/' . $fileName;
                if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
                    $error = 'Failed to upload file.';
                }
            }
        } else {
            $error = 'Please select a file to upload.';
        }
    } else {
        // Handle link submission
        $link_url = sanitize_input($_POST['link_url'] ?? '');
        if (empty($link_url)) {
            $error = 'Please enter a valid URL.';
        } elseif (!filter_var($link_url, FILTER_VALIDATE_URL)) {
            $error = 'Invalid URL format.';
        } else {
            $file_path = $link_url;
        }
    }
    
    // Insert into database
    if (empty($error)) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO materials (title, description, type, category, subject, file_path, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'ssssssi', $title, $description, $type, $category, $subject, $file_path, $userId);
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Material uploaded successfully!';
            // Clear form
            $_POST = [];
        } else {
            $error = 'Database error. Please try again.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Material — Smart Learning</title>
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
            --green:#1f7a5c;--red:#c0392b;
        }
        html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--canvas);color:var(--ink)}

        .sidebar{
            position:fixed;top:0;left:0;height:100vh;width:var(--sidebar);
            background:#1a2235;display:flex;flex-direction:column;z-index:100;
            overflow-y:auto;
        }
        .sb-brand{
            padding:28px 24px 24px;
            background:linear-gradient(135deg,#1e3a5f 0%,#1a2235 100%);
            border-bottom:1px solid rgba(255,255,255,.06);
        }
        .sb-mark{
            width:40px;height:40px;background:var(--accent);border-radius:10px;
            display:flex;align-items:center;justify-content:center;margin-bottom:14px;
        }
        .sb-mark svg{width:22px;height:22px;fill:#fff}
        .sb-name{font-family:'DM Serif Display',serif;font-size:1.15rem;color:#fff;line-height:1.1}
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
        .topbar{
            background:var(--white);border-bottom:1px solid var(--border);
            padding:16px 36px;display:flex;align-items:center;gap:16px;
            position:sticky;top:0;z-index:50;
        }
        .topbar-breadcrumb{font-size:.8rem;color:var(--ink-faint)}
        .topbar-breadcrumb span{color:var(--ink);font-weight:600}
        .body{padding:32px 36px}

        .form-container{max-width:800px;margin:0 auto}
        .form-card{background:var(--white);border-radius:var(--radius);border:1.5px solid var(--border);padding:36px}
        .form-header{margin-bottom:28px}
        .form-header h1{font-family:'DM Serif Display',serif;font-size:1.8rem;margin-bottom:8px}
        .form-header p{color:var(--ink-faint)}

        .form-group{margin-bottom:24px}
        label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-faint);margin-bottom:8px}
        .form-control{
            width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;
            font-family:'DM Sans',sans-serif;font-size:.9rem;background:var(--canvas);
            transition:border-color .2s;
        }
        .form-control:focus{outline:none;border-color:var(--accent)}
        textarea.form-control{resize:vertical;min-height:100px}
        select.form-control{cursor:pointer}

        .alert{padding:14px 18px;border-radius:12px;margin-bottom:24px}
        .alert-success{background:#e8f5f0;border:1px solid #c5e0d4;color:var(--green)}
        .alert-danger{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}

        .btn-submit{
            width:100%;padding:14px;background:var(--accent);color:#fff;border:none;
            border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;
            transition:background .2s;
        }
        .btn-submit:hover{background:#1a4f8f}

        .link-field{display:none}
        @media(max-width:900px){
            .sidebar{display:none}.main{margin-left:0}
            .body{padding:20px}
            .form-card{padding:24px}
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-brand">
        <div class="sb-mark"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div class="sb-name">Smart Learning</div>
        <div class="sb-role">Teacher Portal</div>
    </div>
    <ul class="sb-nav">
        <li><a href="../dashboard/teacher_dashboard.php"><span class="nav-icon">⊞</span> Dashboard</a></li>
        <li><a href="materials_list.php"><span class="nav-icon">📚</span> Materials</a></li>
        <li><a href="upload_material.php" class="active"><span class="nav-icon">⬆</span> Upload Material</a></li>
    </ul>
    <div class="sb-footer">
        <div class="sb-user"><div class="sb-avatar"><?= $avatarLetter ?></div><div><div class="sb-user-name"><?= $fullName ?></div><div class="sb-user-sub">Teacher</div></div></div>
        <a href="../../logout.php" class="sb-logout">→ Sign out</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-breadcrumb">Teaching / <span>Upload Material</span></div>
    </div>
    <div class="body">
        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <h1>Upload Learning Material</h1>
                    <p>Share educational resources with your students</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" class="form-control" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Material Type *</label>
                        <select name="type" id="materialType" class="form-control" required>
                            <option value="pdf" <?= (($_POST['type'] ?? '') === 'pdf') ? 'selected' : '' ?>>📄 PDF Document</option>
                            <option value="doc" <?= (($_POST['type'] ?? '') === 'doc') ? 'selected' : '' ?>>📝 Word Document (DOC)</option>
                            <option value="docx" <?= (($_POST['type'] ?? '') === 'docx') ? 'selected' : '' ?>>📝 Word Document (DOCX)</option>
                            <option value="ppt" <?= (($_POST['type'] ?? '') === 'ppt') ? 'selected' : '' ?>>📊 PowerPoint (PPT)</option>
                            <option value="pptx" <?= (($_POST['type'] ?? '') === 'pptx') ? 'selected' : '' ?>>📊 PowerPoint (PPTX)</option>
                            <option value="mp4" <?= (($_POST['type'] ?? '') === 'mp4') ? 'selected' : '' ?>>🎬 Video (MP4)</option>
                            <option value="link" <?= (($_POST['type'] ?? '') === 'link') ? 'selected' : '' ?>>🔗 External Link</option>
                        </select>
                    </div>

                    <div class="form-group" id="fileField">
                        <label>File *</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4">
                        <small style="font-size:.7rem;color:var(--ink-faint);margin-top:4px;display:block">Max 10MB. Allowed: PDF, DOC, DOCX, PPT, PPTX, MP4</small>
                    </div>

                    <div class="form-group link-field" id="linkField">
                        <label>URL *</label>
                        <input type="url" name="link_url" class="form-control" placeholder="https://example.com/resource">
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="">-- Select Category --</option>
                            <option value="Lecture Notes" <?= (($_POST['category'] ?? '') === 'Lecture Notes') ? 'selected' : '' ?>>Lecture Notes</option>
                            <option value="Tutorial" <?= (($_POST['category'] ?? '') === 'Tutorial') ? 'selected' : '' ?>>Tutorial</option>
                            <option value="Assignment" <?= (($_POST['category'] ?? '') === 'Assignment') ? 'selected' : '' ?>>Assignment</option>
                            <option value="Exam Prep" <?= (($_POST['category'] ?? '') === 'Exam Prep') ? 'selected' : '' ?>>Exam Preparation</option>
                            <option value="Reference" <?= (($_POST['category'] ?? '') === 'Reference') ? 'selected' : '' ?>>Reference Material</option>
                            <option value="Other" <?= (($_POST['category'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subject</label>
                        <select name="subject" class="form-control">
                            <option value="">-- Select Subject --</option>
                            <option value="Mathematics" <?= (($_POST['subject'] ?? '') === 'Mathematics') ? 'selected' : '' ?>>Mathematics</option>
                            <option value="Physics" <?= (($_POST['subject'] ?? '') === 'Physics') ? 'selected' : '' ?>>Physics</option>
                            <option value="Chemistry" <?= (($_POST['subject'] ?? '') === 'Chemistry') ? 'selected' : '' ?>>Chemistry</option>
                            <option value="Biology" <?= (($_POST['subject'] ?? '') === 'Biology') ? 'selected' : '' ?>>Biology</option>
                            <option value="Computer Science" <?= (($_POST['subject'] ?? '') === 'Computer Science') ? 'selected' : '' ?>>Computer Science</option>
                            <option value="Business" <?= (($_POST['subject'] ?? '') === 'Business') ? 'selected' : '' ?>>Business</option>
                            <option value="English" <?= (($_POST['subject'] ?? '') === 'English') ? 'selected' : '' ?>>English</option>
                            <option value="History" <?= (($_POST['subject'] ?? '') === 'History') ? 'selected' : '' ?>>History</option>
                            <option value="Geography" <?= (($_POST['subject'] ?? '') === 'Geography') ? 'selected' : '' ?>>Geography</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">📤 Upload Material</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const typeSelect = document.getElementById('materialType');
    const fileField = document.getElementById('fileField');
    const linkField = document.getElementById('linkField');
    
    function toggleFields() {
        if (typeSelect.value === 'link') {
            fileField.style.display = 'none';
            linkField.style.display = 'block';
        } else {
            fileField.style.display = 'block';
            linkField.style.display = 'none';
        }
    }
    toggleFields();
    typeSelect.addEventListener('change', toggleFields);
</script>
</body>
</html>