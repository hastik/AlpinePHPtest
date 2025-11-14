<?php

declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';

function data_path(string $basename): string
{
    return DATA_DIR . '/' . ltrim($basename, '/');
}

function load_json(string $basename, array $default = []): array
{
    $path = data_path($basename);
    if (!file_exists($path)) {
        return $default;
    }

    $json = file_get_contents($path);
    if ($json === false || trim($json) === '') {
        return $default;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $default;
    }

    return $decoded;
}

function save_json(string $basename, array $data): void
{
    $path = data_path($basename);
    $tmpPath = $path . '.tmp';
    file_put_contents($tmpPath, json_encode($data, JSON_PRETTY_PRINT));
    rename($tmpPath, $path);
}

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

function current_user(): ?array
{
    ensure_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /auth');
        exit;
    }
}

function find_user_by_email(string $email): ?array
{
    $data = load_json('users.json', ['users' => []]);
    foreach ($data['users'] as $user) {
        if (strcasecmp($user['email'], $email) === 0) {
            return $user;
        }
    }

    return null;
}

function store_user(array $user): void
{
    $data = load_json('users.json', ['users' => []]);
    $found = false;
    foreach ($data['users'] as &$existing) {
        if ($existing['id'] === $user['id']) {
            $existing = $user;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $data['users'][] = $user;
    }
    save_json('users.json', $data);
}

function next_id(): string
{
    return bin2hex(random_bytes(8));
}

function load_all_webs(): array
{
    return load_json('webs.json', ['webs' => []]);
}

function save_all_webs(array $data): void
{
    save_json('webs.json', $data);
}

function save_web_record(array $web): void
{
    $data = load_all_webs();
    $found = false;
    foreach ($data['webs'] as &$existing) {
        if ($existing['id'] === $web['id']) {
            $existing = $web;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $data['webs'][] = $web;
    }
    save_all_webs($data);
}

function webs_for_user(string $userId): array
{
    $data = load_all_webs();
    return array_values(array_filter(
        $data['webs'],
        fn(array $web) => $web['owner_id'] === $userId
    ));
}

function delete_web(string $webId, string $userId): bool
{
    $data = load_all_webs();
    $before = count($data['webs']);
    $data['webs'] = array_values(array_filter(
        $data['webs'],
        fn(array $web) => !($web['id'] === $webId && $web['owner_id'] === $userId)
    ));
    if (count($data['webs']) === $before) {
        return false;
    }
    save_all_webs($data);
    return true;
}

function find_web(string $webId): ?array
{
    $data = load_all_webs();
    foreach ($data['webs'] as $web) {
        if ($web['id'] === $webId) {
            return $web;
        }
    }
    return null;
}

function create_reset_token(string $userId): array
{
    $data = load_json('reset_tokens.json', ['tokens' => []]);
    $token = [
        'id' => next_id(),
        'user_id' => $userId,
        'token' => bin2hex(random_bytes(16)),
        'created_at' => time(),
    ];
    $data['tokens'][] = $token;
    save_json('reset_tokens.json', $data);
    return $token;
}

function find_reset_token(string $tokenValue): ?array
{
    $data = load_json('reset_tokens.json', ['tokens' => []]);
    foreach ($data['tokens'] as $token) {
        if (hash_equals($token['token'], $tokenValue)) {
            return $token;
        }
    }
    return null;
}

function consume_reset_token(string $tokenValue): void
{
    $data = load_json('reset_tokens.json', ['tokens' => []]);
    $data['tokens'] = array_values(array_filter(
        $data['tokens'],
        fn(array $token) => !hash_equals($token['token'], $tokenValue)
    ));
    save_json('reset_tokens.json', $data);
}

