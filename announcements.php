
<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$allowedRoles = [
    'admin',
    'chairperson',
    'secretary',
    'vice_secretary',
    'pastor',
    'treasurer'
];

if (!in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    exit("Access denied. You are not authorized to view announcements.");
}

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];

$canManage = in_array($role, [
    'admin',
    'chairperson',
    'secretary',
    'vice_secretary'
], true);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Create or update announcement status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit("You are not allowed to manage announcements.");
    }

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = "Invalid security token. Refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $announcementMessage = trim($_POST['message'] ?? '');
            $announcementDate = $_POST['announcement_date'] ?? '';
            $audience = trim($_POST['audience'] ?? 'All Members');
            $status = $_POST['status'] ?? 'Draft';

            $validStatuses = ['Draft', 'Published'];

            if (
                $title === '' ||
                $announcementMessage === '' ||
                $announcementDate === '' ||
                $audience === '' ||
                !in_array($status, $validStatuses, true)
            ) {
                $error = "Please complete all fields using valid values.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO announcements
                    (title, message, announcement_date, audience, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssi",
                        $title,
                        $announcementMessage,
                        $announcementDate,
                        $audience,
                        $status,
                        $userId
                    );

                    if ($stmt->execute()) {
                        header("Location: announcements.php?success=created");
                        exit();
                    } else {
                        $error = "Could not save the announcement.";
                    }

                    $stmt->close();
                } else {
                    $error = "Could not prepare the database query.";
                }
            }
        } elseif ($action === 'update_status') {
            $announcementId = (int)($_POST['announcement_id'] ?? 0);
            $newStatus = $_POST['status'] ?? '';

            if (
                $announcementId < 1 ||
                !in_array($newStatus, ['Draft', 'Published', 'Archived'], true)
            ) {
                $error = "Invalid announcement or status.";
            } else {
                $stmt = $conn->prepare(
                    "UPDATE announcements SET status = ? WHERE id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param("si", $newStatus, $announcementId);

                    if ($stmt->execute()) {
                        header("Location: announcements.php?success=updated");
                        exit();
                    } else {
                        $error = "Could not update the announcement.";
                    }

                    $stmt->close();
                } else {
                    $error = "Could not prepare the database query.";
                }
            }
        } else {
            $error = "Invalid request.";
        }
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $message = "Announcement saved successfully.";
    } elseif ($_GET['success'] === 'updated') {
        $message = "Announcement status updated successfully.";
    }
}

// Search announcements
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $searchTerm = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT a.*, u.username
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         WHERE a.title LIKE ? OR a.message LIKE ?
         ORDER BY a.announcement_date DESC, a.id DESC"
    );

    $stmt->bind_param("ss", $searchTerm, $searchTerm);
} else {
    $stmt = $conn->prepare(
        "SELECT a.*, u.username
         FROM announcements a
         LEFT JOIN users u ON a.created_by = u.id
         ORDER BY a.announcement_date DESC, a.id DESC"
    );
}

