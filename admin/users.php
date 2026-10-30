
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Admin-only access
if (
    !isset($_SESSION['user_id']) ||
    strtolower(trim($_SESSION['role'] ?? '')) !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Process account status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $error = 'Security check failed. Refresh the page and try again.';
    } elseif (!$userId || !in_array($newStatus, ['active', 'pending', 'inactive'], true)) {
        $error = 'Invalid account details.';
    } elseif ($userId === (int) $_SESSION['user_id']) {
        $error = 'You cannot change your own account status here.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET status = ? WHERE id = ? AND role <> 'admin'"
        );
        $stmt->bind_param('si', $newStatus, $userId);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $message = 'Account status updated.';
        } else {
            $error = 'Unable to update account status.';
        }

        $stmt->close();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch users, excluding admin accounts
$result = $conn->query(
    "SELECT id, username, email, role, status, created_at
     FROM users
     WHERE role <> 'admin'
     ORDER BY
        CASE WHEN status = 'pending' THEN 0 ELSE 1 END,
        created_at DESC"
);

if (!$result) {
    die('Unable to load users. Please check the database connection.');
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | AIC Kalyet</title>

<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}
header {
    background: #123b2a;
    color: white;
    padding: 22px 30px;
}
header h1 { margin: 0 0 8px; }
header p { margin: 0; color: #d1fae5; }
.container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.nav { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.nav a {
    text-decoration: none;
    background: #123b2a;
    color: white;
    padding: 11px 15px;
    border-radius: 6px;
}
.notice {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
    background: #dcfce7;
    color: #166534;
}
.error { background: #fee2e2; color: #991b1b; }
.table-wrap {
    overflow-x: auto;
    background: white;
    border-radius: 10px;
    box-shadow: 0 3px 12px #0000000a;
}
table { width: 100%; border-collapse: collapse; min-width: 850px; }
th, td { padding: 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
th { background: #e2e8f0; }
.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background: #e2e8f0;
    font-size: 12px;
}
.status.active { background: #dcfce7; color: #166534; }
.status.pending { background: #fef3c7; color: #92400e; }
.status.inactive { background: #fee2e2; color: #991b1b; }
select, button { padding: 8px; border-radius: 5px; }
button {
    background: #166534;
    color: white;
    border: 0;
    cursor: pointer;
}
@media(max-width: 600px) {
    header { padding: 20px; }
    .container { padding: 0 10px; }
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<header>
    <h1>User Management</h1>
    <p>AIC Kalyet Church Management System</p>
</header>

<div class="container">
    <nav class="nav">
        <a href="dashboard.php">Admin Dashboard</a>
        <a href="roles.php">Role Assignment</a>
        <a href="../logout.php">Logout</a>
    </nav>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Manage Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="7">No other user accounts found.</td></tr>
            <?php else: ?>
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= e($user['username']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e($user['role']) ?></td>
                        <td>
                            <span class="status <?= e(strtolower($user['status'])) ?>">
                                <?= e(ucfirst($user['status'])) ?>
                            </span>
                        </td>
                        <td><?= e($user['created_at']) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id"
                                    value="<?= (int) $user['id'] ?>">

                                <select name="status" required>
                                    <option value="pending"
                                        <?= $user['status'] === 'pending' ? 'selected' : '' ?>>
                                        Pending
                                    </option>
                                    <option value="active"
                                        <?= $user['status'] === 'active' ? 'selected' : '' ?>>
                                        Active
                                    </option>
                                    <option value="inactive"
                                        <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>
                                        Inactive
                                    </option>
                                </select>

                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
