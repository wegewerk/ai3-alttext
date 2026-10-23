import ModalElement from "@typo3/backend/modal.js";
import {html} from 'lit-html';
import {LitElement} from 'lit-element';
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";

/**
 * Preview image tile for files in the folder queue (awaiting approval / processing).
 * Shows thumbnail or placeholder icon, file name or alt-text suggestion, and a badge.
 */
class QueueImageElement extends LitElement {
    static properties = {
        file: {type: Object},
        showSuggestion: {type: Boolean, attribute: 'show-suggestion'},
        badgeText: {type: String, attribute: 'badge-text'},
        badgeClass: {type: String, attribute: 'badge-class'},
    }
    constructor() {
        super();
        this.file = null;
        this.showSuggestion = false;
        this.badgeText = '';
        this.badgeClass = 'text-bg-secondary';
    }
    // Shadow DOM nicht verwenden, global styles sollen gültig bleiben
    createRenderRoot() {
        return this;
    }

    openImageModal() {
        const file = this.file || {};
        const imageUrl = file.publicUrl || file.thumbnailUrl;
        if (!imageUrl) {
            return;
        }
        const altText = file.altSuggestion || file.name;
        ModalElement.advanced({
            content: html`<img style="width: 100%; height: 100%; object-fit: contain;" src="${imageUrl}" alt="${altText}">`,
            title: file.name,
            staticBackdrop: false,
            additionalCssClasses: ['modal-size-expand'],
            callback: (modal) => {
                if (!file.altSuggestion) {
                    return;
                }
                const body = modal.querySelector('.t3js-modal-body');
                if (!body) {
                    return;
                }
                const footer = document.createElement('div');
                footer.className = 'modal-footer t3js-modal-footer';
                const suggestion = document.createElement('div');
                suggestion.className = 'me-auto text-start text-break';
                suggestion.textContent = file.altSuggestion;
                footer.appendChild(suggestion);
                body.insertAdjacentElement('afterend', footer);
            },
        });
    }

    render() {
        const file = this.file || {};
        console.log(file);
        const previewUrl = file.thumbnailUrl || file.publicUrl;
        const altText = file.altSuggestion || file.name;
        return html`
            <div class="text-center" style="width: 160px;">
                ${previewUrl ? html`
                    <img src="${previewUrl}" alt="${altText}" title="${altText}"
                         class="rounded border" style="width: 96px; height: 72px; object-fit: cover; cursor: zoom-in;"
                         @click="${this.openImageModal.bind(this)}">
                ` : html`
                    <div class="rounded border bg-light d-flex align-items-center justify-content-center"
                         style="width: 96px; height: 72px;">${Typo3Icon('default-content-file-unknown', 'medium')}</div>
                `}
                ${this.showSuggestion ? html`
                    <div class="small mt-1 text-break" title="${altText}">${altText}</div>
                ` : html`
                    <div class="small text-truncate" style="max-width: 96px;" title="${file.name}">${file.name}</div>
                `}
                ${this.badgeText ? html`
                    <span class="badge ${this.badgeClass}">${this.badgeText}</span>
                ` : ''}
            </div>
        `;
    }
}
customElements.define('queue-image-element', QueueImageElement);
