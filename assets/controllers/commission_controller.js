import { Controller } from '@hotwired/stimulus';

/*
 * Bespoke Commissions: the "For Your Home" / "For Your Project" tabs (no form
 * shows until one is chosen; switching fades the new form in, as in the Site
 * v4 prototype), the phone field's "(optional)" turning to "*" when the
 * customer would like a call, and the file choosers: the chosen files are
 * listed, and files of the wrong type or over the size limits are refused
 * here before anything is sent (the server checks again).
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'phoneOptional', 'phoneRequired', 'fileField', 'fileInput', 'fileList', 'fileError'];
    static values = { client: String, maxFileBytes: Number, maxTotalBytes: Number, extensions: Array };

    connect() {
        this.render(false);
        this.contact();
        this.fileInputTargets.forEach((input) => this.listFiles(input));
    }

    choose(event) {
        const client = event.currentTarget.dataset.client;
        if (client === this.clientValue) return;
        this.clientValue = client;
        this.render(true);
    }

    render(fade) {
        this.tabTargets.forEach((tab) => tab.setAttribute('aria-pressed', String(tab.dataset.client === this.clientValue)));
        this.panelTargets.forEach((panel) => {
            const shown = panel.dataset.client === this.clientValue;
            panel.hidden = !shown;
            if (shown && fade) {
                panel.classList.add('opacity-0');
                requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.remove('opacity-0')));
            }
        });
    }

    // The phone number is required when the customer chooses Phone.
    contact() {
        const chosen = this.element.querySelector('input[name$="[contactMethod]"]:checked');
        const phone = chosen?.value === 'phone';
        this.phoneOptionalTargets.forEach((el) => { el.hidden = phone; });
        this.phoneRequiredTargets.forEach((el) => { el.hidden = !phone; });
    }

    files(event) {
        const input = event.currentTarget;
        const problem = this.problem([...input.files]);
        const error = this.part(input, 'fileError');
        error.textContent = problem ?? '';
        error.hidden = problem === null;
        if (problem !== null) input.value = '';
        this.listFiles(input);
    }

    problem(files) {
        const allowed = this.extensionsValue.map((e) => e.toLowerCase());
        const list = allowed.filter((e) => e !== 'jpeg').map((e) => e.toUpperCase());
        const kinds = `${list.slice(0, -1).join(', ')} or ${list.at(-1)}`;
        for (const file of files) {
            const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
            if (!allowed.includes(extension)) return `"${file.name}" can't be sent: please choose ${kinds} files.`;
            if (file.size > this.maxFileBytesValue) return `"${file.name}" is over ${this.megabytes(this.maxFileBytesValue)}: each file can be up to ${this.megabytes(this.maxFileBytesValue)}.`;
        }
        const total = files.reduce((sum, file) => sum + file.size, 0);
        if (total > this.maxTotalBytesValue) return `These files come to more than ${this.megabytes(this.maxTotalBytesValue)} in total. Please choose fewer or smaller files.`;
        return null;
    }

    listFiles(input) {
        const list = this.part(input, 'fileList');
        list.replaceChildren(...[...input.files].map((file) => {
            const item = document.createElement('li');
            item.textContent = `${file.name} (${this.size(file.size)})`;
            return item;
        }));
    }

    part(input, name) {
        const field = input.closest('[data-commission-target~="fileField"]');
        return field.querySelector(`[data-commission-target~="${name}"]`);
    }

    megabytes(bytes) {
        return `${Math.round(bytes / (1024 * 1024))}MB`;
    }

    size(bytes) {
        if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)}MB`;
        return `${Math.max(1, Math.round(bytes / 1024))}KB`;
    }
}
