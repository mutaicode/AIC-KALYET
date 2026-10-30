
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));

if (!in_array($role, ['assets_officer', 'admin'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$editAsset = null;

$validConditions = ['Good', 'Fair', 'Needs Repair', 'Damaged'];
$validStatuses = ['Active', 'Under Maintenance', 'Disposed'];

/* Register or update an asset */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $assetId = filter_input(INPUT_POST, 'asset_id', FILTER_VALIDATE_INT);
    $assetCode = trim($_POST['asset_code'] ?? '');
    $assetName = trim($_POST['asset_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $acquisitionDate = trim($_POST['acquisition_date'] ?? '');
    $condition = $_POST['condition_status'] ?? 'Good';
    $assignedTo = trim($_POST['assigned_to'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($action, ['create', 'update', 'dispose'], true)) {
        $error = 'Invalid action.';
    } elseif ($action === 'dispose') {
        if (!$assetId) {
            $error = 'Invalid asset selected.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE assets SET status = 'Disposed' WHERE id = ?"
            );
            $stmt->bind_param('i', $assetId);

            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $message = 'Asset marked as disposed.';
            } else {
                $error = 'Could not update the asset.';
            }
            $stmt->close();
        }
    } elseif (
        $assetCode === '' || $assetName === '' || $category === ''
        || !in_array($condition, $validConditions, true)
        || !in_array($status, $validStatuses, true)
    ) {
        $error = 'Enter the asset code, name, category, and valid status values.';
    } elseif (
        $acquisitionDate !== ''
        && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $acquisitionDate)
    ) {
        $error = 'Enter a valid acquisition date.';
    } elseif ($action === 'update' && !$assetId) {
        $error = 'Invalid asset selected for editing.';
    } else {
        $acquisitionDate = $acquisitionDate !== '' ? $acquisitionDate : null;

        try {
            if ($action === 'create') {
                $stmt = $conn->prepare(
                    "INSERT INTO assets
                    (asset_code, asset_name, category, description, location,
                     acquisition_date, condition_status, assigned_to, status,
                     notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $userId = (int)$_SESSION['user_id'];

                $stmt->bind_param(
                    'ssssssssssi',
                    $assetCode, $assetName, $category, $description,
                    $location, $acquisitionDate, $condition, $assignedTo,
                    $status, $notes, $userId
                );

                $stmt->execute();
                $stmt->close();
                $message = 'Asset registered successfully.';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE assets SET
                        asset_code = ?, asset_name = ?, category = ?,
                        description = ?, location = ?, acquisition_date = ?,
                        condition_status = ?, assigned_to = ?, status = ?,
                        notes = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    'ssssssssssi',
                    $assetCode, $assetName, $category, $description,
                    $location, $acquisitionDate, $condition, $assignedTo,
                    $status, $notes, $assetId
                );

                $stmt->execute();
                $stmt->close();
                $message = 'Asset updated successfully.';
            }
        } catch (mysqli_sql_exception $ex) {
            error_log('Asset save error: ' . $ex->getMessage());

            if ((int)$ex->getCode() === 1062) {
                $error = 'That asset code already exists. Use a unique code.';
            } else {
                $error = 'The asset could not be saved. Check the details and try again.';
            }
        }
    }
}

/* Load asset for editing */
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editAsset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* Search and filter */
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT id, asset_code, asset_name, category, location,
               acquisition_date, condition_status, assigned_to, status
        FROM assets WHERE 1=1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (asset_code LIKE ? OR asset_name LIKE ? OR category LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
    $types .= 'sss';
}

if (in_array($filterStatus, $validStatuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$formAsset = $editAsset ?? [
    'id' => '',
    'asset_code' => '',
    'asset_name' => '',
    'category' => '',
    'description' => '',
    'location' => '',
    'acquisition_date' => '',
    'condition_status' => 'Good',
    'assigned_to' => '',
    'status' => 'Active',
    'notes' => ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Register | AIC Kalyet</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f6fb;
    color: #263449;
}

header {
    background: #17365d;
    color: white;
    padding: 20px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

header h1 { margin: 0; font-size: 23px; }
header a { color: white; text-decoration: none; }

.container {
    max-width: 1250px;
    width: 94%;
    margin: 25px auto;
}

.panel {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

h2 { color: #17365d; margin-top: 0; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field.full { grid-column: 1 / -1; }

label { font-size: 14px; font-weight: bold; }

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccd6e3;
    border-radius: 7px;
    font: inherit;
    background: white;
}

textarea { min-height: 85px; resize: vertical; }

button, .btn {
    border: 0;
    background: #2468a6;
    color: white;
    padding: 11px 16px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary { background: #64748b; }
.btn-warning { background: #b45309; }
.btn-danger { background: #b91c1c; }

.message, .error {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.message { background: #dcfce7; color: #166534; }
.error { background: #fee2e2; color: #991b1b; }

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.filters input { flex: 1; min-width: 180px; }
.filters select { max-width: 220px; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

th, td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #e5ebf3;
    font-size: 13px;
}

th { background: #edf3fa; color: #17365d; }

.actions {
    display: flex;
    gap: 7px;
    align-items: center;
}

.actions form { margin: 0; }

.actions button, .actions a {
    padding: 7px 10px;
    font-size: 12px;
    white-space: nowrap;
}

.status {
    padding: 4px 8px;
    border-radius: 20px;
    background: #e2e8f0;
    font-size: 11px;
    white-space: nowrap;
}

@media (max-width: 650px) {
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .panel { padding: 16px; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<header>
    <h1>📦 Church Asset Register</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a> |
        <a href="maintenance.php">Maintenance</a> |
        <a href="asset_reports.php">Reports</a> |
        <a href="../dashboard.php">Main Dashboard</a>
    </nav>
</header>

<main class="container">

    <section class="panel">
        <h2><?= $editAsset ? 'Edit Asset' : 'Register a New Asset'; ?></h2>

        <?php if ($message): ?>
            <div class="message"><?= e($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action"
                   value="<?= $editAsset ? 'update' : 'create'; ?>">

            <input type="hidden" name="asset_id"
                   value="<?= e($formAsset['id']); ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="asset_code">Asset Code *</label>
                    <input id="asset_code" name="asset_code" required
                           maxlength="50"
                           placeholder="e.g. AIC-001"
                           value="<?= e($formAsset['asset_code']); ?>">
                </div>

                <div class="field">
                    <label for="asset_name">Asset Name *</label>
                    <input id="asset_name" name="asset_name" required
                           maxlength="150"
                           placeholder="e.g. Church Laptop"
                           value="<?= e($formAsset['asset_name']); ?>">
                </div>

                <div class="field">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <?php
                        $categories = [
                            'Furniture', 'Electronics', 'Sound Equipment',
                            'Musical Instruments', 'Office Equipment',
                            'Buildings', 'Land', 'Vehicles', 'Other'
                        ];
                        foreach ($categories as $categoryOption):
                        ?>
                            <option value="<?= e($categoryOption); ?>"
                                <?= $formAsset['category'] === $categoryOption ? 'selected' : ''; ?>>
                                <?= e($categoryOption); ?>
                            </option>
                        <?php endforeach; ?>

                        <?php if (
                            $formAsset['category'] !== ''
                            && !in_array($formAsset['category'], $categories, true)
                        ): ?>
                            <option value="<?= e($formAsset['category']); ?>" selected>
                                <?= e($formAsset['category']); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="location">Location</label>
                    <input id="location" name="location" maxlength="150"
                           placeholder="e.g. Church office"
                           value="<?= e($formAsset['location']); ?>">
                </div>

                <div class="field">
                    <label for="acquisition_date">Acquisition Date</label>
                    <input type="date" id="acquisition_date"
                           name="acquisition_date"
                           value="<?= e($formAsset['acquisition_date']); ?>">
                </div>

                <div class="field">
                    <label for="condition_status">Condition *</label>
                    <select id="condition_status" name="condition_status" required>
                        <?php foreach ($validConditions as $option): ?>
                            <option value="<?= e($option); ?>"
                                <?= $formAsset['condition_status'] === $option ? 'selected' : ''; ?>>
                                <?= e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="assigned_to">Assigned To</label>
                    <input id="assigned_to" name="assigned_to" maxlength="150"
                           value="<?= e($formAsset['assigned_to']); ?>">
                </div>

                <div class="field">
                    <label for="status">Asset Status *</label>
                    <select id="status" name="status" required>
                        <?php foreach ($validStatuses as $option): ?>
                            <option value="<?= e($option); ?>"
                                <?= $formAsset['status'] === $option ? 'selected' : ''; ?>>
                                <?= e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= e($formAsset['description']); ?></textarea>
                </div>

                <div class="field full">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes"><?= e($formAsset['notes']); ?></textarea>
                </div>

                <div class="field full">
                    <div>
                        <button type="submit">
                            <?= $editAsset ? 'Update Asset' : 'Register Asset'; ?>
                        </button>

                        <?php if ($editAsset): ?>
                            <a class="btn btn-secondary" href="assets.php">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Registered Church Assets</h2>

        <form class="filters" method="GET">
            <input type="search" name="search"
                   placeholder="Search asset code, name, or category"
                   value="<?= e($search); ?>">

            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($validStatuses as $option): ?>
                    <option value="<?= e($option); ?>"
                        <?= $filterStatus === $option ? 'selected' : ''; ?>>
                        <?= e($option); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Search</button>
            <a class="btn btn-secondary" href="assets.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Asset Name</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Acquired</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($assets): ?>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><?= e($asset['asset_code']); ?></td>
                            <td><?= e($asset['asset_name']); ?></td>
                            <td><?= e($asset['category']); ?></td>
                            <td><?= e($asset['location'] ?: '—'); ?></td>
                            <td><?= e($asset['acquisition_date'] ?: '—'); ?></td>
                            <td><span class="status"><?= e($asset['condition_status']); ?></span></td>
                            <td><span class="status"><?= e($asset['status']); ?></span></td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="assets.php?edit=<?= (int)$asset['id']; ?>">
                                        Edit
                                    </a>

                                    <?php if ($asset['status'] !== 'Disposed'): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Mark this asset as disposed?');">
                                            <input type="hidden" name="action" value="dispose">
                                            <input type="hidden" name="asset_id"
                                                   value="<?= (int)$asset['id']; ?>">
                                            <button class="btn-danger" type="submit">
                                                Dispose
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No assets found. Register your first asset above.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</body>
</html>