$stmt->execute();
$announcements = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Announcements | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #263247;
        }

        .container {
            max-width: 1150px;
            margin: auto;
        }

        .header {
            background: #17365d;
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            color: #e0e9f5;
        }

        .navigation {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .navigation a {
            color: white;
            text-decoration: none;
            padding: 9px 13px;
            background: rgba(255,255,255,0.14);
            border-radius: 6px;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(20,40,70,0.06);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input, textarea, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccd5e0;
            border-radius: 7px;
            font: inherit;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        button, .button {
            display: inline-block;
            padding: 10px 15px;
            background: #176b4d;
            color: white;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font: inherit;
        }

        .search-button {
            background: #17365d;
        }

        .alert {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 7px;
        }

        .success {
            background: #e4f7ec;
            color: #176b4d;
        }

        .error {
            background: #fdeaea;
            color: #a32626;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-form input {
            flex: 1;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e4e9f0;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5f7fa;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .Draft {
            background: #fff0cf;
            color: #805600;
        }

        .Published {
            background: #d9f5e5;
            color: #176b4d;
        }

        .Archived {
            background: #e7eaf0;
            color: #505a6b;
        }

        .status-form {
            display: flex;
            gap: 7px;
            min-width: 190px;
        }

        .status-form select {
            padding: 7px;
        }

        .status-form button {
            padding: 8px;
        }

        .announcement-message {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            max-width: 400px;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

            .search-form {
                flex-direction: column;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>
<div class="container">

    <header class="header">
        <h1>AIC Kalyet Church</h1>
        <p>Church Announcements Management</p>

        <nav class="navigation">
            <?php if ($role === 'admin'): ?>
                <a href="admin/dashboard.php">Admin Dashboard</a>
            <?php elseif ($role === 'chairperson'): ?>
                <a href="chairperson/dashboard.php">Chairperson Dashboard</a>
            <?php elseif (in_array($role, ['secretary', 'vice_secretary'], true)): ?>
                <a href="secretary/dashboard.php">Secretary Dashboard</a>
            <?php endif; ?>

            <a href="minutes.php">Meeting Minutes</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="card">
            <h2>Create Announcement</h2>

            <form method="POST" action="announcements.php">
                <input type="hidden" name="csrf_token"
                       value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div class="full-width">
                        <label for="title">Announcement Title</label>
                        <input type="text" id="title" name="title"
                               maxlength="200" required>
                    </div>

                    <div>
                        <label for="announcement_date">Announcement Date</label>
                        <input type="date" id="announcement_date"
                               name="announcement_date" required>
                    </div>

                    <div>
                        <label for="audience">Target Audience</label>
                        <select id="audience" name="audience" required>
                            <option value="All Members">All Members</option>
                            <option value="Church Leaders">Church Leaders</option>
                            <option value="Youth">Youth</option>
                            <option value="Women">Women</option>
                            <option value="Men">Men</option>
                            <option value="Children">Children</option>
                        </select>
                    </div>

                    <div>
                        <label for="status">Initial Status</label>
                        <select id="status" name="status" required>
                            <option value="Draft">Save as Draft</option>
                            <option value="Published">Publish Now</option>
                        </select>
                    </div>

                    <div class="full-width">
                        <label for="message">Announcement Details</label>
                        <textarea id="message" name="message"
                                  required></textarea>
                    </div>

                    <div class="full-width">
                        <button type="submit">Save Announcement</button>
                    </div>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Announcement Records</h2>

        <form method="GET" action="announcements.php" class="search-form">
            <input type="search" name="search"
                   placeholder="Search title or message..."
                   value="<?= e($search) ?>">
            <button type="submit" class="search-button">Search</button>
            <a href="announcements.php" class="button">Clear</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Title and Details</th>
                    <th>Audience</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Print</th>
                    <?php if ($canManage): ?>
                        <th>Manage Status</th>
                    <?php endif; ?>
                </tr>
                </thead>

                <tbody>
                <?php if ($announcements->num_rows > 0): ?>
                    <?php while ($row = $announcements->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($row['announcement_date']) ?></td>
                            <td>
                                <strong><?= e($row['title']) ?></strong>
                                <p class="announcement-message"><?= e($row['message']) ?></p>
                            </td>
                            <td><?= e($row['audience']) ?></td>
                            <td>
                                <span class="status <?= e($row['status']) ?>">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td><?= e($row['username'] ?? 'Unknown') ?></td>
                            <td>
                                <a class="button" href="announcement_print.php?id=<?= (int)$row['id'] ?>"
                                   target="_blank" rel="noopener">
                                    Print
                                </a>
                            </td>

                            <?php if ($canManage): ?>
                                <td>
                                    <form method="POST" action="announcements.php"
                                          class="status-form">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action"
                                               value="update_status">
                                        <input type="hidden" name="announcement_id"
                                               value="<?= (int)$row['id'] ?>">

                                        <select name="status" aria-label="Announcement status">
                                            <?php foreach (['Draft', 'Published', 'Archived'] as $statusOption): ?>
                                                <option value="<?= e($statusOption) ?>"
                                                    <?= $row['status'] === $statusOption ? 'selected' : '' ?>>
                                                    <?= e($statusOption) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit">Update</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $canManage ? 7 : 6 ?>">
                            No announcements found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</body>
</html>
<?php
$stmt->close();
?>
