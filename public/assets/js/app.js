const deepClone = (value) =>
    typeof structuredClone === 'function'
        ? structuredClone(value)
        : JSON.parse(JSON.stringify(value));

function uuid() {
    if (crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxx'.replace(/x/g, () =>
        Math.floor(Math.random() * 16).toString(16)
    );
}

window.webBuilderApp = function (initialWebs) {
    return {
        webs: initialWebs || [],
        selectedWebId: initialWebs?.[0]?.id || null,
        selectedPageId: initialWebs?.[0]?.pages?.[0]?.id || null,
        isSaving: false,
        showModal: false,
        modalForm: {name: '', slug: ''},
        treeInstances: [],

        get activeWeb() {
            return this.webs.find((web) => web.id === this.selectedWebId) || null;
        },

        get activePage() {
            if (!this.activeWeb) return null;
            return this.findPageById(this.activeWeb.pages, this.selectedPageId);
        },

        get activeSchema() {
            if (!this.activeWeb || !this.activePage || !this.activePage.schemaId) return null;
            return this.activeWeb.customSchemas.find((schema) => schema.id === this.activePage.schemaId) || null;
        },

        init() {
            this.ensureSelections();
            this.$watch('selectedWebId', () => {
                this.ensureSelections();
                this.renderTree();
            });
            this.renderTree();
        },

        ensureSelections() {
            if (!this.activeWeb && this.webs.length) {
                this.selectedWebId = this.webs[0].id;
            }
            if (this.activeWeb && !this.activePage) {
                this.activeWeb.pages = this.activeWeb.pages || [];
                this.activeWeb.customSchemas = this.activeWeb.customSchemas || [];
                const first = this.activeWeb.pages?.[0];
                this.selectedPageId = first ? first.id : null;
            }
            this.$nextTick(() => this.syncPerexEditor());
        },

        selectWeb(id) {
            this.selectedWebId = id;
            const web = this.activeWeb;
            this.selectedPageId = web?.pages?.[0]?.id || null;
        },

        selectPage(id) {
            this.selectedPageId = id;
            this.$nextTick(() => this.syncPerexEditor());
        },

        openCreateWeb() {
            this.modalForm = {name: '', slug: ''};
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },

        async submitModal() {
            if (!this.modalForm.name.trim()) return;
            const payload = {...this.modalForm};
            const res = await fetch('/api.php?action=create_web', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                alert('Failed to create web');
                return;
            }
            const data = await res.json();
            this.webs.push(data.web);
            this.closeModal();
            this.selectWeb(data.web.id);
            this.renderTree();
        },

        addPage() {
            if (!this.activeWeb) return;
            this.activeWeb.pages.push(this.newPage());
            const last = this.activeWeb.pages[this.activeWeb.pages.length - 1];
            this.selectedPageId = last?.id || null;
            this.renderTree();
        },

        addChildPage() {
            const page = this.activePage;
            if (!page) return;
            page.children = page.children || [];
            const child = this.newPage();
            page.children.push(child);
            this.selectedPageId = child.id;
            this.renderTree();
        },

        addChildPageTo(pageId) {
            const page = this.findPageById(this.activeWeb.pages, pageId);
            if (!page) return;
            page.children = page.children || [];
            const child = this.newPage();
            page.children.push(child);
            this.selectedPageId = child.id;
            this.renderTree();
        },

        deletePage(pageId) {
            if (!this.activeWeb) return;
            const removeRecursive = (pages) => {
                return pages
                    .map((page) => {
                        if (page.id === pageId) {
                            return null;
                        }
                        page.children = removeRecursive(page.children || []);
                        return page;
                    })
                    .filter(Boolean);
            };
            this.activeWeb.pages = removeRecursive(this.activeWeb.pages);
            if (this.selectedPageId === pageId) {
                this.selectedPageId = this.activeWeb.pages?.[0]?.id || null;
            }
            this.renderTree();
        },

        syncPerexEditor() {
            if (!this.$refs.perexEditor || !this.activePage) return;
            this.$refs.perexEditor.innerHTML = this.activePage.perex || '';
        },

        updatePerex() {
            if (this.activePage && this.$refs.perexEditor) {
                this.activePage.perex = this.$refs.perexEditor.innerHTML;
            }
        },

        format(command) {
            document.execCommand(command, false, null);
            this.updatePerex();
        },

        updateTags(value) {
            if (!this.activePage) return;
            this.activePage.tags = value
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean);
        },

        handleSchemaChange() {
            if (!this.activePage) return;
            this.activePage.customData = this.activePage.customData || {};
        },

        updateField(fieldId, value) {
            if (!this.activePage) return;
            this.activePage.customData = {...(this.activePage.customData || {}), [fieldId]: value};
        },

        addSchema() {
            if (!this.activeWeb) return;
            this.activeWeb.customSchemas.push({
                id: uuid(),
                name: 'New schema',
                fields: [],
            });
        },

        addField(schemaId) {
            const schema = this.activeWeb?.customSchemas.find((s) => s.id === schemaId);
            if (!schema) return;
            schema.fields.push({
                id: uuid(),
                label: 'Field',
                type: 'text',
            });
        },

        removeField(schemaId, fieldId) {
            const schema = this.activeWeb?.customSchemas.find((s) => s.id === schemaId);
            if (!schema) return;
            schema.fields = schema.fields.filter((f) => f.id !== fieldId);
        },

        newPage() {
            return {
                id: uuid(),
                name: 'Untitled',
                url: '/',
                perex: '',
                tags: [],
                schemaId: null,
                customData: {},
                children: [],
            };
        },

        findPageById(pages, id) {
            if (!id) return null;
            for (const page of pages || []) {
                if (page.id === id) return page;
                const child = this.findPageById(page.children || [], id);
                if (child) return child;
            }
            return null;
        },

        addTreeListeners(li, page) {
            const node = li.querySelector('.page-node');
            node.addEventListener('click', () => this.selectPage(page.id));
            node.querySelector('[data-action="add-child"]').addEventListener('click', (event) => {
                event.stopPropagation();
                this.addChildPageTo(page.id);
            });
            node.querySelector('[data-action="delete"]').addEventListener('click', (event) => {
                event.stopPropagation();
                this.deletePage(page.id);
            });
        },

        renderTree() {
            const container = this.$refs.treeRoot;
            if (!container) return;
            this.destroySortables();
            container.innerHTML = '';
            if (!this.activeWeb || !this.activeWeb.pages.length) {
                container.innerHTML = '<p class="muted">No pages yet.</p>';
                return;
            }

            const buildList = (pages) => {
                const ul = document.createElement('ul');
                ul.className = 'page-tree';
                pages.forEach((page) => {
                    const li = document.createElement('li');
                    li.dataset.pageId = page.id;
                    const node = document.createElement('div');
                    node.className = 'page-node' + (page.id === this.selectedPageId ? ' is-active' : '');
                    node.innerHTML = `
                        <span>${page.name || 'Untitled'}</span>
                        <div class="actions">
                            <button class="ghost" data-action="add-child">+</button>
                            <button class="ghost danger" data-action="delete">×</button>
                        </div>
                    `;
                    li.appendChild(node);

                    const childWrap = document.createElement('div');
                    childWrap.className = 'page-children';
                    if (page.children && page.children.length) {
                        childWrap.appendChild(buildList(page.children));
                    } else {
                        const empty = document.createElement('ul');
                        empty.className = 'page-tree';
                        childWrap.appendChild(empty);
                    }
                    li.appendChild(childWrap);
                    ul.appendChild(li);
                    this.addTreeListeners(li, page);
                });
                return ul;
            };

            const tree = buildList(this.activeWeb.pages);
            container.appendChild(tree);
            this.setupSortables(container);
        },

        setupSortables(container) {
            const lists = container.querySelectorAll('ul.page-tree');
            lists.forEach((list) => {
                const instance = Sortable.create(list, {
                    group: 'pages',
                    animation: 150,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    handle: '.page-node',
                    filter: '.actions',
                    onEnd: () => this.syncPagesFromDom(),
                });
                this.treeInstances.push(instance);
            });
        },

        destroySortables() {
            this.treeInstances.forEach((instance) => instance.destroy());
            this.treeInstances = [];
        },

        syncPagesFromDom() {
            if (!this.activeWeb) return;
            const container = this.$refs.treeRoot;
            const build = (ul) => {
                const items = Array.from(ul.children).filter((el) => el.matches('li'));
                return items.map((li) => {
                    const pageId = li.dataset.pageId;
                    const existing = deepClone(this.findPageById(this.activeWeb.pages, pageId)) || this.newPage();
                    const childUl = li.querySelector(':scope > .page-children > ul');
                    existing.children = childUl ? build(childUl) : [];
                    return existing;
                });
            };
            const rootList = container.querySelector(':scope > ul.page-tree');
            if (!rootList) return;
            this.activeWeb.pages = build(rootList);
            this.renderTree();
        },

        async saveActiveWeb() {
            if (!this.activeWeb) return;
            this.isSaving = true;
            const payload = {
                web: JSON.parse(JSON.stringify(this.activeWeb)),
            };
            const res = await fetch('/api.php?action=save_web', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
            this.isSaving = false;
            if (!res.ok) {
                alert('Failed to save');
                return;
            }
            const data = await res.json();
            const index = this.webs.findIndex((w) => w.id === data.web.id);
            if (index >= 0) {
                this.webs[index] = data.web;
            }
            this.renderTree();
        },

        async removeWeb(id) {
            if (!confirm('Delete this web?')) return;
            await fetch('/api.php?action=delete_web', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id}),
            });
            this.webs = this.webs.filter((web) => web.id !== id);
            if (this.selectedWebId === id) {
                this.selectedWebId = this.webs[0]?.id || null;
            }
            this.renderTree();
        },

        exportWeb(web) {
            const data = JSON.stringify(web, null, 2);
            const blob = new Blob([data], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${web.slug || 'web'}.json`;
            link.click();
            URL.revokeObjectURL(url);
        },
    };
};
