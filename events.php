
<?php
require_once __DIR__ . "/config/database.php";

$message = "";
$messageType = "success";

function escapeEvent($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
    $eventName = trim($_POST["event_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $eventStatus = $_POST["status"] ?? "Published";
    $validStatuses = ["Draft", "Published"];

    if ($action === "delete") {
        if (!$id) {
            $message = "Invalid event selected.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                header("Location: events.php?status=deleted");
                exit;
            }

            $stmt->close();
            $message = "The event could not be deleted or was not found.";
            $messageType = "error";
        }
    } elseif ($action === "add" || $action === "update") {
        $validDate = DateTime::createFromFormat("!Y-m-d", $eventDate);
        $dateIsValid = $validDate &&
            $validDate->format("Y-m-d") === $eventDate;

        if ($eventName === "" || !$dateIsValid ||
            !in_array($eventStatus, $validStatuses, true)) {
            $message = "Enter an event name and a valid event date.";
            $messageType = "error";
        } elseif ($action === "update" && !$id) {
            $message = "Invalid event selected for editing.";
            $messageType = "error";
        } else {
            if ($action === "add") {
                $stmt = $conn->prepare(
                    "INSERT INTO events
                    (event_name, event_date, location, description, status)
                    VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "sssss",
                    $eventName,
                    $eventDate,
                    $location,
                    $description,
                    $eventStatus
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE events
                    SET event_name = ?, event_date = ?,
                        location = ?, description = ?, status = ?
                    WHERE id = ?"
                );
                $stmt->bind_param(
                    "sssssi",
                    $eventName,
                    $eventDate,
                    $location,
                    $description,
                    $eventStatus,
                    $id
                );
            }

            if ($stmt->execute()) {
                $stmt->close();
                header(
                    "Location: events.php?status=" .
                    ($action === "add" ? "added" : "updated")
                );
                exit;
            }

            $message = "Unable to save the event. Please try again.";
            $messageType = "error";
            $stmt->close();
        }
    }
}

// Status messages
if (isset($_GET["status"])) {
    $statuses = [
        "added" => "Event added successfully.",
        "updated" => "Event updated successfully.",
        "deleted" => "Event deleted successfully."
    ];

    if (isset($statuses[$_GET["status"]])) {
        $message = $statuses[$_GET["status"]];
    }
}

// Load event for editing
$editEvent = null;

if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT id, event_name, event_date, location, description, status
         FROM events WHERE id = ?"
    );
    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $result = $stmt->get_result();
    $editEvent = $result->fetch_assoc();
    $stmt->close();

    if (!$editEvent) {
        $message = "Event not found.";
        $messageType = "error";
    }
}

// Fetch events, upcoming first
$events = $conn->query(
    "SELECT id, event_name, event_date, location, description, status, created_at
     FROM events
     ORDER BY event_date ASC, id DESC"
);

