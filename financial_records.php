
<?php
session_start();
require_once 'config/database.php';

$allowedRoles = ['admin', 'chairperson', 'treasurer'];

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowedRoles, true)
) {
    header('Location: login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$isTreasurer = $_SESSION['role'] === 'treasurer'
    || $_SESSION['role'] === 'admin';

$canManage = $isTreasurer;
$userId = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$categories = [
    'Tithes',
    'Offerings',
    'Thanksgiving',
    'Donations',
    'Fundraising',
    'Project Contributions',
    'Other Income',
    'Salaries and Allowances',
    'Utilities',
    'Transport',
    'Church Projects',
    'Repairs and Maintenance',
    'Office Expenses',
    'Other Expenses'
];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        exit('You do not have permission to record transactions.');
    }

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Security validation failed. Refresh the page and try again.';
    } else {
        $date = trim($_POST['transaction_date'] ?? '');
        $type = trim($_POST['transaction_type'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = filter_var(
            $_POST['amount'] ?? '',
            FILTER_VALIDATE_FLOAT
        );
        $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
        $reference = trim($_POST['reference_number'] ?? '');

        $validDate = DateTime::createFromFormat('!Y-m-d', $date);

        if (
            !$validDate ||
            $validDate->format('Y-m-d') !== $date
        ) {
            $error = 'Please enter a valid transaction date.';
        } elseif (!in_array($type, ['income', 'expense'], true)) {
            $error = 'Choose Income or Expense.';
        } elseif ($category === '' || strlen($category) > 100) {
            $error = 'Enter a valid category.';
        } elseif ($description === '') {
            $error = 'Please enter a description.';
        } elseif (
            $amount === false ||
            !is_finite((float)$amount) ||
            $amount <= 0 ||
            $amount > 9999999999.99
        ) {
            $error = 'Enter a valid amount greater than zero.';
        } elseif (
            !in_array(
                $paymentMethod,
                ['Cash', 'M-Pesa', 'Bank Transfer', 'Cheque', 'Other'],
                true
            )
        ) {
            $error = 'Choose a valid payment method.';
        } elseif (strlen($reference) > 100) {
            $error = 'Reference number is too long.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO financial_records
                (
                    transaction_date,
                    transaction_type,
                    category,
                    description,
                    amount,
                    payment_method,
                    reference_number,
                    recorded_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $amount = (float)$amount;
                $stmt->bind_param(
                    "ssssdssi",
                    $date,
                    $type,
                    $category,
                    $description,
                    $amount,
                    $paymentMethod,
                    $reference,
                    $userId
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    header('Location: financial_records.php?saved=1');
                    exit;
                }

                $error = 'Could not save the transaction. Please try again.';
                $stmt->close();
            } else {
                $error = 'Could not prepare the database query.';
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Financial transaction saved successfully.';
}

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$monthStart = $month . '-01';
$monthEnd = date(
    'Y-m-d',
    strtotime($monthStart . ' +1 month')
);

$summaryStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(
            CASE WHEN transaction_type = 'income'
            THEN amount ELSE 0 END
        ), 0) AS income_total,
        COALESCE(SUM(
            CASE WHEN transaction_type = 'expense'
            THEN amount ELSE 0 END
        ), 0) AS expense_total,
        COUNT(*) AS transaction_count
    FROM financial_records
    WHERE transaction_date >= ?
      AND transaction_date < ?
");

$summaryStmt->bind_param("ss", $monthStart, $monthEnd);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

$incomeTotal = (float)$summary['income_total'];
$expenseTotal = (float)$summary['expense_total'];
$balance = $incomeTotal - $expenseTotal;

$listStmt = $conn->prepare("
    SELECT f.*, u.username AS recorder
    FROM financial_records f
    INNER JOIN users u ON u.id = f.recorded_by
    WHERE f.transaction_date >= ?
      AND f.transaction_date < ?
    ORDER BY f.transaction_date DESC, f.id DESC
");

$listStmt->bind_param("ss", $monthStart, $monthEnd);
$listStmt->execute();
$records = $listStmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Records | AIC Kalyet Church</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header {
    padding: 22px 30px;
    background: #102b4e;
    color: white;
}

header h1 { margin: 0 0 7px; }
header p { margin: 0; color: #dbeafe; }

main {
    max-width: 1250px;
    margin: 25px auto;
    padding: 0 20px;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.btn {
    display: inline-block;
    padding: 11px 15px;
    border: 0;
    border-radius: 6px;
    background: #176b65;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-secondary { background: #475569; }

.panel {
    padding: 22px;
    margin-bottom: 22px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.panel h2 { margin-top: 0; }

.summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.summary-card {
    padding: 20px;
    border-radius: 9px;
    background: white;
    border: 1px solid #e2e8f0;
}

.summary-card span {
    color: #64748b;
    font-size: 13px;
}

.summary-card h3 {
    margin-bottom: 0;
    overflow-wrap: anywhere;
}

.income { color: #15803d; }
.expense { color: #b91c1c; }
.balance { color: #176b65; }

form .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
    font-size: 13px;
}

input, select, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font: inherit;
}

textarea {
    min-height: 85px;
    resize: vertical;
}

.full { grid-column: 1 / -1; }

.notice {
    padding: 13px;
    margin-bottom: 18px;
    border-radius: 6px;
}

.success { background: #dcfce7; color: #166534; }
.error { background: #fee2e2; color: #991b1b; }

.filter {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 12px;
}

.filter div { min-width: 200px; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

th { background: #f8fafc; }
td.description { white-space: normal; min-width: 180px; }
.empty { padding: 25px; text-align: center; color: #64748b; }

@media (max-width: 650px) {
    main { padding: 0 12px; }
    header { padding: 20px; }
    .summary { grid-template-columns: 1fr; }
    form .grid { grid-template-columns: 1fr; }
    .full { grid-column: auto; }
}


@media print {
    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    body {
        background: white;
        color: #000;
        font-size: 11px;
    }

    /* Keep the church letterhead visible */
    header {
        display: block !important;
        background: white !important;
        color: black !important;
        text-align: center;
        padding: 0 0 15px;
        margin-bottom: 15px;
        border-bottom: 3px solid #14532d;
    }

    header h1 {
        font-size: 23px;
        margin: 0 0 6px;
        color: black !important;
    }

    header p {
        color: black !important;
        margin: 4px 0;
    }

    main {
        max-width: none;
        margin: 0;
        padding: 0;
    }

    .no-print,
    .actions,
    form {
        display: none !important;
    }

    .panel {
        border: 1px solid #aaa;
        box-shadow: none;
        padding: 12px;
        break-inside: avoid;
    }

    .summary {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .summary-card {
        border: 1px solid #aaa;
        box-shadow: none;
        padding: 12px;
    }

    table {
        width: 100%;
        min-width: 0;
        border-collapse: collapse;
    }

    th, td {
        font-size: 9px;
        padding: 7px;
        border: 1px solid #bbb;
        color: black;
    }

    th {
        background: #eee !important;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    h2 {
        font-size: 15px;
    }
}

</style>
</head>

<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>

<header>
    <h1>AIC Kalyet Church</h1>
    <p>Financial Records Management</p>
</header>

<main>

    <div class="actions no-print">
        <a class="btn btn-secondary" href="treasurer/dashboard.php">
            Treasurer Dashboard
        </a>
        <a class="btn btn-secondary" href="chairperson/dashboard.php">
            Chairperson Dashboard
        </a>
        <button class="btn" onclick="window.print()">
            Print / Save as PDF
        </button>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Monthly Financial Summary</h2>

        <form method="get" class="filter no-print">
            <div>
                <label for="month">Select month</label>
                <input type="month" id="month" name="month"
                       value="<?= e($month) ?>" required>
            </div>
            <button class="btn" type="submit">View Month</button>
        </form>

        <p>
            Report period:
            <strong><?= e(date('F Y', strtotime($monthStart))) ?></strong>
        </p>

        <div class="summary">
            <div class="summary-card">
                <span>Total Income</span>
                <h3 class="income">KSh <?= number_format($incomeTotal, 2) ?></h3>
            </div>

            <div class="summary-card">
                <span>Total Expenses</span>
                <h3 class="expense">KSh <?= number_format($expenseTotal, 2) ?></h3>
            </div>

            <div class="summary-card">
                <span>Monthly Net Balance</span>
                <h3 class="balance">KSh <?= number_format($balance, 2) ?></h3>
            </div>
        </div>

        <p>
            Transactions recorded this month:
            <strong><?= number_format((int)$summary['transaction_count']) ?></strong>
        </p>
    </section>

    <?php if ($canManage): ?>
    <section class="panel no-print">
        <h2>Record Income or Expense</h2>

        <form method="post" action="financial_records.php?month=<?= e($month) ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="grid">
                <div>
                    <label for="transaction_date">Transaction Date</label>
                    <input type="date" id="transaction_date"
                           name="transaction_date"
                           value="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div>
                    <label for="transaction_type">Transaction Type</label>
                    <select id="transaction_type" name="transaction_type" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>

                <div>
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>">
                                <?= e($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="amount">Amount (KSh)</label>
                    <input type="number" id="amount" name="amount"
                           min="0.01" max="9999999999.99"
                           step="0.01" required>
                </div>

                <div>
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option>Cash</option>
                        <option>M-Pesa</option>
                        <option>Bank Transfer</option>
                        <option>Cheque</option>
                        <option>Other</option>
                    </select>
                </div>

                <div>
                    <label for="reference_number">Reference Number (optional)</label>
                    <input type="text" id="reference_number"
                           name="reference_number" maxlength="100">
                </div>

                <div class="full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              required maxlength="5000"></textarea>
                </div>

                <div class="full">
                    <button type="submit" class="btn">Save Transaction</button>
                </div>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <h2>
            Transactions — <?= e(date('F Y', strtotime($monthStart))) ?>
        </h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Reference</th>
                        <th>Amount (KSh)</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($records->num_rows === 0): ?>
                    <tr>
                        <td colspan="8" class="empty">
                            No transactions found for this month.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($record = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($record['transaction_date']))) ?></td>
                            <td><?= e(ucfirst($record['transaction_type'])) ?></td>
                            <td><?= e($record['category']) ?></td>
                            <td class="description"><?= e($record['description']) ?></td>
                            <td><?= e($record['payment_method']) ?></td>
                            <td><?= e($record['reference_number'] ?? '') ?></td>
                            <td><?= number_format((float)$record['amount'], 2) ?></td>
                            <td><?= e($record['recorder']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p class="no-print" style="text-align:center;color:#64748b;font-size:12px">
        AIC Kalyet Church — Confidential Financial Records
    </p>

</main>
</body>
</html>
<?php
$listStmt->close();
?>
