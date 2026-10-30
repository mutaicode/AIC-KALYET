
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

$roles = [
    'user'                => 'Church Member / User',
    'chairperson'         => 'Chairperson',
    'vice_chairperson'    => 'Vice Chairperson',
    'secretary'            => 'Secretary',
    'vice_secretary'       => 'Vice Secretary',
    'treasurer'            => 'Treasurer',
    'projects_officer'     => 'Projects Officer',
    'assets_officer'       => 'Assets Officer',
    'christian_education'  => 'Christian Education Officer',
    'youth_chairperson'    => 'Youth Chairperson',
    'pastor'               => 'Pastor',
    'chairlady'             => 'Chairlady'
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process role assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $newRole = $_POST['role'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $error = 'Security check failed. Refresh the page and try again.';
    } elseif (!$userId || !array_key_exists($newRole, $roles)) {
        $error = 'Invalid user or role selected.';
    } elseif ($userId === (int) $_SESSION['user_id']) {
        $error = 'You cannot change your own role here.';
    } else {
        // Only assign roles to active, non-admin accounts
        $stmt = $conn->prepare(
            "UPDATE users SET role = ?
             WHERE id = ? AND status = 'active' AND role <> 'admin'"
        );
        $stmt->bind_param('si', $newRole, $userId);

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            if ($stmt->affected_rows === 1) {
                $message = 'User role updated successfully.';
            } else {
                $message = 'No changes were made. Check that the account is active.';
            }
        } else {
            $error = 'Unable to update the role.';
        }

        $stmt->close();
    }
}

// Fetch non-admin accounts
$result = $conn->query(
    "SELECT id, username, email, role, status
     FROM users
     WHERE role <> 'admin'
     ORDER BY username ASC"
);

if (!$result) {
    die('Unable to load users. Please check your database connection.');
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
<title>Role Assignment | AIC Kalyet</title>

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
    padding: 24px 30px;
}

header h1 { margin: 0 0 8px; }
header p { margin: 0; color: #d1fae5; }

.container {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 20px;
}

.nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.nav a {
    background: #123b2a;
    color: white;
    text-decoration: none;
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

.error {
    background: #fee2e2;
    color: #991b1b;
}

.info {
    background: white;
    border-left: 4px solid #256b4b;
    padding: 16px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.table-wrap {
    overflow-x: auto;
    background: white;
    border-radius: 10px;
}

table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

th { background: #e2e8f0; }

select, button {
    padding: 9px;
    border-radius: 5px;
}

button {
    border: none;
    background: #166534;
    color: white;
    cursor: pointer;
}

button:hover { background: #14532d; }

.pending { color: #92400e; }
.active { color: #166534; }
.inactive { color: #991b1b; }

@media(max-width: 600px) {
    .container { padding: 0 10px; }
    header { padding: 20px; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<header>
    <h1>Role Assignment</h1>
    <p>Assign official positions in AIC Kalyet Church</p>
</header>

<div class="container">
    <nav class="nav">
        <a href="dashboard.php">Admin Dashboard</a>
        <a href="users.php">User Management</a>
        <a href="../logout.php">Logout</a>
    </nav>

    <div class="info">
        <strong>Important:</strong>
        Only active accounts can be assigned roles. Approve pending
        registrations first in User Management. Admin accounts are excluded
        from this page.
    </div>

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
                    <th>Username</th>
                    <th>Email</th>
                    <th>Current Role</th>
                    <th>Account Status</th>
                    <th>Assign Role</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="5">No user accounts found.</td>
                </tr>
            <?php else: ?>
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($user['username']) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td>
                            <?= e($roles[$user['role']] ?? $user['role']) ?>
                        </td>
                        <td class="<?= e(strtolower($user['status'])) ?>">
                            <?= e(ucfirst($user['status'])) ?>
                        </td>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>">

                                    <input type="hidden" name="user_id"
                                        value="<?= (int) $user['id'] ?>">

                                    <select name="role" required>
                                        <?php foreach ($roles as $value => $label): ?>
                                            <option value="<?= e($value) ?>"
                                                <?= $user['role'] === $value ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit">Save Role</button>
                                </form>
                            <?php else: ?>
                                <span>Activate account first</span>
                            <?php endif; ?>
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
