# Prototype Web Builder

## Getting started

```bash
php -S localhost:8000 -t public
```

Visit:
- `/auth` – login, registration, lost password (email logs stored at `data/mail.log`)
- `/` – authenticated dashboard with web/page builder

## Features
- JSON-based storage in `data/`
- Login/registration/reset without email verification
- Create multiple “webs”, each with nested pages managed via SortableJS tree
- Page editing with URL, tags, WYSIWYG perex
- Custom schema builder per web supporting text, number, wysiwyg, image(s), tags
- Export any web as JSON and re-load via API after login