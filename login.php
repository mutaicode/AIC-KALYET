<?php
require_once __DIR__ . '/config/bootstrap.php';

$error = '';
$login = '';

// Already logged in? Redirect.
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    $dashboards = [
        'admin'               => 'admin/dashboard.php',
        'chairperson'         => 'chairperson/dashboard.php',
        'vice_chairperson'    => 'vice_chairperson/dashboard.php',
        'secretary'           => 'secretary/dashboard.php',
        'vice_secretary'      => 'vice_secretary/dashboard.php',
        'treasurer'           => 'treasurer/dashboard.php',
        'projects_officer'    => 'projects_officer/dashboard.php',
        'assets_officer'      => 'assets_officer/dashboard.php',
        'christian_education' => 'christian_education/dashboard.php',
        'youth_chairperson'   => 'youth_chairperson/dashboard.php',
        'pastor'              => 'pastor/dashboard.php',
        'chairlady'           => 'chairlady/dashboard.php',
        'user'                => 'dashboard.php',
    ];
    $role = strtolower(trim($_SESSION['role']));
    header('Location: ' . ($dashboards[$role] ?? 'dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, username, email, password, role, status
                 FROM users
                 WHERE username = ? OR email = ?
                 LIMIT 1"
            );

            if (!$stmt) {
                throw new RuntimeException('Prepare failed: ' . $conn->error);
            }

            $stmt->bind_param('ss', $login, $login);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                if (strtolower($user['status']) !== 'active') {
                    $error = 'Your account is not active yet. Please contact an administrator.';
                } else {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['account_status'] = $user['status'];

                    $dashboards = [
                        'admin'               => 'admin/dashboard.php',
                        'chairperson'         => 'chairperson/dashboard.php',
                        'vice_chairperson'    => 'vice_chairperson/dashboard.php',
                        'secretary'           => 'secretary/dashboard.php',
                        'vice_secretary'      => 'vice_secretary/dashboard.php',
                        'treasurer'           => 'treasurer/dashboard.php',
                        'projects_officer'    => 'projects_officer/dashboard.php',
                        'assets_officer'      => 'assets_officer/dashboard.php',
                        'christian_education' => 'christian_education/dashboard.php',
                        'youth_chairperson'   => 'youth_chairperson/dashboard.php',
                        'pastor'              => 'pastor/dashboard.php',
                        'chairlady'           => 'chairlady/dashboard.php',
                        'user'                => 'dashboard.php',
                    ];

                    $role = strtolower(trim($user['role']));
                    header('Location: ' . ($dashboards[$role] ?? 'dashboard.php'));
                    exit;
                }
            } else {
                $error = 'Invalid login details.';
            }
        } catch (Throwable $ex) {
            app_log('login', $ex);
            $error = 'Login failed. Please try again.';
        }
    }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login | AIC Kalyet</title>
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

            .login-card {
                width: 100%;
                max-width: 430px;
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

            .login-button {
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

            .login-button:hover {
                background: #105b54;
            }

            .message {
                margin-bottom: 18px;
                padding: 12px;
                border-radius: 7px;
                color: #a61b1b;
                background: #fff0f0;
                font-size: 14px;
                line-height: 1.5;
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
                .login-card {
                    padding: 27px 22px;
                }
            }
        </style>
    </head>
    <body>
        <main class="login-card">
            <div class="church-icon" aria-hidden="true">✝</div>

            <h1>Welcome</h1>
            <p class="subtitle">Sign in to the AIC Kalyet Church Management System</p>

            <?php if ($error !== ''): ?>
                <div class="message" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="login">Username or Email</label>
                    <input
                        type="text"
                        id="login"
                        name="login"
                        value="<?= e($login) ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-button">Sign In</button>
            </form>

            <div class="links">
                Need an account?
                <a href="register.php">Register here</a>
                <br>
                <a href="index.php">Back to home</a>
            </div>

            <div class="footer">
                &copy; <?= date('Y') ?> AIC Kalyet Church Management System
            </div>
        </main>
    </body>
    </html>
}