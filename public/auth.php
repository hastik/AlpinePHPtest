<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

ensure_session();

$errors = [];
$success = null;
$tokenParam = $_GET['token'] ?? null;
$mode = $_POST['mode'] ?? ($_GET['mode'] ?? ($tokenParam ? 'reset' : 'login'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'login') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $user = find_user_by_email($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            $_SESSION['user'] = $user;
            header('Location: /');
            exit;
        }
    } elseif ($mode === 'register') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $name === '' || $password === '') {
            $errors[] = 'All fields are required.';
        } elseif (find_user_by_email($email)) {
            $errors[] = 'Email already registered.';
        } else {
            $user = [
                'id' => next_id(),
                'email' => $email,
                'name' => $name,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => time(),
            ];
            store_user($user);
            $_SESSION['user'] = $user;
            header('Location: /');
            exit;
        }
    } elseif ($mode === 'forgot') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $user = find_user_by_email($email);
        if (!$user) {
            $errors[] = 'If the account exists you will receive an email shortly.';
        } else {
            $token = create_reset_token($user['id']);
            $link = sprintf('%s://%s/auth?token=%s', isset($_SERVER['HTTPS']) ? 'https' : 'http', $_SERVER['HTTP_HOST'], $token['token']);
            $body = "Hi {$user['name']},\n\nReset your password using this link:\n{$link}\n\n";
            file_put_contents(data_path('mail.log'), sprintf("[%s] To:%s\n%s\n\n", date('c'), $user['email'], $body), FILE_APPEND);
            $success = 'Password reset instructions were sent to your email.';
        }
    } elseif ($mode === 'reset') {
        $tokenValue = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        if ($password === '') {
            $errors[] = 'Password is required.';
        } else {
            $tokenRecord = find_reset_token($tokenValue);
            if (!$tokenRecord) {
                $errors[] = 'Invalid or expired token.';
            } else {
                $data = load_json('users.json', ['users' => []]);
                foreach ($data['users'] as &$user) {
                    if ($user['id'] === $tokenRecord['user_id']) {
                        $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                        store_user($user);
                        consume_reset_token($tokenValue);
                        $success = 'Password updated. You can now login.';
                        break;
                    }
                }
                $mode = 'login';
            }
        }
    }
}

if (current_user()) {
    header('Location: /');
    exit;
}

function active(string $current, string $mode): string
{
    return $current === $mode ? 'is-active' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auth • Web Prototype</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="auth-body">
<main class="auth-card">
    <h1>Access your workspace</h1>
    <nav class="tab-nav">
        <a href="?mode=login" class="<?= active('login', $mode); ?>">Login</a>
        <a href="?mode=register" class="<?= active('register', $mode); ?>">Register</a>
        <a href="?mode=forgot" class="<?= active('forgot', $mode); ?>">Lost password</a>
    </nav>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?= implode('<br>', array_map('htmlspecialchars', $errors)); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">
            <?= htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'login'): ?>
        <form method="post" class="stacked-form">
            <input type="hidden" name="mode" value="login">
            <label>Email <input type="email" name="email" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button type="submit">Login</button>
        </form>
    <?php elseif ($mode === 'register'): ?>
        <form method="post" class="stacked-form">
            <input type="hidden" name="mode" value="register">
            <label>Name <input type="text" name="name" required></label>
            <label>Email <input type="email" name="email" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button type="submit">Create account</button>
        </form>
    <?php elseif ($mode === 'forgot'): ?>
        <form method="post" class="stacked-form">
            <input type="hidden" name="mode" value="forgot">
            <label>Email <input type="email" name="email" required></label>
            <button type="submit">Send reset link</button>
        </form>
    <?php else: ?>
        <form method="post" class="stacked-form">
            <input type="hidden" name="mode" value="reset">
            <input type="hidden" name="token" value="<?= htmlspecialchars($tokenParam ?? ($_POST['token'] ?? '')); ?>">
            <label>New password <input type="password" name="password" required></label>
            <button type="submit">Update password</button>
        </form>
    <?php endif; ?>

    <p class="dev-hint">
        Lost password emails are logged to <code>data/mail.log</code> for this prototype.
    </p>
</main>
</body>
</html>
