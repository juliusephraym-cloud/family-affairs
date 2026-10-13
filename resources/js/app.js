import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Copies text and briefly shows a confirmation; used for account numbers, links.
Alpine.data('copy', (text) => ({
    copied: false,
    async go() {
        try { await navigator.clipboard.writeText(text); this.copied = true; setTimeout(() => (this.copied = false), 1600); } catch (e) {}
    },
}));

// Drag-free kanban: status changes are plain form posts, this only handles optimistic UI.
Alpine.data('otpInput', () => ({
    code: '',
    onInput(e) {
        this.code = e.target.value.replace(/\D/g, '').slice(0, 6);
        e.target.value = this.code;
        if (this.code.length === 6) this.$refs.form.requestSubmit();
    },
}));

// WhatsApp composer: live preview, recipient count and cost from the server.
Alpine.data('composer', (templates, url) => ({
    templates,
    templateId: (templates.find((t) => !t.sensitive) ?? templates[0])?.id ?? null,
    audience: 'all_active',
    when: 'later',
    preview: '',
    recipients: null,
    cost: null,
    needsApproval: false,
    get current() { return this.templates.find((t) => t.id === Number(this.templateId)); },
    init() { this.$nextTick(() => this.refresh()); },
    async refresh() {
        try {
            const r = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(this.$root) });
            if (!r.ok) { this.recipients = 0; this.cost = 0; this.preview = this.current?.body ?? ''; return; }
            const d = await r.json();
            Object.assign(this, { preview: d.preview, recipients: d.recipients, cost: d.cost, needsApproval: d.needs_approval });
        } catch (e) { /* offline: keep last preview */ }
    },
}));

window.Alpine = Alpine;
Alpine.start();
