<?php
require_once __DIR__ . '/config/bootstrap.php';

require_role([
    'admin', 'chairperson', 'secretary', 'vice_secretary',
    'pastor', 'chairlady', 'youth_chairperson', 'treasurer'
]);

$currentRole = strtolower(trim($_SESSION['role']));
$message = '';
$error = '';

if ($flash = flash_get('members')) {
    if ($flash['type'] === 'error') {
        $error = $flash['message'];
    } else {
        $message = $flash['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');
            $gender    = $_POST['gender'] ?? '';
            $phone     = trim($_POST['phone'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $dob       = trim($_POST['date_of_birth'] ?? '');
            $address   = trim($_POST['address'] ?? '');

            if ($firstName === '' || $lastName === '') {
                $error = 'First name and last name are required.';
            } elseif (!in_array($gender, ['Male', 'Female'], true)) {
                $error = 'Please select a valid gender.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    $memberNumber = 'KAL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

                    $stmt = $conn->prepare(
                        "INSERT INTO members
                        (membership_no, first_name, last_name, gender, phone,
                         email, date_of_birth, address, membership_status)
                        VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), 'pending')"
                    );

                    if (!$stmt) {
                        throw new RuntimeException('Prepare failed: ' . $conn->error);
                    }

                    $stmt->bind_param(
                        'ssssssss',
                        $memberNumber, $firstName, $lastName, $gender,
                        $phone, $email, $dob, $address
                    );

                    if (!$stmt->execute()) {
                        throw new RuntimeException('Execute failed: ' . $stmt->error);
                    }

                    $stmt->close();
                    flash_set('members', 'Member registered successfully. Membership number: ' . $memberNumber);
                    header('Location: members.php');
                    exit;
                } catch (Throwable $ex) {
                    app_log('members create', $ex);
                    $error = 'Could not register the member. Please check the database configuration.';
                }
            }
        } elseif ($action === 'approve' || $action === 'deactivate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = $action === 'approve' ? 'active' : 'inactive';

            if (!$id) {
                $error = 'Invalid member selected.';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE members SET membership_status = ? WHERE id = ?");
                    $stmt->bind_param('si', $newStatus, $id);
                    $stmt->execute();
                    $stmt->close();
                    flash_set('members', 'Member status updated to ' . $newStatus . '.');
                    header('Location: members.php');
                    exit;
                } catch (Throwable $ex) {
                    app_log('members status', $ex);
                    $error = 'Could not update member status.';
                }
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'pending', 'active', 'inactive'];

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}

