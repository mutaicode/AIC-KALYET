
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!in_array($role, ['christian_education', 'admin'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

$editClass = null;

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId) {
        $stmt = $conn->prepare("SELECT * FROM ce_classes WHERE id = ?");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editClass = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $className = trim($_POST['class_name'] ?? '');
        $ageGroup = trim($_POST['age_group'] ?? '');
        $teacherName = trim($_POST['teacher_name'] ?? '');
        $meetingDay = trim($_POST['meeting_day'] ?? 'Sunday');
        $meetingTime = trim($_POST['meeting_time'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $validDays = [
            'Sunday', 'Monday', 'Tuesday', 'Wednesday',
            'Thursday', 'Friday', 'Saturday'
        ];

        if ($className === '') {
            $error = 'Please enter the class name.';
        } elseif (!in_array($meetingDay, $validDays, true)) {
            $error = 'Please choose a valid meeting day.';
        } elseif ($meetingTime !== '' &&
            !preg_match('/^\d{2}:\d{2}$/', $meetingTime)) {
            $error = 'Please enter a valid meeting time.';
        } else {
            $meetingTime = $meetingTime === '' ? null : $meetingTime;
            $ageGroup = $ageGroup === '' ? null : $ageGroup;
            $teacherName = $teacherName === '' ? null : $teacherName;
            $location = $location === '' ? null : $location;
            $description = $description === '' ? null : $description;

            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE ce_classes
                    SET class_name = ?, age_group = ?, teacher_name = ?,
                        meeting_day = ?, meeting_time = ?, location = ?,
                        description = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    'sssssssi',
                    $className,
                    $ageGroup,
                    $teacherName,
                    $meetingDay,
                    $meetingTime,
                    $location,
                    $description,
                    $id
                );
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO ce_classes
                    (class_name, age_group, teacher_name, meeting_day,
                     meeting_time, location, description, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'sssssssi',
                    $className,
                    $ageGroup,
                    $teacherName,
                    $meetingDay,
                    $meetingTime,
                    $location,
                    $description,
                    $userId
                );
            }

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: classes.php?success=1');
                exit;
            }

            $error = 'Unable to save the class. Please check whether the class already exists or review the database error.';
            $stmt->close();
        }
    }

    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE ce_classes
                SET status = CASE
                    WHEN status = 'Active' THEN 'Inactive'
                    ELSE 'Active'
                END
                WHERE id = ?
            ");

            $stmt->bind_param('i', $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: classes.php?status_updated=1');
                exit;
            }

            $error = 'Unable to update class status.';
            $stmt->close();
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Class saved successfully.';
}

