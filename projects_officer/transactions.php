
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));
if (!in_array($role, ['projects_officer', 'admin'], true)) {
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

function showDate($date): string
{
    if (!$date) {
        return 'Not specified';
    }

    $time = strtotime($date);
    return $time ? date('d M Y', $time) : $date;
}

$typesAllowed = ['Contribution', 'Expense'];
$errors = [];
$message = '';

$form = [
    'id' => '',
    'project_id' => '',
    'transaction_type' => 'Contribution',
    'transaction_date' => date('Y-m-d'),
    'category' => '',
    'description' => '',
    'contributor_or_payee' => '',
    'amount' => '',
    'payment_method' => '',
    'reference_number' => '',
    'receipt_number' => '',
    'notes' => ''
];

// Load available projects
$projects = [];

try {
    $result = $conn->query(
        "SELECT id, project_code, project_name
         FROM projects
         WHERE status != 'Cancelled'
         ORDER BY project_name ASC"
    );

    if ($result) {
        $projects = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $ex) {
    error_log('Load projects: ' . $ex->getMessage());
    $errors[] = 'Unable to load projects. Check that the projects table exists.';
}

// Save a transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    } else {
        $idInput = $_POST['id'] ?? '';
        $id = $idInput === ''
            ? null
            : (is_string($idInput)
                ? filter_var($idInput, FILTER_VALIDATE_INT)
                : false);
        $projectId = filter_var($_POST['project_id'] ?? '', FILTER_VALIDATE_INT);
        $type = trim($_POST['transaction_type'] ?? '');
        $date = trim($_POST['transaction_date'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $person = trim($_POST['contributor_or_payee'] ?? '');
        $amountInput = trim($_POST['amount'] ?? '');
        $amount = filter_var($amountInput, FILTER_VALIDATE_FLOAT);
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $reference = trim($_POST['reference_number'] ?? '');
        $receipt = trim($_POST['receipt_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $validDate = DateTime::createFromFormat('!Y-m-d', $date);
        $dateIsValid = $validDate && $validDate->format('Y-m-d') === $date;

        $projectExists = false;

        if ($projectId !== false && $projectId > 0) {
            foreach ($projects as $project) {
                if ((int)$project['id'] === (int)$projectId) {
                    $projectExists = true;
                    break;
                }
            }
        }

        if ($id === false || ($id !== null && $id < 1)) {
            $errors[] = 'Invalid transaction ID.';
        } elseif (!$projectExists) {
            $errors[] = 'Select a valid project.';
        } elseif (!in_array($type, $typesAllowed, true)) {
            $errors[] = 'Select Contribution or Expense.';
        } elseif (!$dateIsValid) {
            $errors[] = 'Enter a valid transaction date.';
        } elseif ($category === '' || $description === '') {
            $errors[] = 'Category and description are required.';
        } elseif (strlen($category) > 100) {
            $errors[] = 'Category must not exceed 100 characters.';
        } elseif ($amount === false || !is_finite((float)$amount) || $amount <= 0) {
            $errors[] = 'Enter an amount greater than zero.';
        } elseif (strlen($person) > 200) {
            $errors[] = 'Contributor or payee name is too long.';
        } elseif (strlen($paymentMethod) > 50) {
            $errors[] = 'Payment method must not exceed 50 characters.';
        } elseif (strlen($reference) > 100 || strlen($receipt) > 100) {
            $errors[] = 'Reference and receipt numbers must not exceed 100 characters.';
        } else {
            try {
                $userId = (int)$_SESSION['user_id'];

                if ($id) {
                    // Confirm the transaction exists before editing it.
                    $check = $conn->prepare(
                        "SELECT id FROM project_transactions WHERE id = ?"
                    );
                    $check->bind_param('i', $id);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if (!$exists) {
                        throw new RuntimeException('Transaction not found.');
                    }

                    $stmt = $conn->prepare(
                        "UPDATE project_transactions SET
                            project_id = ?,
                            transaction_type = ?,
                            transaction_date = ?,
                            category = ?,
                            description = ?,
                            contributor_or_payee = NULLIF(?, ''),
                            amount = ?,
                            payment_method = NULLIF(?, ''),
                            reference_number = NULLIF(?, ''),
                            receipt_number = NULLIF(?, ''),
                            notes = NULLIF(?, '')
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        'isssssdssssi',
                        $projectId,
                        $type,
                        $date,
                        $category,
                        $description,
                        $person,
                        $amount,
                        $paymentMethod,
                        $reference,
                        $receipt,
                        $notes,
                        $id
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO project_transactions (
                            project_id, transaction_type, transaction_date,
                            category, description, contributor_or_payee,
                            amount, payment_method, reference_number,
                            receipt_number, notes, recorded_by
                        ) VALUES (
                            ?, ?, ?, ?, ?, NULLIF(?, ''),
                            ?, NULLIF(?, ''), NULLIF(?, ''),
                            NULLIF(?, ''), NULLIF(?, ''), ?
                        )"
                    );

                    $stmt->bind_param(
                        'isssssdssssi',
                        $projectId,
                        $type,
                        $date,
                        $category,
                        $description,
                        $person,
                        $amount,
                        $paymentMethod,
                        $reference,
                        $receipt,
                        $notes,
                        $userId
                    );
                }

                $stmt->execute();
                $stmt->close();

                // Update the project's total recorded expenses.
                $updateProject = $conn->prepare(
                    "UPDATE projects
                     SET amount_spent = (
                         SELECT COALESCE(SUM(amount), 0)
                         FROM project_transactions
                         WHERE project_id = projects.id
                           AND transaction_type = 'Expense'
                     )
                     WHERE id = ?"
                );
                $updateProject->bind_param('i', $projectId);
                $updateProject->execute();
                $updateProject->close();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: transactions.php?success=1');
                exit;
            } catch (Throwable $ex) {
                error_log('Save project transaction: ' . $ex->getMessage());
                $errors[] = $ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'Unable to save the transaction. Check the database and try again.';

                $form = [
                    'id' => $id ?: '',
                    'project_id' => $projectId ?: '',
                    'transaction_type' => $type,
                    'transaction_date' => $date,
                    'category' => $category,
                    'description' => $description,
                    'contributor_or_payee' => $person,
                    'amount' => $amountInput,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $reference,
                    'receipt_number' => $receipt,
                    'notes' => $notes
                ];
            }
        }
    }
}

if (isset($_GET['success'])) {
    $message = 'Transaction saved successfully.';
}

// Load transaction for editing
if ($_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit']) &&
    (int)$_GET['edit'] > 0) {
    try {
        $editId = (int)$_GET['edit'];

        $stmt = $conn->prepare(
            "SELECT id, project_id, transaction_type, transaction_date,
                    category, description, contributor_or_payee, amount,
                    payment_method, reference_number, receipt_number, notes
             FROM project_transactions
             WHERE id = ?"
        );
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $transaction = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($transaction) {
            $form = $transaction;
        } else {
            $errors[] = 'Transaction not found.';
        }
    } catch (Throwable $ex) {
        error_log('Edit transaction: ' . $ex->getMessage());
        $errors[] = 'Unable to load the selected transaction.';
    }
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$filterProject = trim($_GET['project'] ?? '');

$transactions = [];
$totalContributions = 0.0;
$totalExpenses = 0.0;

try {
    $sql = "SELECT t.*, p.project_code, p.project_name
            FROM project_transactions t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE 1 = 1";

    $types = '';
    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            t.category LIKE ? OR
            t.description LIKE ? OR
            t.contributor_or_payee LIKE ? OR
            p.project_name LIKE ? OR
            p.project_code LIKE ? OR
            t.receipt_number LIKE ? OR
            t.reference_number LIKE ?
        )";

        $term = '%' . $search . '%';
        $types .= 'sssssss';
        array_push($params, $term, $term, $term, $term, $term, $term, $term);
    }

    if (in_array($filterType, $typesAllowed, true)) {
        $sql .= " AND t.transaction_type = ?";
        $types .= 's';
        $params[] = $filterType;
    }

    if ($filterProject !== '' && ctype_digit($filterProject)) {
        $sql .= " AND t.project_id = ?";
        $types .= 'i';
        $params[] = (int)$filterProject;
    }

    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($transactions as $transaction) {
        if ($transaction['transaction_type'] === 'Contribution') {
            $totalContributions += (float)$transaction['amount'];
        } else {
            $totalExpenses += (float)$transaction['amount'];
        }
    }
} catch (Throwable $ex) {
    error_log('List project transactions: ' . $ex->getMessage());
    $errors[] = 'Unable to load transactions. Check the project transactions table.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Contributions & Expenses | AIC Kalyet</title>
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
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:22px}
.stat{background:white;padding:18px;border:1px solid #e5ebf3;border-radius:11px}
.stat span{font-size:13px;color:#748196}
.stat strong{display:block;margin-top:8px;font-size:23px;color:#17365d;overflow-wrap:anywhere}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.field{display:flex;flex-direction:column;gap:7px}
.full{grid-column:1/-1}
label{font-size:13px;font-weight:bold;color:#43546b}
input,select,textarea{width:100%;padding:11px;border:1px solid #ccd6e2;border-radius:7px;font:inherit;background:white}
textarea{min-height:80px;resize:vertical}
input:focus,select:focus,textarea:focus{outline:2px solid #bdd7f5;border-color:#3274b9}
.actions,.filters{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.filters{margin:0 0 18px}
.filters input{flex:1;min-width:180px}
.filters select{min-width:150px}
.btn{display:inline-block;border:0;border-radius:7px;padding:11px 15px;background:#1e5a91;color:white;cursor:pointer;font-weight:bold;font-size:13px}
.btn.secondary{background:#e7edf5;color:#263449}
.btn.edit{padding:7px 10px;background:#e4efff;color:#174b80}
.notice{padding:12px 15px;border-radius:8px;margin-bottom:18px}
.success{background:#dcfce7;color:#166534}
.error{background:#fee2e2;color:#991b1b}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1050px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #edf0f5;font-size:13px;vertical-align:top}
th{background:#f4f7fb;color:#53657d}
.status{display:inline-block;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:bold}
.Contribution{background:#dcfce7;color:#166534}
.Expense{background:#fee2e2;color:#991b1b}
.empty{text-align:center;padding:25px;background:#f8fafc;color:#78869a}
footer{text-align:center;color:#8290a3;font-size:12px;padding:12px}
@media(max-width:850px){.sidebar{position:static;width:100%;height:auto}.main{margin:0;padding:18px}.form-grid{grid-template-columns:1fr}h1{font-size:23px}}
@media print{.sidebar,.no-print,footer{display:none!important}.main{margin:0;padding:0}.panel,.stat{box-shadow:none;border:0}body{background:white}.stats{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="brand"><h2>AIC Kalyet</h2><p>Church Management System</p></div>
    <p class="nav-title">Projects Management</p>
    <a class="nav-link" href="dashboard.php">🏠 Dashboard</a>
    <a class="nav-link" href="projects.php">🏗️ Project Register</a>
    <a class="nav-link active" href="transactions.php">💰 Contributions & Expenses</a>
    <a class="nav-link" href="project_reports.php">📄 Project Reports</a>
    <p class="nav-title">Church</p>
    <a class="nav-link" href="../index.php">🌐 Homepage</a>
    <a class="nav-link" href="../dashboard.php">⛪ Main Dashboard</a>
    <a class="nav-link" href="../logout.php">🚪 Logout</a>
</aside>

<main class="main">
<header class="topbar">
    <div>
        <h1>Project Contributions & Expenses</h1>
        <p>Record and track funds received and expenditure for each project.</p>
    </div>
    <div class="profile">
        <strong><?= e($username); ?></strong>
        <span>Projects Management</span>
    </div>
</header>

<?php if ($message): ?>
    <div class="notice success"><?= e($message); ?></div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="notice error"><?= e($err); ?></div>
<?php endforeach; ?>

<section class="stats">
    <div class="stat">
        <span>Contributions in Current Filter</span>
        <strong>KSh <?= number_format($totalContributions, 2); ?></strong>
    </div>
    <div class="stat">
        <span>Expenses in Current Filter</span>
        <strong>KSh <?= number_format($totalExpenses, 2); ?></strong>
    </div>
    <div class="stat">
        <span>Balance in Current Filter</span>
        <strong>KSh <?= number_format($totalContributions - $totalExpenses, 2); ?></strong>
    </div>
</section>

<section class="panel" id="transaction-form">
<h2><?= $form['id'] ? '✏️ Edit Transaction' : '➕ Record Contribution or Expense'; ?></h2>

<?php if (!$projects): ?>
    <div class="notice error">No eligible projects are available. Register a project first.</div>
<?php endif; ?>

<form method="POST" action="transactions.php" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">
<input type="hidden" name="id" value="<?= e($form['id']); ?>">

<div class="form-grid">
    <div class="field">
        <label for="project_id">Project *</label>
        <select name="project_id" id="project_id" required>
            <option value="">-- Select Project --</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= (int)$project['id']; ?>"
                    <?= (string)$form['project_id'] === (string)$project['id'] ? 'selected' : ''; ?>>
                    <?= e($project['project_code'] . ' — ' . $project['project_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="transaction_type">Transaction Type *</label>
        <select name="transaction_type" id="transaction_type" required>
            <?php foreach ($typesAllowed as $typeOption): ?>
                <option value="<?= e($typeOption); ?>"
                    <?= $form['transaction_type'] === $typeOption ? 'selected' : ''; ?>>
                    <?= e($typeOption); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="transaction_date">Transaction Date *</label>
        <input type="date" name="transaction_date" id="transaction_date"
               value="<?= e($form['transaction_date']); ?>" required>
    </div>

    <div class="field">
        <label for="category">Category *</label>
        <input type="text" name="category" id="category" maxlength="100"
               placeholder="e.g. Member contribution, Cement, Transport"
               value="<?= e($form['category']); ?>" required>
    </div>

    <div class="field">
        <label for="amount">Amount (KSh) *</label>
        <input type="number" name="amount" id="amount" min="0.01"
               step="0.01" value="<?= e($form['amount']); ?>" required>
    </div>

    <div class="field">
        <label for="contributor_or_payee">Contributor / Payee</label>
        <input type="text" name="contributor_or_payee" id="contributor_or_payee"
               maxlength="200" value="<?= e($form['contributor_or_payee']); ?>"
               placeholder="Person or business name">
    </div>

    <div class="field">
        <label for="payment_method">Payment Method</label>
        <select name="payment_method" id="payment_method">
            <option value="">-- Select Method --</option>
            <?php foreach (['Cash', 'M-Pesa', 'Bank Transfer', 'Cheque', 'Other'] as $method): ?>
                <option value="<?= e($method); ?>"
                    <?= $form['payment_method'] === $method ? 'selected' : ''; ?>>
                    <?= e($method); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="reference_number">Reference Number</label>
        <input type="text" name="reference_number" id="reference_number"
               maxlength="100" value="<?= e($form['reference_number']); ?>">
    </div>

    <div class="field">
        <label for="receipt_number">Receipt Number</label>
        <input type="text" name="receipt_number" id="receipt_number"
               maxlength="100" value="<?= e($form['receipt_number']); ?>">
    </div>

    <div class="field full">
        <label for="description">Description *</label>
        <textarea name="description" id="description" required><?= e($form['description']); ?></textarea>
    </div>

    <div class="field full">
        <label for="notes">Additional Notes</label>
        <textarea name="notes" id="notes"><?= e($form['notes']); ?></textarea>
    </div>
</div>

<div class="actions">
    <button type="submit" class="btn" <?= !$projects ? 'disabled' : ''; ?>>
        <?= $form['id'] ? '💾 Update Transaction' : '💾 Save Transaction'; ?>
    </button>
    <?php if ($form['id']): ?>
        <a href="transactions.php" class="btn secondary">Cancel Editing</a>
    <?php else: ?>
        <button type="reset" class="btn secondary">Clear Form</button>
    <?php endif; ?>
</div>
</form>
</section>

<section class="panel">
<h2>📋 Transaction Records (<?= count($transactions); ?>)</h2>

<form method="GET" action="transactions.php" class="filters no-print">
    <input type="text" name="search"
           placeholder="Search project, contributor, receipt or description..."
           value="<?= e($search); ?>">

    <select name="type">
        <option value="">All Types</option>
        <?php foreach ($typesAllowed as $typeOption): ?>
            <option value="<?= e($typeOption); ?>"
                <?= $filterType === $typeOption ? 'selected' : ''; ?>>
                <?= e($typeOption); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="project">
        <option value="">All Projects</option>
        <?php foreach ($projects as $project): ?>
            <option value="<?= (int)$project['id']; ?>"
                <?= $filterProject === (string)$project['id'] ? 'selected' : ''; ?>>
                <?= e($project['project_code'] . ' — ' . $project['project_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn">Search / Filter</button>
    <a href="transactions.php" class="btn secondary">Reset</a>
    <button type="button" class="btn secondary" onclick="window.print()">Print / Save PDF</button>
</form>

<?php if ($transactions): ?>
<div class="table-wrap">
<table>
<thead>
<tr>
    <th>Date</th>
    <th>Project</th>
    <th>Type</th>
    <th>Category / Description</th>
    <th>Contributor / Payee</th>
    <th>Amount</th>
    <th>Payment</th>
    <th>Receipt / Reference</th>
    <th class="no-print">Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($transactions as $t): ?>
<tr>
    <td><?= e(showDate($t['transaction_date'])); ?></td>
    <td><?= e($t['project_code'] . ' — ' . $t['project_name']); ?></td>
    <td><span class="status <?= e($t['transaction_type']); ?>"><?= e($t['transaction_type']); ?></span></td>
    <td>
        <strong><?= e($t['category']); ?></strong><br>
        <?= e($t['description']); ?>
        <?php if (!empty($t['notes'])): ?>
            <br><small><?= e($t['notes']); ?></small>
        <?php endif; ?>
    </td>
    <td><?= e($t['contributor_or_payee'] ?: 'Not specified'); ?></td>
    <td>KSh <?= number_format((float)$t['amount'], 2); ?></td>
    <td><?= e($t['payment_method'] ?: 'Not specified'); ?></td>
    <td>
        Receipt: <?= e($t['receipt_number'] ?: '—'); ?><br>
        Ref: <?= e($t['reference_number'] ?: '—'); ?>
    </td>
    <td class="no-print">
        <a class="btn edit" href="transactions.php?edit=<?= (int)$t['id']; ?>#transaction-form">Edit</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty">No transactions found. Record a contribution or expense above.</div>
<?php endif; ?>
</section>

<footer>&copy; <?= date('Y'); ?> AIC Kalyet Church Management System</footer>
</main>
</body>
</html>
