<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

header('Content-Type: application/json');
ensure_session();

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($payload['action'] ?? null);

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action']);
    exit;
}

try {
    switch ($action) {
        case 'load_webs':
            echo json_encode(['webs' => webs_for_user($user['id'])]);
            break;
        case 'create_web':
            echo json_encode(['web' => create_web($user, $payload)]);
            break;
        case 'save_web':
            echo json_encode(['web' => save_web($user, $payload['web'] ?? [])]);
            break;
        case 'delete_web':
            handle_delete_web($user, $payload);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}

function create_web(array $user, array $payload): array
{
    $name = trim($payload['name'] ?? '');
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }

    $slug = trim($payload['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }

    $all = load_all_webs();
    foreach ($all['webs'] as $web) {
        if ($web['owner_id'] === $user['id'] && $web['slug'] === $slug) {
            throw new InvalidArgumentException('Slug already exists.');
        }
    }

    $web = [
        'id' => next_id(),
        'owner_id' => $user['id'],
        'name' => $name,
        'slug' => $slug,
        'pages' => [default_page()],
        'customSchemas' => [],
        'created_at' => time(),
        'updated_at' => time(),
    ];

    save_web_record($web);
    return $web;
}

function default_page(): array
{
    return [
        'id' => next_id(),
        'name' => 'Home',
        'url' => '/',
        'perex' => '<p>Your content...</p>',
        'tags' => [],
        'schemaId' => null,
        'customData' => [],
        'children' => [],
    ];
}

function save_web(array $user, array $payload): array
{
    if (!isset($payload['id'])) {
        throw new InvalidArgumentException('Web id missing.');
    }

    $existing = find_web($payload['id']);
    if (!$existing || $existing['owner_id'] !== $user['id']) {
        throw new RuntimeException('Web not found.');
    }

    $clean = [
        'id' => $existing['id'],
        'owner_id' => $user['id'],
        'name' => trim($payload['name'] ?? $existing['name']),
        'slug' => trim($payload['slug'] ?? $existing['slug']),
        'pages' => sanitize_pages($payload['pages'] ?? []),
        'customSchemas' => sanitize_schemas($payload['customSchemas'] ?? []),
        'created_at' => $existing['created_at'],
        'updated_at' => time(),
    ];

    save_web_record($clean);
    return $clean;
}

function sanitize_pages(array $pages): array
{
    $clean = [];
    foreach ($pages as $page) {
        if (!isset($page['id'])) {
            $page['id'] = next_id();
        }
        $clean[] = [
            'id' => (string)$page['id'],
            'name' => trim($page['name'] ?? 'Untitled'),
            'url' => trim($page['url'] ?? '/'),
            'perex' => $page['perex'] ?? '',
            'tags' => array_values(array_filter(array_map('trim', $page['tags'] ?? []))),
            'schemaId' => $page['schemaId'] ?? null,
            'customData' => sanitize_custom_data($page['customData'] ?? []),
            'children' => sanitize_pages($page['children'] ?? []),
        ];
    }
    return $clean;
}

function sanitize_schemas(array $schemas): array
{
    $allowed = ['text', 'string', 'number', 'wysiwyg', 'image', 'images', 'tags'];
    $result = [];
    foreach ($schemas as $schema) {
        $fields = [];
        foreach ($schema['fields'] ?? [] as $field) {
            if (!in_array($field['type'], $allowed, true)) {
                continue;
            }
            $fields[] = [
                'id' => $field['id'] ?? next_id(),
                'label' => trim($field['label'] ?? 'Field'),
                'type' => $field['type'],
            ];
        }
        $result[] = [
            'id' => $schema['id'] ?? next_id(),
            'name' => trim($schema['name'] ?? 'Schema'),
            'fields' => $fields,
        ];
    }
    return $result;
}

function sanitize_custom_data(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = array_values($value);
        } elseif (is_scalar($value)) {
            $data[$key] = $value;
        } else {
            unset($data[$key]);
        }
    }
    return $data;
}

function handle_delete_web(array $user, array $payload): void
{
    $webId = $payload['id'] ?? null;
    if (!$webId) {
        throw new InvalidArgumentException('Missing id.');
    }

    if (!delete_web($webId, $user['id'])) {
        throw new RuntimeException('Web not found.');
    }

    echo json_encode(['status' => 'ok']);
}