try {
    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR membership_no LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
        $types .= 'ssss';
    }

    if ($statusFilter !== 'all') {
        $where[] = 'membership_status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }

    $sql = "SELECT id, membership_no, first_name, last_name, gender, phone, email,
                   ministry_id, membership_status
            FROM members";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY first_name, last_name';

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $totalMembersResult = $conn->query("SELECT COUNT(*) AS total FROM members");
    $totalMembers = (int) $totalMembersResult->fetch_assoc()['total'];

} catch (Throwable $ex) {
    app_log('members list', $ex);
    http_response_code(500);
    exit('Unable to load member records. Please check the database structure.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Members Management | AIC Kalyet</title>
<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
header { background: #123b2a; color: white; padding: 25px 30px; }
header h1 { margin: 0 0 8px; }
header p { margin: 0; color: #d1fae5; }
.container { max-width: 1250px; margin: 28px auto; padding: 0 20px; }
.nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
.nav a { background: #123b2a; color: white; text-decoration: none; padding: 11px 15px; border-radius: 6px; }
.nav a:hover { background: #166534; }
.summary, .panel { background: white; padding: 22px; border-radius: 10px; margin-bottom: 22px; box-shadow: 0 3px 12px #00000008; }
.layout { display: grid; grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.5fr); gap: 22px; align-items: start; }
label { display: block; font-size: 14px; font-weight: bold; margin: 14px 0 6px; }
input, select, textarea { width: 100%; padding: 11px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
button { border: none; background: #166534; color: white; padding: 12px 16px; border-radius: 6px; cursor: pointer; font: inherit; }
button:hover { background: #14532d; }
.submit { width: 100%; margin-top: 18px; }
.notice { padding: 13px; border-radius: 7px; margin-bottom: 18px; background: #dcfce7; color: #166534; overflow-wrap: anywhere; }
.error { background: #fee2e2; color: #991b1b; }
.search-form { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
.search-form input { flex: 1; min-width: 180px; }
.search-form select { max-width: 180px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
th { background: #e2e8f0; }
.small { color: #64748b; font-size: 12px; margin-top: 4px; }
.status { display: inline-block; padding: 5px 9px; border-radius: 20px; font-size: 12px; }
.status.active { background: #dcfce7; color: #166534; }
.status.pending { background: #fef3c7; color: #92400e; }
.status.inactive { background: #fee2e2; color: #991b1b; }
.actions { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-sm { padding: 6px 10px; font-size: 12px; border-radius: 5px; }
.btn-approve { background: #15803d; }
.btn-deactivate { background: #b45309; }
.empty { text-align: center; padding: 25px; color: #64748b; }
@media (max-width: 850px) { .layout { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>

<header>
    <h1>Members Management</h1>
    <p>AIC Kalyet Church Management System</p>
</header>

<div class="container">
    <nav class="nav">
        <a href="dashboard.php">Main Dashboard</a>
        <?php if ($currentRole === 'admin'): ?>
            <a href="admin/dashboard.php">Admin Dashboard</a>
        <?php elseif ($currentRole === 'chairperson'): ?>
            <a href="chairperson/dashboard.php">Chairperson Dashboard</a>
        <?php elseif ($currentRole === 'secretary'): ?>
            <a href="secretary/dashboard.php">Secretary Dashboard</a>
        <?php elseif ($currentRole === 'pastor'): ?>
            <a href="pastor/dashboard.php">Pastor Dashboard</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </nav>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="summary">
        <p>Total registered members</p>
        <h2><?= number_format($totalMembers) ?></h2>
    </section>

    <div class="layout">
        <section class="panel">
            <h2>Register New Member</h2>
            <form method="POST" action="members.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <label for="first_name">First Name *</label>
                <input id="first_name" name="first_name" maxlength="100" required>

                <label for="last_name">Last Name *</label>
                <input id="last_name" name="last_name" maxlength="100" required>

                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>

                <label for="phone">Phone Number</label>
                <input id="phone" name="phone" maxlength="20" type="tel">

                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" maxlength="150">

                <label for="date_of_birth">Date of Birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date">

                <label for="address">Address / Village</label>
                <textarea id="address" name="address" rows="2" maxlength="255"></textarea>

                <p class="small">A membership number will be generated automatically.</p>
                <button class="submit" type="submit">Register Member</button>
            </form>
        </section>

        <section class="panel">
            <h2>Church Members</h2>

            <form method="GET" action="members.php" class="search-form">
                <input name="search" placeholder="Search name, phone or membership number..."
                       value="<?= e($search) ?>">
                <select name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit">Filter</button>
                <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                    <a href="members.php" style="padding:12px;text-decoration:none;color:#166534;">Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Member No.</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="6" class="empty">No members found.</td></tr>
                    <?php else: ?>
                        <?php while ($member = $result->fetch_assoc()): ?>
                            <?php $status = strtolower($member['membership_status'] ?? 'pending'); ?>
                            <tr>
                                <td><?= e($member['membership_no']) ?></td>
                                <td>
                                    <?= e($member['first_name'] . ' ' . $member['last_name']) ?>
                                    <?php if (!empty($member['email'])): ?>
                                        <div class="small"><?= e($member['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($member['gender']) ?></td>
                                <td><?= e($member['phone']) ?></td>
                                <td><span class="status <?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($status !== 'active'): ?>
                                            <form method="POST" style="display:inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
                                                <button class="btn-sm btn-approve" type="submit">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($status === 'active'): ?>
                                            <form method="POST" style="display:inline"
                                                  onsubmit="return confirm('Deactivate this member?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
                                                <button class="btn-sm btn-deactivate" type="submit">Deactivate</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</body>
</html>