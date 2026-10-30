
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
$editLesson = null;

$allowedStatuses = ['Draft', 'Published', 'Archived'];

if (isset($_GET['edit'])) {
    $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($editId) {
        $stmt = $conn->prepare("SELECT * FROM ce_lessons WHERE id = ?");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editLesson = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $classId = (int)($_POST['class_id'] ?? 0);
        $title = trim($_POST['lesson_title'] ?? '');
        $reference = trim($_POST['bible_reference'] ?? '');
        $lessonDate = trim($_POST['lesson_date'] ?? '');
        $teacher = trim($_POST['teacher_name'] ?? '');
        $notes = trim($_POST['lesson_notes'] ?? '');
        $status = $_POST['status'] ?? 'Draft';

        $dateValid = DateTime::createFromFormat('!Y-m-d', $lessonDate);
        $dateValid = $dateValid && $dateValid->format('Y-m-d') === $lessonDate;

        if ($title === '') {
            $error = 'Please enter the lesson title.';
        } elseif (!$dateValid) {
            $error = 'Please enter a valid lesson date.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $error = 'Please choose a valid publication status.';
        } else {
            $classId = $classId > 0 ? $classId : null;
            $reference = $reference === '' ? null : $reference;
            $teacher = $teacher === '' ? null : $teacher;
            $notes = $notes === '' ? null : $notes;

            if ($classId !== null) {
                $check = $conn->prepare(
                    "SELECT id FROM ce_classes WHERE id = ?"
                );
                $check->bind_param('i', $classId);
                $check->execute();
                $classExists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$classExists) {
                    $error = 'The selected class does not exist.';
                }
            }

            if ($error === '') {
                if ($id > 0) {
                    $stmt = $conn->prepare("
                        UPDATE ce_lessons
                        SET class_id = ?, lesson_title = ?,
                            bible_reference = ?, lesson_date = ?,
                            lesson_notes = ?, teacher_name = ?, status = ?
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        'issssssi',
                        $classId,
                        $title,
                        $reference,
                        $lessonDate,
                        $notes,
                        $teacher,
                        $status,
                        $id
                    );
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO ce_lessons
                        (class_id, lesson_title, bible_reference,
                         lesson_date, lesson_notes, teacher_name,
                         status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        'issssssi',
                        $classId,
                        $title,
                        $reference,
                        $lessonDate,
                        $notes,
                        $teacher,
                        $status,
                        $userId
                    );
                }

                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: lessons.php?saved=1');
                    exit;
                }

                $error = 'Unable to save the lesson. Please check your database.';
                $stmt->close();
            }
        }
    }

    if ($action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if ($id < 1 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = 'Invalid lesson or status.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE ce_lessons SET status = ? WHERE id = ?"
            );
            $stmt->bind_param('si', $newStatus, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: lessons.php?status_updated=1');
                exit;
            }

            $error = 'Unable to update the lesson status.';
            $stmt->close();
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Lesson saved successfully.';
}

if (isset($_GET['status_updated'])) {
    $message = 'Lesson status updated successfully.';
}

