
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));

if (!in_array($role, ['projects_officer', 'admin',], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Projects Officer';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function projectDate($value): string
{
    if (!$value || $value === '0000-00-00') {
        return '';
    }

    return date('Y-m-d', strtotime($value));
}

$statuses = ['Planned', 'Ongoing', 'On Hold', 'Completed', 'Cancelled'];
$message = '';
$error = '';

$form = [
    'id' => '',
    'project_code' => '',
    'project_name' => '',
    'description' => '',
    'project_category' => '',
    'location' => '',
    'start_date' => '',
    'expected_completion_date' => '',
    'actual_completion_date' => '',
    'budget' => '0.00',
    'progress_percentage' => '0',
    'status' => 'Planned',
    'challenges' => '',
    'notes' => ''
];

// Save project
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $idInput = $_POST['id'] ?? '';
        $id = $idInput === ''
            ? null
            : (is_string($idInput)
                ? filter_var($idInput, FILTER_VALIDATE_INT)
                : false);
        $code = trim($_POST['project_code'] ?? '');
        $name = trim($_POST['project_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['project_category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $startDate = trim($_POST['start_date'] ?? '');
        $expectedDate = trim($_POST['expected_completion_date'] ?? '');
        $actualDate = trim($_POST['actual_completion_date'] ?? '');
        $budgetInput = trim($_POST['budget'] ?? '');
        $progressInput = trim($_POST['progress_percentage'] ?? '');
        $status = trim($_POST['status'] ?? 'Planned');
        $challenges = trim($_POST['challenges'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $validDate = static function ($date) {
            if ($date === '') {
                return true;
            }

            $parsed = DateTime::createFromFormat('!Y-m-d', $date);

            return $parsed && $parsed->format('Y-m-d') === $date;
        };

        $budget = filter_var($budgetInput, FILTER_VALIDATE_FLOAT);
        $progress = filter_var(
            $progressInput,
            FILTER_VALIDATE_INT
        );

        if ($id === false || ($id !== null && $id < 1)) {
            $error = 'Invalid project ID.';
        } elseif ($code === '' || $name === '') {
            $error = 'Project code and project name are required.';
        } elseif (strlen($code) > 50 || strlen($name) > 200) {
            $error = 'Project code or name is too long.';
        } elseif ($budget === false || $budget < 0) {
            $error = 'Enter a valid, non-negative project budget.';
        } elseif ($progress === false || $progress < 0 || $progress > 100) {
            $error = 'Progress must be a whole number between 0 and 100.';
        } elseif (!in_array($status, $statuses, true)) {
            $error = 'Please select a valid project status.';
        } elseif (
            !$validDate($startDate) ||
            !$validDate($expectedDate) ||
            !$validDate($actualDate)
        ) {
            $error = 'Please enter valid dates.';
        } elseif (
            $startDate !== '' &&
            $expectedDate !== '' &&
            $expectedDate < $startDate
        ) {
            $error = 'Expected completion cannot be before the start date.';
        } elseif (
            $actualDate !== '' &&
            $startDate !== '' &&
            $actualDate < $startDate
        ) {
            $error = 'Actual completion cannot be before the start date.';
        } elseif ($status === 'Completed' && $actualDate === '') {
            $error = 'Enter the actual completion date for a completed project.';
        } else {
            if ($status === 'Completed') {
                $progress = 100;
            }

            if ($status === 'Cancelled') {
                // Cancellation does not automatically erase project records.
            }

            try {
                if ($id) {
                    $check = $conn->prepare(
                        "SELECT id FROM projects WHERE id = ?"
                    );
                    $check->bind_param('i', $id);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if (!$exists) {
                        throw new RuntimeException('Project not found.');
                    }

                    $stmt = $conn->prepare(
                        "UPDATE projects SET
                            project_code = ?,
                            project_name = ?,
                            description = ?,
                            project_category = ?,
                            location = ?,
                            start_date = NULLIF(?, ''),
                            expected_completion_date = NULLIF(?, ''),
                            actual_completion_date = NULLIF(?, ''),
                            budget = ?,
                            progress_percentage = ?,
                            status = ?,
                            challenges = ?,
                            notes = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        'ssssssssdisssi',
                        $code,
                        $name,
                        $description,
                        $category,
                        $location,
                        $startDate,
                        $expectedDate,
                        $actualDate,
                        $budget,
                        $progress,
                        $status,
                        $challenges,
                        $notes,
                        $id
                    );
                } else {
                    $userId = (int)$_SESSION['user_id'];

                    $stmt = $conn->prepare(
                        "INSERT INTO projects (
                            project_code,
                            project_name,
                            description,
                            project_category,
                            location,
                            start_date,
                            expected_completion_date,
                            actual_completion_date,
                            budget,
                            progress_percentage,
                            status,
                            challenges,
                            notes,
                            recorded_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, NULLIF(?, ''),
                            NULLIF(?, ''), NULLIF(?, ''),
                            ?, ?, ?, ?, ?, ?
                        )"
                    );

                    $stmt->bind_param(
                        'ssssssssdisssi',
                        $code,
                        $name,
                        $description,
                        $category,
                        $location,
                        $startDate,
                        $expectedDate,
                        $actualDate,
                        $budget,
                        $progress,
                        $status,
                        $challenges,
                        $notes,
                        $userId
                    );
                }

                $stmt->execute();
                $stmt->close();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: projects.php?success=1');
                exit;
            } catch (Throwable $ex) {
                error_log('Project register: ' . $ex->getMessage());

                if ($ex instanceof mysqli_sql_exception &&
                    (int)$ex->getCode() === 1062) {
                    $error = 'That project code already exists. Use a different code.';
                } elseif ($ex instanceof RuntimeException) {
                    $error = $ex->getMessage();
                } else {
                    $error = 'Unable to save the project. Check the database settings and try again.';
                }

                $form = [
                    'id' => $id ?: '',
                    'project_code' => $code,
                    'project_name' => $name,
                    'description' => $description,
                    'project_category' => $category,
                    'location' => $location,
                    'start_date' => $startDate,
                    'expected_completion_date' => $expectedDate,
                    'actual_completion_date' => $actualDate,
                    'budget' => $budgetInput,
                    'progress_percentage' => $progressInput,
                    'status' => $status,
                    'challenges' => $challenges,
                    'notes' => $notes
                ];
            }
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Project saved successfully.';
}

// Load project for editing
if ($_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit']) &&
    (int)$_GET['edit'] > 0) {
    try {
        $editId = (int)$_GET['edit'];

        $stmt = $conn->prepare(
            "SELECT id, project_code, project_name, description,
                    project_category, location, start_date,
                    expected_completion_date, actual_completion_date,
                    budget, progress_percentage, status, challenges, notes
             FROM projects WHERE id = ?"
        );

        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $stmt->close();

        if ($project) {
            $form = $project;
            $form['start_date'] = projectDate($project['start_date']);
            $form['expected_completion_date'] =
                projectDate($project['expected_completion_date']);
            $form['actual_completion_date'] =
                projectDate($project['actual_completion_date']);
        } else {
            $error = 'The requested project was not found.';
        }
    } catch (Throwable $ex) {
        error_log('Edit project: ' . $ex->getMessage());
        $error = 'Unable to load this project.';
    }
}

// Search and filter projects
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$projects = [];

try {
    $sql = "SELECT id, project_code, project_name, description, project_category,
                   location, start_date, expected_completion_date,
                   actual_completion_date, budget, amount_spent,
                   progress_percentage, status
            FROM projects
            WHERE 1 = 1";

    $types = '';
    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            project_code LIKE ? OR
            project_name LIKE ? OR
            project_category LIKE ? OR
            location LIKE ?
        )";

        $term = '%' . $search . '%';
        $types .= 'ssss';
        array_push($params, $term, $term, $term, $term);
    }

    if ($filterStatus !== '' &&
        in_array($filterStatus, $statuses, true)) {
        $sql .= " AND status = ?";
        $types .= 's';
        $params[] = $filterStatus;
    }

    $sql .= " ORDER BY created_at DESC, id DESC";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Throwable $ex) {
    error_log('Project listing: ' . $ex->getMessage());
    $error = $error ?: 'Unable to load projects. Please check the projects table.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Register | AIC Kalyet</title>

<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f3f6fb;color:#263449}
a{text-decoration:none}
.sidebar{position:fixed;top:0;left:0;width:245px;height:100vh;padding:25px 16px;background:#17365d;color:white;overflow-y:auto}
.brand{padding:0 10px 25px;border-bottom:1px solid #365477}
.brand h2{font-size:21px;margin:0 0 5px}
.brand p{color:#c9d8eb;font-size:12px;margin:0}
.nav-title{margin:25px 10px 10px;color:#aebfd5;font-size:11px;text-transform:uppercase;letter-spacing:1px}
.nav-link{display:block;padding:12px 13px;margin-bottom:6px;border-radius:7px;color:#e9f0f9;font-size:14px}
.nav-link:hover,.nav-link.active{background:#2b5686;color:white}
.main{margin-left:245px;padding:28px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:24px}
h1{margin:0;color:#17365d;font-size:27px}
.topbar p{margin:6px 0 0;color:#748196}
.profile,.panel{background:white;border:1px solid #e5ebf3;border-radius:12px;padding:20px;margin-bottom:22px}
.profile{padding:12px 15px;margin:0}
.profile span{display:block;color:#748196;font-size:12px;margin-top:4px}
h2{font-size:19px;color:#17365d;margin:0 0 18px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.field{display:flex;flex-direction:column;gap:7px}
.field.full{grid-column:1/-1}
label{font-size:13px;font-weight:bold;color:#43546b}
input,select,textarea{width:100%;padding:11px;border:1px solid #ccd6e2;border-radius:7px;font:inherit;background:#fff}
textarea{min-height:85px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:2px solid #bdd7f5;border-color:#3274b9}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.btn{display:inline-block;border:0;border-radius:7px;padding:11px 16px;background:#1e5a91;color:white;cursor:pointer;font-weight:bold;font-size:13px}
.btn:hover{opacity:.9}
.btn.secondary{background:#e7edf5;color:#263449}
.btn.edit{background:#e4efff;color:#174b80;padding:7px 10px}
.notice{padding:12px 15px;border-radius:8px;margin-bottom:18px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
.filters input{flex:1;min-width:180px}
.filters select{max-width:210px}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1000px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #edf0f5;font-size:13px;vertical-align:top}
th{background:#f4f7fb;color:#53657d}
.status{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:bold;background:#edf2f7}
.status.Ongoing,.status.Completed{background:#dcfce7;color:#166534}
.status.Planned,.status.On-Hold{background:#fef3c7;color:#92400e}
.status.Cancelled{background:#fee2e2;color:#991b1b}
.progress{width:100px;height:8px;background:#e6edf5;border-radius:10px;overflow:hidden;margin-bottom:5px}
.progress span{display:block;height:100%;background:#3479b8}
.empty{text-align:center;padding:25px;background:#f8fafc;color:#78869a;border-radius:8px}
footer{text-align:center;color:#8290a3;font-size:12px;padding:12px}
@media(max-width:850px){.sidebar{position:static;width:100%;height:auto}.main{margin:0;padding:18px}.form-grid{grid-template-columns:1fr}h1{font-size:23px}}
@media print{.sidebar,.no-print,footer{display:none!important}.main{margin:0;padding:0}.panel{box-shadow:none;border:0}body{background:white}}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<aside class="sidebar">
    <div class="brand">
        <h2>AIC Kalyet</h2>
        <p>Church Management System</p>
    </div>
    <p class="nav-title">Projects Management</p>
    <a class="nav-link" href="dashboard.php">🏠 Dashboard</a>
    <a class="nav-link active" href="projects.php">🏗️ Project Register</a>
    <a class="nav-link" href="transactions.php">💰 Contributions & Expenses</a>
    <a class="nav-link" href="project_reports.php">📄 Project Reports</a>
    <p class="nav-title">Church</p>
    <a class="nav-link" href="../dashboard.php">⛪ Main Dashboard</a>
    <a class="nav-link" href="../logout.php">🚪 Logout</a>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <h1>Project Register</h1>
            <p>Register projects and track budgets, timelines and progress.</p>
        </div>
        <div class="profile">
            <strong><?= e($username); ?></strong>
            <span>Projects Management</span>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="notice success"><?= e($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error); ?></div>
    <?php endif; ?>

    <section class="panel" id="project-form">
        <h2><?= $form['id'] ? '✏️ Edit Project' : '➕ Register New Project'; ?></h2>

        <form method="POST" action="projects.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?= e($form['id']); ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="project_code">Project Code *</label>
                    <input id="project_code" name="project_code" maxlength="50" required
                           placeholder="e.g. PRJ-001" value="<?= e($form['project_code']); ?>">
                </div>

                <div class="field">
                    <label for="project_name">Project Name *</label>
                    <input id="project_name" name="project_name" maxlength="200" required
                           placeholder="e.g. Church Hall Construction"
                           value="<?= e($form['project_name']); ?>">
                </div>

                <div class="field">
                    <label for="project_category">Category</label>
                    <input id="project_category" name="project_category"
                           placeholder="Construction, Water, Education..."
                           value="<?= e($form['project_category']); ?>">
                </div>

                <div class="field">
                    <label for="location">Project Location</label>
                    <input id="location" name="location"
                           value="<?= e($form['location']); ?>">
                </div>

                <div class="field">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                           value="<?= e($form['start_date']); ?>">
                </div>

                <div class="field">
                    <label for="expected_completion_date">Expected Completion Date</label>
                    <input type="date" id="expected_completion_date"
                           name="expected_completion_date"
                           value="<?= e($form['expected_completion_date']); ?>">
                </div>

                <div class="field">
                    <label for="actual_completion_date">Actual Completion Date</label>
                    <input type="date" id="actual_completion_date"
                           name="actual_completion_date"
                           value="<?= e($form['actual_completion_date']); ?>">
                </div>

                <div class="field">
                    <label for="budget">Approved/Planned Budget (KSh) *</label>
                    <input type="number" id="budget" name="budget" min="0"
                           step="0.01" required value="<?= e($form['budget']); ?>">
                </div>

                <div class="field">
                    <label for="progress_percentage">Progress (%)</label>
                    <input type="number" id="progress_percentage"
                           name="progress_percentage" min="0" max="100"
                           step="1" required
                           value="<?= e($form['progress_percentage']); ?>">
                </div>

                <div class="field">
                    <label for="status">Project Status *</label>
                    <select id="status" name="status" required>
                        <?php foreach ($statuses as $statusOption): ?>
                            <option value="<?= e($statusOption); ?>"
                                <?= $form['status'] === $statusOption ? 'selected' : ''; ?>>
                                <?= e($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="description">Project Description</label>
                    <textarea id="description" name="description"><?= e($form['description']); ?></textarea>
                </div>

                <div class="field full">
                    <label for="challenges">Challenges / Issues</label>
                    <textarea id="challenges" name="challenges"><?= e($form['challenges']); ?></textarea>
                </div>

                <div class="field full">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes"><?= e($form['notes']); ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn">
                    <?= $form['id'] ? '💾 Update Project' : '➕ Save Project'; ?>
                </button>

                <?php if ($form['id']): ?>
                    <a class="btn secondary" href="projects.php">Cancel Editing</a>
                <?php else: ?>
                    <button type="reset" class="btn secondary">Clear Form</button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>📋 Registered Projects (<?= count($projects); ?>)</h2>

        <form method="GET" action="projects.php" class="filters no-print">
            <input type="text" name="search" placeholder="Search code, name, category or location..."
                   value="<?= e($search); ?>">

            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $statusOption): ?>
                    <option value="<?= e($statusOption); ?>"
                        <?= $filterStatus === $statusOption ? 'selected' : ''; ?>>
                        <?= e($statusOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn" type="submit">Search / Filter</button>
            <a class="btn secondary" href="projects.php">Reset</a>
            <button class="btn secondary" type="button" onclick="window.print()">Print</button>
        </form>

        <?php if ($projects): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Code</th>
                        <th>Project</th>
                        <th>Category / Location</th>
                        <th>Budget</th>
                        <th>Spent</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Expected Completion</th>
                        <th class="no-print">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $progress = max(0, min(100, (int)$project['progress_percentage']));
                        $statusClass = str_replace(' ', '-', $project['status']);
                        ?>
                        <tr>
                            <td><?= e($project['project_code']); ?></td>
                            <td>
                                <strong><?= e($project['project_name']); ?></strong>
                                <?php if ($project['description']): ?>
                                    <br><small><?= e($project['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($project['project_category'] ?: 'General'); ?><br>
                                <small><?= e($project['location'] ?: 'No location'); ?></small>
                            </td>
                            <td>KSh <?= number_format((float)$project['budget'], 2); ?></td>
                            <td>KSh <?= number_format((float)$project['amount_spent'], 2); ?></td>
                            <td>
                                <div class="progress">
                                    <span style="width:<?= $progress; ?>%"></span>
                                </div>
                                <?= $progress; ?>%
                            </td>
                            <td>
                                <span class="status <?= e($statusClass); ?>">
                                    <?= e($project['status']); ?>
                                </span>
                            </td>
                            <td><?= e($project['expected_completion_date'] ? date('d M Y', strtotime($project['expected_completion_date'])) : 'Not set'); ?></td>
                            <td class="no-print">
                                <a class="btn edit"
                                   href="projects.php?edit=<?= (int)$project['id']; ?>#project-form">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                No projects found. Register a new project above, or change your search filters.
            </div>
        <?php endif; ?>
    </section>

    <footer>&copy; <?= date('Y'); ?> AIC Kalyet Church Management System</footer>
</main>
</body>
</html>