if (!$events) {
    die("Unable to load events. Please check the database table.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management | A.I.C Kalyet</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .events-container {
            max-width: 1150px;
            margin: 35px auto;
            padding: 20px;
        }

        .events-header {
            background: linear-gradient(135deg, #0d47a1, #1976d2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .events-header h1 {
            margin: 0 0 8px;
        }

        .events-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, .08);
            margin-bottom: 25px;
        }

        .events-card h2 {
            color: #0d47a1;
            margin-top: 0;
        }

        .events-form label {
            display: block;
            font-weight: 600;
            margin: 15px 0 7px;
        }

        .events-form input,
        .events-form textarea,
        .events-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ccd5e0;
            border-radius: 7px;
            font: inherit;
        }

        .events-form input:focus,
        .events-form textarea:focus {
            outline: 2px solid #90caf9;
            border-color: #1976d2;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            margin: 4px 2px;
        }

        .btn-primary { background: #1565c0; }
        .btn-edit { background: #00897b; }
        .btn-delete { background: #c62828; }
        .btn-cancel { background: #616161; }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .message-success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .message-error {
            background: #ffebee;
            color: #b71c1c;
        }

        .table-wrapper { overflow-x: auto; }

        .events-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .events-table th,
        .events-table td {
            padding: 13px;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
            vertical-align: top;
        }

        .events-table th {
            background: #eaf2fb;
            color: #0d47a1;
        }

        .events-table tr:hover { background: #f8fbff; }

        .status-badge {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-published {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .status-draft {
            background: #fff3e0;
            color: #e65100;
        }

        .status-help {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .empty-state {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #1565c0;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .events-container {
                padding: 12px;
                margin: 15px auto;
            }

            .events-card { padding: 16px; }
        }
    </style>
</head>

<body>
<div class="events-container">

    <a class="back-link" href="index.php">&larr; Back to Homepage</a>

    <div class="events-header">
        <h1>Church Events Management</h1>
        <p>Plan, organize, and manage A.I.C Kalyet Church events.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType === "error"
            ? "message-error" : "message-success" ?>">
            <?= escapeEvent($message) ?>
        </div>
    <?php endif; ?>

    <div class="events-card">
        <h2><?= $editEvent ? "Edit Event" : "Add New Event" ?></h2>

        <form class="events-form" method="POST" action="events.php">
            <input
                type="hidden"
                name="action"
                value="<?= $editEvent ? "update" : "add" ?>"
            >

            <?php if ($editEvent): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$editEvent["id"] ?>"
                >
            <?php endif; ?>

            <label for="event_name">Event Name *</label>
            <input
                type="text"
                id="event_name"
                name="event_name"
                maxlength="150"
                required
                value="<?= escapeEvent($editEvent["event_name"] ?? "") ?>"
                placeholder="e.g. Sunday Service"
            >

            <label for="event_date">Event Date *</label>
            <input
                type="date"
                id="event_date"
                name="event_date"
                required
                value="<?= escapeEvent($editEvent["event_date"] ?? "") ?>"
            >

            <label for="location">Location / Venue</label>
            <input
                type="text"
                id="location"
                name="location"
                maxlength="255"
                value="<?= escapeEvent($editEvent["location"] ?? "") ?>"
                placeholder="e.g. Main Church"
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                placeholder="Enter event details"
            ><?= escapeEvent($editEvent["description"] ?? "") ?></textarea>

            <label for="status">Publication Status</label>
            <select id="status" name="status">
                <?php $currentStatus = $editEvent["status"] ?? "Published"; ?>
                <option value="Published" <?= $currentStatus === "Published"
                    ? "selected" : "" ?>>Published</option>
                <option value="Draft" <?= $currentStatus === "Draft"
                    ? "selected" : "" ?>>Draft</option>
            </select>
            <p class="status-help">
                Published events appear in the public homepage updates section.
                Draft events remain visible only here.
            </p>

            <button type="submit" class="btn btn-primary">
                <?= $editEvent ? "Update Event" : "Add Event" ?>
            </button>

            <?php if ($editEvent): ?>
                <a href="events.php" class="btn btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="events-card">
        <h2>Church Events</h2>

        <div class="table-wrapper">
            <table class="events-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($events->num_rows > 0): ?>
                    <?php $number = 1; ?>

                    <?php while ($event = $events->fetch_assoc()): ?>
                        <tr>
                            <td><?= $number++ ?></td>

                            <td>
                                <strong><?= escapeEvent($event["event_name"]) ?></strong>
                            </td>

                            <td>
                                <?= date(
                                    "d M Y",
                                    strtotime($event["event_date"])
                                ) ?>
                            </td>

                            <td><?= escapeEvent(
                                $event["location"] ?: "Not specified"
                            ) ?></td>

                            <td><?= escapeEvent(
                                $event["description"] ?: "No description"
                            ) ?></td>

                            <td>
                                <?php $eventStatus = $event["status"] ?? "Published"; ?>
                                <span class="status-badge status-<?= strtolower(
                                    escapeEvent($eventStatus)
                                ) ?>">
                                    <?= escapeEvent($eventStatus) ?>
                                </span>
                                <form
                                    method="POST"
                                    action="events.php"
                                    style="margin-top: 8px"
                                >
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$event["id"] ?>">
                                    <input type="hidden" name="event_name" value="<?= escapeEvent($event["event_name"]) ?>">
                                    <input type="hidden" name="event_date" value="<?= escapeEvent($event["event_date"]) ?>">
                                    <input type="hidden" name="location" value="<?= escapeEvent($event["location"]) ?>">
                                    <input type="hidden" name="description" value="<?= escapeEvent($event["description"]) ?>">
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="<?= $eventStatus === "Published" ? "Draft" : "Published" ?>"
                                    >
                                    <button type="submit" class="btn <?= $eventStatus === "Published"
                                        ? "btn-cancel" : "btn-edit" ?>">
                                        <?= $eventStatus === "Published"
                                            ? "Unpublish" : "Publish" ?>
                                    </button>
                                </form>
                            </td>

                            <td>
                                <a
                                    class="btn btn-edit"
                                    href="events.php?edit=<?= (int)$event["id"] ?>"
                                >Edit</a>

                                <form
                                    method="POST"
                                    action="events.php"
                                    style="display:inline"
                                    onsubmit="return confirm('Are you sure you want to delete this event?');"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$event["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-delete"
                                    >Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            No events have been added yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
