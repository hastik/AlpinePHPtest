<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

ensure_session();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/auth')) {
    require __DIR__ . '/auth.php';
    exit;
}

if ($path === '/logout') {
    session_destroy();
    header('Location: /auth');
    exit;
}

require_login();
$user = current_user();
$webs = webs_for_user($user['id']);

?>
<!doctype html>
<html lang="en" x-data='webBuilderApp(<?= json_encode($webs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
<head>
    <meta charset="UTF-8">
    <title>Web Prototype Builder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script defer src="/assets/js/app.js"></script>
</head>
<body class="dashboard" x-init="init()">
<header class="app-header">
    <h1>Welcome, <?= htmlspecialchars($user['name']); ?></h1>
    <div class="header-actions">
        <button @click="openCreateWeb()">Create new web</button>
        <a href="/logout" class="secondary">Logout</a>
    </div>
</header>

<main class="layout">
    <aside class="web-list">
        <h2>Your webs</h2>
        <template x-if="webs.length === 0">
            <p class="muted">No webs yet. Create one to start.</p>
        </template>
        <ul>
            <template x-for="web in webs" :key="web.id">
                <li :class="{'is-active': web.id === selectedWebId}" @click="selectWeb(web.id)">
                    <div>
                        <strong x-text="web.name"></strong>
                        <small x-text="web.slug"></small>
                    </div>
                    <div class="web-actions">
                        <button class="ghost" @click.stop="exportWeb(web)">Export</button>
                        <button class="ghost danger" @click.stop="removeWeb(web.id)">Delete</button>
                    </div>
                </li>
            </template>
        </ul>
    </aside>

    <section class="workspace" x-show="activeWeb" x-cloak>
        <header class="workspace-header">
            <div>
                <h2 x-text="activeWeb?.name"></h2>
                <p class="muted">Manage pages, custom data and preview.</p>
            </div>
            <button class="secondary" @click="saveActiveWeb" :disabled="isSaving">
                <span x-show="!isSaving">Save</span>
                <span x-show="isSaving">Saving...</span>
            </button>
        </header>

        <div class="workspace-body">
            <div class="pane pane-tree">
                <div class="pane-header">
                    <h3>Pages</h3>
                    <button class="ghost" @click="addPage()">+ Page</button>
                </div>
                <div class="tree-container" x-ref="treeRoot"></div>
            </div>

            <div class="pane pane-editor" x-show="activePage" x-cloak>
                <div class="pane-header">
                    <h3>Page settings</h3>
                    <button class="ghost" @click="addChildPage()">+ Child page</button>
                </div>
                <div class="form-grid">
                    <label>
                        Name
                        <input type="text" x-model="activePage.name">
                    </label>
                    <label>
                        URL
                        <input type="text" x-model="activePage.url">
                    </label>
                    <label>
                        Tags
                        <input type="text" :value="activePage.tags.join(', ')" @input="updateTags($event.target.value)">
                    </label>
                </div>
                <label>
                    Perex
                    <div class="wysiwyg">
                        <div class="toolbar">
                            <button type="button" @click="format('bold')"><b>B</b></button>
                            <button type="button" @click="format('italic')"><i>I</i></button>
                            <button type="button" @click="format('insertUnorderedList')">•</button>
                        </div>
                        <div class="editor" contenteditable="true" x-ref="perexEditor" @input="updatePerex" x-html="activePage.perex"></div>
                    </div>
                </label>
                <div class="custom-data">
                    <div class="pane-header">
                        <h4>Custom data</h4>
                        <select x-model="activePage.schemaId" @change="handleSchemaChange">
                            <option value="">No schema</option>
                            <template x-for="schema in activeWeb.customSchemas" :key="schema.id">
                                <option :value="schema.id" x-text="schema.name"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="activeSchema">
                        <div class="schema-fields">
                            <template x-for="field in activeSchema.fields" :key="field.id">
                                <label>
                                    <span x-text="field.label"></span>
                                    <template x-if="['text','string'].includes(field.type)">
                                        <input type="text" :value="activePage.customData[field.id] || ''" @input="updateField(field.id, $event.target.value)">
                                    </template>
                                    <template x-if="field.type === 'number'">
                                        <input type="number" :value="activePage.customData[field.id] || ''" @input="updateField(field.id, $event.target.value)">
                                    </template>
                                    <template x-if="field.type === 'wysiwyg'">
                                        <textarea :value="activePage.customData[field.id] || ''" @input="updateField(field.id, $event.target.value)"></textarea>
                                    </template>
                                    <template x-if="field.type === 'image'">
                                        <input type="url" placeholder="Image URL" :value="activePage.customData[field.id] || ''" @input="updateField(field.id, $event.target.value)">
                                    </template>
                                    <template x-if="field.type === 'images'">
                                        <textarea placeholder="One URL per line" :value="(activePage.customData[field.id] || []).join('\\n')" @input="updateField(field.id, $event.target.value.split(/\\n+/).filter(Boolean))"></textarea>
                                    </template>
                                    <template x-if="field.type === 'tags'">
                                        <input type="text" placeholder="comma,separated" :value="(activePage.customData[field.id] || []).join(', ')" @input="updateField(field.id, $event.target.value.split(',').map(t => t.trim()).filter(Boolean))">
                                    </template>
                                </label>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="pane pane-schemas">
                <div class="pane-header">
                    <h3>Custom schemas</h3>
                    <button class="ghost" @click="addSchema">+ Schema</button>
                </div>
                <template x-if="activeWeb.customSchemas.length === 0">
                    <p class="muted">No schemas yet.</p>
                </template>
                <template x-for="schema in activeWeb.customSchemas" :key="schema.id">
                    <div class="schema-card">
                        <input type="text" x-model="schema.name" placeholder="Schema name">
                        <template x-for="field in schema.fields" :key="field.id">
                            <div class="schema-field">
                                <input type="text" x-model="field.label" placeholder="Label">
                                <select x-model="field.type">
                                    <option value="text">Text</option>
                                    <option value="string">String</option>
                                    <option value="number">Number</option>
                                    <option value="wysiwyg">Wysiwyg</option>
                                    <option value="image">Image</option>
                                    <option value="images">Images</option>
                                    <option value="tags">Tags</option>
                                </select>
                                <button class="ghost danger" @click="removeField(schema.id, field.id)">×</button>
                            </div>
                        </template>
                        <button class="ghost" @click="addField(schema.id)">Add field</button>
                    </div>
                </template>
            </div>
        </div>
    </section>
</main>

<template x-if="showModal">
    <div class="modal-backdrop">
        <div class="modal">
            <h3>Create new web</h3>
            <label>
                Name
                <input type="text" x-model="modalForm.name">
            </label>
            <label>
                Slug
                <input type="text" x-model="modalForm.slug">
            </label>
            <footer class="modal-actions">
                <button class="secondary" @click="closeModal">Cancel</button>
                <button @click="submitModal">Create</button>
            </footer>
        </div>
    </div>
</template>

</body>
</html>
