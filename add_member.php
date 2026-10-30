
<?php
session_start();
require_once __DIR__ . "/config/database.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $dateOfBirth = $_POST["date_of_birth"] ?? "";
    $address = trim($_POST["address"] ?? "");

    $allowedGenders = ["Male", "Female", "Other"];

    if ($firstName === "" || $lastName === "") {
        $message = "First name and last name are required.";
        $messageType = "danger";
    } elseif (!in_array($gender, $allowedGenders, true) && $gender !== "") {
        $message = "Please select a valid gender.";
        $messageType = "danger";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "danger";
    } elseif ($dateOfBirth !== "" && (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth) ||
        !checkdate(
            (int) substr($dateOfBirth, 5, 2),
            (int) substr($dateOfBirth, 8, 2),
            (int) substr($dateOfBirth, 0, 4)
        )
    )) {
        $message = "Please enter a valid date of birth.";
        $messageType = "danger";
    } else {
        $membershipNo = "KAL-" . strtoupper(bin2hex(random_bytes(4)));
        $dob = $dateOfBirth !== "" ? $dateOfBirth : null;
        $emailValue = $email !== "" ? $email : null;
        $genderValue = $gender !== "" ? $gender : null;
        $phoneValue = $phone !== "" ? $phone : null;
        $addressValue = $address !== "" ? $address : null;

        $stmt = $conn->prepare(
            "INSERT INTO members
            (membership_no, first_name, last_name, gender, phone,
             email, date_of_birth, address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $message = "Unable to prepare the registration. Please try again.";
            $messageType = "danger";
        } else {
            $stmt->bind_param(
                "ssssssss",
                $membershipNo,
                $firstName,
                $lastName,
                $genderValue,
                $phoneValue,
                $emailValue,
                $dob,
                $addressValue
            );

            if ($stmt->execute()) {
                $message = "Member registered successfully! Membership No: "
                    . $membershipNo;
                $messageType = "success";
            } else {
                $message = "Registration failed. Please try again.";
                $messageType = "danger";
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Member | A.I.C Kalyet</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            A.I.C Kalyet Church
        </a>
        <a href="index.php" class="btn btn-light btn-sm">Home</a>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">

                    <h2 class="fw-bold text-primary mb-2">
                        Church Member Registration
                    </h2>

                    <p class="text-secondary mb-4">
                        Enter the member's details in the form below.
                    </p>

                    <?php if ($message !== ""): ?>
                        <div class="alert alert-<?= htmlspecialchars($messageType) ?>"
                             role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="add_member.php">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <label for="first_name" class="form-label">
                                    First Name *
                                </label>
                                <input type="text" id="first_name"
                                       name="first_name" class="form-control"
                                       maxlength="100" required>
                            </div>

                            <div class="col-md-6">
                                <label for="last_name" class="form-label">
                                    Last Name *
                                </label>
                                <input type="text" id="last_name"
                                       name="last_name" class="form-control"
                                       maxlength="100" required>
                            </div>

                            <div class="col-md-6">
                                <label for="gender" class="form-label">
                                    Gender
                                </label>
                                <select id="gender" name="gender"
                                        class="form-select">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">
                                    Phone Number
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       class="form-control" maxlength="20"
                                       placeholder="e.g. 07XX XXX XXX">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">
                                    Email Address
                                </label>
                                <input type="email" id="email" name="email"
                                       class="form-control" maxlength="150">
                            </div>

                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">
                                    Date of Birth
                                </label>
                                <input type="date" id="date_of_birth"
                                       name="date_of_birth"
                                       class="form-control">
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label">
                                    Address / Village
                                </label>
                                <textarea id="address" name="address"
                                          class="form-control" rows="3"
                                          maxlength="255"></textarea>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit"
                                        class="btn btn-primary px-4 py-2">
                                    Register Member
                                </button>

                                <a href="index.php"
                                   class="btn btn-outline-secondary px-4 py-2 ms-2">
                                    Cancel
                                </a>
                            </div>

                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</main>

</body>
</html>