if (isset($_GET['status_updated'])) {
    $message = 'Class status updated successfully.';
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';

if (!in_array($statusFilter, ['All', 'Active', 'Inactive'], true)) {
    $statusFilter = 'All';
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(class_name LIKE ? OR age_group LIKE ? OR teacher_name LIKE ? OR location LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}

if ($statusFilter !== 'All') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "SELECT * FROM ce_classes";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY status ASC, class_name ASC';

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$days = [
    'Sunday', 'Monday', 'Tuesday', 'Wednesday',
    'Thursday', 'Friday', 'Saturday'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Classes | A.I.C Kalyet</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.container {
    max-width: 1250px;
    margin: auto;
    padding: 25px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

h1, h2 { color: #123a66; }

h1 { margin: 0 0 8px; }

.header p { color: #64748b; margin: 0; }

.panel {
    background: white;
    border-radius: 11px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(15,23,42,.05);
}

.panel h2 { margin-top: 0; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field.full { grid-column: 1 / -1; }

label { font-size: 13px; font-weight: bold; }

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font: inherit;
    background: white;
}

textarea { resize: vertical; min-height: 90px; }

.btn {
    display: inline-block;
    border: 0;
    border-radius: 6px;
    padding: 10px 14px;
    color: white;
    background: #164e8a;
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover { background: #0f3764; }
.btn-gray { background: #475569; }
.btn-green { background: #15803d; }
.btn-orange { background: #b45309; }

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.message {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 18px;
    background: #dcfce7;
    color: #166534;
}

.error {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 18px;
    background: #fee2e2;
    color: #991b1b;
}

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: end;
    margin-bottom: 20px;
}

.filters .field { min-width: 180px; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
    vertical-align: top;
}

th { background: #eaf1f8; color: #123a66; }

.badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge.Active { background: #dcfce7; color: #166534; }
.badge.Inactive { background: #fee2e2; color: #991b1b; }

.inline-form { display: inline; }

@media (max-width: 650px) {
    .container { padding: 14px; }
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<div class="container">

    <div class="header">
        <div>
            <h1>Manage Christian Education Classes</h1>
            <p>Register and manage Sunday school classes and Bible study groups.</p>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn btn-gray">Back to Dashboard</a>
            <a href="../dashboard.php" class="btn btn-gray">Church Dashboard</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= e($error); ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2><?= $editClass ? 'Edit Class' : 'Register New Class'; ?></h2>

        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"
                   value="<?= (int)($editClass['id'] ?? 0); ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="class_name">Class Name *</label>
                    <input id="class_name" name="class_name" required
                           maxlength="150"
                           value="<?= e($editClass['class_name'] ?? ''); ?>"
                           placeholder="e.g. Beginner Sunday School">
                </div>

                <div class="field">
                    <label for="age_group">Age Group</label>
                    <input id="age_group" name="age_group" maxlength="100"
                           value="<?= e($editClass['age_group'] ?? ''); ?>"
                           placeholder="e.g. Children aged 4–7">
                </div>

                <div class="field">
                    <label for="teacher_name">Teacher Name</label>
                    <input id="teacher_name" name="teacher_name" maxlength="150"
                           value="<?= e($editClass['teacher_name'] ?? ''); ?>"
                           placeholder="Enter teacher's name">
                </div>

                <div class="field">
                    <label for="meeting_day">Meeting Day</label>
                    <select id="meeting_day" name="meeting_day" required>
                        <?php foreach ($days as $day): ?>
                            <option value="<?= e($day); ?>"
                                <?= ($editClass['meeting_day'] ?? 'Sunday') === $day
                                    ? 'selected' : ''; ?>>
                                <?= e($day); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="meeting_time">Meeting Time</label>
                    <input type="time" id="meeting_time" name="meeting_time"
                           value="<?= e(isset($editClass['meeting_time'])
                               ? substr($editClass['meeting_time'], 0, 5)
                               : ''); ?>">
                </div>

                <div class="field">
                    <label for="location">Class Location</label>
                    <input id="location" name="location" maxlength="150"
                           value="<?= e($editClass['location'] ?? ''); ?>"
                           placeholder="e.g. Main Church Hall">
                </div>

                <div class="field full">
                    <label for="description">Description / Notes</label>
                    <textarea id="description" name="description"
                              placeholder="Enter additional class details"><?= e($editClass['description'] ?? ''); ?></textarea>
                </div>

            </div>

            <br>

            <div class="actions">
                <button class="btn btn-green" type="submit">
                    <?= $editClass ? 'Update Class' : 'Save Class'; ?>
                </button>

                <?php if ($editClass): ?>
                    <a href="classes.php" class="btn btn-gray">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Registered Classes</h2>

        <form method="GET" class="filters">
            <div class="field">
                <label for="search">Search Classes</label>
                <input id="search" name="search"
                       value="<?= e($search); ?>"
                       placeholder="Name, teacher, age group...">
            </div>

            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (['All', 'Active', 'Inactive'] as $option): ?>
                        <option value="<?= e($option); ?>"
                            <?= $statusFilter === $option ? 'selected' : ''; ?>>
                            <?= e($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">Search / Filter</button>
            <a href="classes.php" class="btn btn-gray">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Class</th>
                    <th>Teacher</th>
                    <th>Meeting Schedule</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$classes): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:25px;">
                            No classes found. Register your first class above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $index => $class): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>

                            <td>
                                <strong><?= e($class['class_name']); ?></strong><br>
                                <small><?= e($class['age_group'] ?: 'Age group not set'); ?></small>
                            </td>

                            <td><?= e($class['teacher_name'] ?: 'Not assigned'); ?></td>

                            <td>
                                <?= e($class['meeting_day']); ?><br>
                                <?= e($class['meeting_time']
                                    ? date('h:i A', strtotime($class['meeting_time']))
                                    : 'Time not set'); ?>
                            </td>

                            <td><?= e($class['location'] ?: 'Not specified'); ?></td>

                            <td>
                                <span class="badge <?= e($class['status']); ?>">
                                    <?= e($class['status']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">
                                    <a class="btn"
                                       href="classes.php?edit=<?= (int)$class['id']; ?>">
                                        Edit
                                    </a>

                                    <form method="POST" class="inline-form"
                                          onsubmit="return confirm('Change this class status?');">
                                        <input type="hidden" name="action"
                                               value="toggle_status">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$class['id']; ?>">

                                        <button type="submit"
                                                class="btn <?= $class['status'] === 'Active'
                                                    ? 'btn-orange' : 'btn-green'; ?>">
                                            <?= $class['status'] === 'Active'
                                                ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>