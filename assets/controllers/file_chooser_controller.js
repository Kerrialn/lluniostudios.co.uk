import { Controller } from '@hotwired/stimulus';

/*
 * A file chooser (Bespoke Commissions, and Reserve Your Design's reference
 * photos, 10 October 2026): the chosen files are listed, and files of the
 * wrong type or over the size limits are refused here before anything is
 * sent (the server checks again, CommissionFields::uploadProblem(), in the
 * same words).
 */
export default class extends Controller {
    static targets = ['input', 'list', 'error'];
    static values = { maxFileBytes: Number, maxTotalBytes: Number, extensions: Array };

    connect() {
        this.listFiles();
    }

    choose() {
        const input = this.inputTarget;
        const problem = this.problem([...input.files]);
        this.errorTarget.textContent = problem ?? '';
        this.errorTarget.hidden = problem === null;
        if (problem !== null) input.value = '';
        this.listFiles();
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

    listFiles() {
        this.listTarget.replaceChildren(...[...this.inputTarget.files].map((file) => {
            const item = document.createElement('li');
            item.textContent = `${file.name} (${this.size(file.size)})`;
            return item;
        }));
    }

    megabytes(bytes) {
        return `${Math.round(bytes / (1024 * 1024))}MB`;
    }

    size(bytes) {
        if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)}MB`;
        return `${Math.max(1, Math.round(bytes / 1024))}KB`;
    }
}