$classes = [];
$classResult = $conn->query("
    SELECT id, class_name
    FROM ce_classes
    WHERE status = 'Active'
    ORDER BY class_name
");

if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'All';

if (!in_array($statusFilter, ['All', 'Draft', 'Published', 'Archived'], true)) {
    $statusFilter = 'All';
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(
        l.lesson_title LIKE ?
        OR l.bible_reference LIKE ?
        OR l.teacher_name LIKE ?
        OR c.class_name LIKE ?
    )";

    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}

if ($statusFilter !== 'All') {
    $where[] = 'l.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "
    SELECT l.*, c.class_name
    FROM ce_lessons l
    LEFT JOIN ce_classes c ON c.id = l.class_id
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY l.lesson_date DESC, l.id DESC';

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$lessons = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lessons | A.I.C Kalyet</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    background: #f1f5f9;
    color: #1e293b;
    font-family: Arial, Helvetica, sans-serif;
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
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 22px;
}

h1, h2 { color: #123a66; }
h1 { margin: 0 0 8px; }
.header p { color: #64748b; margin: 0; }

.panel {
    background: white;
    padding: 22px;
    border-radius: 11px;
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

.full { grid-column: 1 / -1; }

label { font-size: 13px; font-weight: bold; }

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: white;
    font: inherit;
}

textarea { min-height: 100px; resize: vertical; }

.btn {
    display: inline-block;
    padding: 10px 14px;
    background: #164e8a;
    color: white;
    text-decoration: none;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
}

.btn:hover { background: #0f3764; }
.btn-gray { background: #475569; }
.btn-green { background: #15803d; }
.btn-orange { background: #b45309; }
.btn-red { background: #b91c1c; }

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.message, .error {
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.message { background: #dcfce7; color: #166534; }
.error { background: #fee2e2; color: #991b1b; }

.filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: end;
    margin-bottom: 20px;
}

.filters .field { min-width: 180px; }

.table-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }

th, td {
    padding: 12px 10px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

th { background: #eaf1f8; color: #123a66; }

.badge {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.badge.Published { background: #dcfce7; color: #166534; }
.badge.Draft { background: #fef3c7; color: #92400e; }
.badge.Archived { background: #e2e8f0; color: #475569; }

.inline-form { display: inline; }

@media (max-width: 650px) {
    .container { padding: 14px; }
    .form-grid { grid-template-columns: 1fr; }
    .full { grid-column: auto; }
}
</style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Lessons & Bible Study</h1>
            <p>Create, organize, and publish Christian education lessons.</p>
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
        <h2><?= $editLesson ? 'Edit Lesson' : 'Add New Lesson'; ?></h2>

        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"
                   value="<?= (int)($editLesson['id'] ?? 0); ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="lesson_title">Lesson Title *</label>
                    <input id="lesson_title" name="lesson_title"
                           required maxlength="200"
                           value="<?= e($editLesson['lesson_title'] ?? ''); ?>"
                           placeholder="e.g. The Good Samaritan">
                </div>

                <div class="field">
                    <label for="class_id">Class</label>
                    <select id="class_id" name="class_id">
                        <option value="0">General / All Classes</option>

                        <?php foreach ($classes as $class): ?>
                            <option value="<?= (int)$class['id']; ?>"
                                <?= (int)($editLesson['class_id'] ?? 0) === (int)$class['id']
                                    ? 'selected' : ''; ?>>
                                <?= e($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="bible_reference">Bible Reference</label>
                    <input id="bible_reference" name="bible_reference"
                           maxlength="200"
                           value="<?= e($editLesson['bible_reference'] ?? ''); ?>"
                           placeholder="e.g. Luke 10:25–37">
                </div>

                <div class="field">
                    <label for="lesson_date">Lesson Date *</label>
                    <input type="date" id="lesson_date" name="lesson_date"
                           required
                           value="<?= e($editLesson['lesson_date'] ?? date('Y-m-d')); ?>">
                </div>

                <div class="field">
                    <label for="teacher_name">Teacher</label>
                    <input id="teacher_name" name="teacher_name"
                           maxlength="150"
                           value="<?= e($editLesson['teacher_name'] ?? ''); ?>"
                           placeholder="Enter teacher's name">
                </div>

                <div class="field">
                    <label for="status">Publication Status</label>
                    <select id="status" name="status">
                        <?php foreach ($allowedStatuses as $option): ?>
                            <option value="<?= e($option); ?>"
                                <?= ($editLesson['status'] ?? 'Draft') === $option
                                    ? 'selected' : ''; ?>>
                                <?= e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="lesson_notes">Lesson Notes / Outline</label>
                    <textarea id="lesson_notes" name="lesson_notes"
                              placeholder="Enter lesson objectives, key points, and teaching notes"><?= e($editLesson['lesson_notes'] ?? ''); ?></textarea>
                </div>

            </div>

            <br>

            <div class="actions">
                <button type="submit" class="btn btn-green">
                    <?= $editLesson ? 'Update Lesson' : 'Save Lesson'; ?>
                </button>

                <?php if ($editLesson): ?>
                    <a href="lessons.php" class="btn btn-gray">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Recorded Lessons</h2>

        <form method="GET" class="filters">
            <div class="field">
                <label for="search">Search</label>
                <input id="search" name="search"
                       value="<?= e($search); ?>"
                       placeholder="Lesson, Bible reference, teacher...">
            </div>

            <div class="field">
                <label for="filter_status">Status</label>
                <select id="filter_status" name="status">
                    <?php foreach (['All', 'Draft', 'Published', 'Archived'] as $option): ?>
                        <option value="<?= e($option); ?>"
                            <?= $statusFilter === $option ? 'selected' : ''; ?>>
                            <?= e($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn" type="submit">Search / Filter</button>
            <a class="btn btn-gray" href="lessons.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Lesson</th>
                    <th>Class</th>
                    <th>Date</th>
                    <th>Teacher</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$lessons): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:25px;">
                            No lessons found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>

                            <td>
                                <strong><?= e($lesson['lesson_title']); ?></strong><br>
                                <small><?= e($lesson['bible_reference'] ?: 'No Bible reference'); ?></small>
                            </td>

                            <td><?= e($lesson['class_name'] ?: 'General / All Classes'); ?></td>

                            <td><?= e(date('d M Y', strtotime($lesson['lesson_date']))); ?></td>

                            <td><?= e($lesson['teacher_name'] ?: 'Not assigned'); ?></td>

                            <td>
                                <span class="badge <?= e($lesson['status']); ?>">
                                    <?= e($lesson['status']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">
                                    <a class="btn"
                                       href="lessons.php?edit=<?= (int)$lesson['id']; ?>">
                                        Edit
                                    </a>

                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action"
                                               value="change_status">
                                        <input type="hidden" name="id"
                                               value="<?= (int)$lesson['id']; ?>">

                                        <select name="new_status"
                                                aria-label="New lesson status">
                                            <?php foreach ($allowedStatuses as $option): ?>
                                                <option value="<?= e($option); ?>"
                                                    <?= $lesson['status'] === $option
                                                        ? 'selected' : ''; ?>>
                                                    <?= e($option); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button class="btn btn-orange" type="submit">
                                            Update Status
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
