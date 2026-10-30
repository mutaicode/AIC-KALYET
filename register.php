
<?php
session_start();
require_once 'config/database.php';

$message = '';
$messageType = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' ||
        $password === '' || $confirmPassword === '') {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $message = 'Username must be 3–50 characters and use only letters, numbers, dots, underscores or hyphens.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Your password must contain at least 8 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'The passwords do not match.';
        $messageType = 'error';
    } else {
        // Check whether the username or email is already registered.
        $check = $conn->prepare(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1"
        );

        if (!$check) {
            $message = 'Unable to check account details. Please check your database.';
            $messageType = 'error';
        } else {
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $existingUser = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existingUser) {
                $message = 'That username or email is already registered.';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Role and status are assigned by the server, not the form.
                $role = 'user';
                $status = 'pending';

                $stmt = $conn->prepare(
                    "INSERT INTO users (username, email, password, role, status)
                     VALUES (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $message = 'Unable to create the account. Please try again.';
                    $messageType = 'error';
                } else {
                    $stmt->bind_param(
                        "sssss",
                        $username,
                        $email,
                        $hashedPassword,
                        $role,
                        $status
                    );

                    if ($stmt->execute()) {
                        $message = 'Registration submitted successfully. An administrator must activate your account before you can log in.';
                        $messageType = 'success';
                        $username = '';
                        $email = '';
                    } else {
                        $message = 'Registration failed. Please try again.';
                        $messageType = 'error';
                    }

                    $stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #102b52, #176b65);
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            padding: 38px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .22);
        }

        .church-icon {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #e2f4ef;
            color: #137b70;
            font-size: 30px;
        }

        h1 {
            text-align: center;
            color: #16365b;
            font-size: 27px;
            margin-bottom: 9px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #137b70;
            box-shadow: 0 0 0 3px rgba(19, 123, 112, .12);
        }

        .register-button {
            width: 100%;
            padding: 14px;
            margin-top: 5px;
            border: none;
            border-radius: 8px;
            background: #137b70;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .register-button:hover {
            background: #105b54;
        }

        .message {
            margin-bottom: 18px;
            padding: 12px;
            border-radius: 7px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.error {
            color: #a61b1b;
            background: #fff0f0;
        }

        .message.success {
            color: #166534;
            background: #ecfdf3;
        }

        .links {
            text-align: center;
            margin-top: 22px;
            font-size: 14px;
            line-height: 1.8;
            color: #64748b;
        }

        a {
            color: #137b70;
            font-weight: bold;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #8793a3;
        }

        @media (max-width: 500px) {
            .register-card {
                padding: 27px 22px;
            }
        }
    </style>
</head>

<body>
    <main class="register-card">
        <div class="church-icon">✝</div>

        <h1>Create an Account</h1>

        <p class="subtitle">
            AIC Kalyet Church Management System
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>"
                 role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    minlength="3"
                    maxlength="50"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="100"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="register-button">
                Register Account
            </button>
        </form>

        <div class="links">
            Already have an account?
            <a href="login.php">Sign in here</a>
        </div>

        <div class="footer">
            &copy; <?= date('Y') ?> AIC Kalyet Church Management System
        </div>
    </main>
</body>
</html>
