
<?php
session_start();
require_once __DIR__ . '/config/database.php';

$allowedRoles = [
    'admin',
    'chairperson',
    'secretary',
    'vice_secretary',
    'pastor'
];

if (
    !isset($_SESSION['user_id']) ||
    !in_array(strtolower(trim($_SESSION['role'] ?? '')), $allowedRoles, true)
) {
    http_response_code(403);
    exit('Access denied.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Invalid meeting minutes ID.');
}

$stmt = $conn->prepare(
    "SELECT m.*, u.username AS recorder
     FROM meeting_minutes m
     LEFT JOIN users u ON m.recorded_by = u.id
     WHERE m.id = ?"
);

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$minutes = $result->fetch_assoc();
$stmt->close();

if (!$minutes) {
    http_response_code(404);
    exit('Meeting minutes not found.');
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function displayText($value) {
    return nl2br(e($value ?: 'Not recorded'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meeting Minutes - <?= e($minutes['meeting_title']) ?></title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    background: #eef2f5;
    font-family: "Times New Roman", serif;
    color: #111;
    line-height: 1.6;
}

.toolbar {
    max-width: 850px;
    margin: 0 auto 18px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.toolbar a,
.toolbar button {
    display: inline-block;
    padding: 10px 16px;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
}

.toolbar a {
    background: #475569;
    color: white;
}

.toolbar button {
    background: #166534;
    color: white;
}

.document {
    max-width: 850px;
    margin: auto;
    padding: 45px 55px;
    background: white;
    box-shadow: 0 3px 15px #00000015;
}

.letterhead {
    text-align: center;
    border-bottom: 3px double #123b2a;
    padding-bottom: 17px;
    margin-bottom: 25px;
}

.letterhead h1 {
    color: #123b2a;
    font-size: 25px;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.letterhead h2 {
    font-size: 17px;
    margin: 5px 0;
    text-transform: uppercase;
}

.letterhead p {
    margin: 3px 0;
    font-size: 13px;
}

.document-title {
    text-align: center;
    text-decoration: underline;
    font-size: 19px;
    margin: 25px 0;
}

.meeting-info {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}

.meeting-info td {
    padding: 7px;
    border: 1px solid #bbb;
    vertical-align: top;
}

.meeting-info td:first-child {
    width: 30%;
    font-weight: bold;
}

.content-section {
    margin-top: 24px;
    page-break-inside: avoid;
}

.content-section h3 {
    font-size: 16px;
    text-transform: uppercase;
    border-bottom: 1px solid #999;
    padding-bottom: 5px;
    margin-bottom: 10px;
}

.content-section p {
    margin-top: 0;
    overflow-wrap: anywhere;
}

.signature-area {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 45px;
    margin-top: 65px;
}

.signature-line {
    border-top: 1px solid #111;
    padding-top: 8px;
    text-align: center;
}

.footer {
    border-top: 1px solid #999;
    margin-top: 40px;
    padding-top: 10px;
    text-align: center;
    font-size: 11px;
}

@media print {
    @page {
        size: A4;
        margin: 15mm;
    }

    body {
        padding: 0;
        background: white;
        font-size: 12pt;
    }

    .toolbar {
        display: none !important;
    }

    .document {
        max-width: none;
        margin: 0;
        padding: 0;
        box-shadow: none;
    }

    .letterhead,
    .meeting-info,
    .content-section,
    .signature-area {
        break-inside: avoid;
    }

    .content-section p {
        overflow-wrap: anywhere;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>

<div class="toolbar">
    <a href="minutes.php">← Back to Minutes</a>
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<main class="document">

    <header class="letterhead">
        <h1>AIC Kalyet Church</h1>
        <h2>Official Church Records</h2>
        <p>Meeting Minutes and Proceedings</p>
        <p>Official Document — For Church Use</p>
    </header>

    <h2 class="document-title">OFFICIAL MEETING MINUTES</h2>

    <table class="meeting-info">
        <tr>
            <td>Meeting Title</td>
            <td><?= e($minutes['meeting_title']) ?></td>
        </tr>
        <tr>
            <td>Date</td>
            <td><?= e(date('d F Y', strtotime($minutes['meeting_date']))) ?></td>
        </tr>
        <tr>
            <td>Venue</td>
            <td><?= e($minutes['venue']) ?></td>
        </tr>
        <tr>
            <td>Attendees</td>
            <td><?= displayText($minutes['attendees']) ?></td>
        </tr>
    </table>

    <section class="content-section">
        <h3>1. Agenda</h3>
        <p><?= displayText($minutes['agenda']) ?></p>
    </section>

    <section class="content-section">
        <h3>2. Discussions and Proceedings</h3>
        <p><?= displayText($minutes['discussions']) ?></p>
    </section>

    <section class="content-section">
        <h3>3. Resolutions and Action Points</h3>
        <p><?= displayText($minutes['resolutions']) ?></p>
    </section>

    <div class="signature-area">
        <div class="signature-line">
            Secretary's Name and Signature
        </div>
        <div class="signature-line">
            Chairperson's Name and Signature
        </div>
    </div>

    <footer class="footer">
        AIC Kalyet Church Management System<br>
        Recorded by: <?= e($minutes['recorder'] ?? 'Unknown') ?><br>
        Document generated on <?= e(date('d F Y')) ?>
    </footer>

</main>

</body>
</html>
