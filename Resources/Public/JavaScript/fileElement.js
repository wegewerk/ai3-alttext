import {lll} from "@typo3/core/lit-helper.js";
import AjaxRequest from "@typo3/core/ajax/ajax-request.js";
import Notification from "@typo3/backend/notification.js";
import {html, render} from 'lit-html';
import {LitElement } from 'lit-element';
import Ai3Api from './ai3api.js'
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";

class fileElement extends LitElement {
    static properties = {
            inProgress: {type: Boolean},
            isGenerated: {type: Boolean},
            hasGeneration: {type: Boolean},
            isImage: {type: Boolean},
            isReviewed: {type: Boolean},
            isSelected: {type: Boolean},
            isEditing: {type: Boolean},
            uid: {type: String},
            thumbnailUrl: {type: String},
            alternative: {type: String},
            title: {type: String},
            name: {type: String},
            description: {type: String},
            altSuggestion: {type: String},
            editlink: {type: String},
            numrefs: {type: String},
            publicUrl: {type: String},
    }
    constructor() {
        super();
        this.inProgress = false;
        this.isGenerated = false;
        this.hasGeneration = false;
        this.isImage = true;
        this.isReviewed = false;
        this.isSelected = false;
        this.isEditing = false;
        this.uid = '';
        this.thumbnailUrl = '';
        this.alternative = '';
        this.title = '';
        this.name = '';
        this.description = '';
        this.altSuggestion = '';
        this.editlink = '';
        this.numrefs = '0';
        this.publicUrl = '';
    }
    // Shadow DOM nicht verwenden, global styles sollen gültig bleiben
    // custom element tag nicht rendern, ersetzen durch <tr>
    createRenderRoot() {
        return this.parentNode.appendChild(document.createElement('tr'));
    }
    handleEditFile(e) {
        window.location.href=this.editlink+'&returnUrl='+window.location.href;
    }
    handleGenerateTask() {
        Ai3Api.prototype.createAlttextTask(this);
    }
    handleAltTextedit(e) {
        this.isEditing=true;
        this.requestUpdate();
    }
    handleEditSuggestion(e) {
        this.isEditingSuggestion = true;
        this.requestUpdate();
    }
    acceptModifiedAltSuggestion(e){
        this.alternative = this.altSuggestion;
        this.isEditingSuggestion = false;
        this.saveFile(e);
    }
    cancelSuggestionEdit(e) {
        this.isEditingSuggestion=false;
        this.requestUpdate();
    }
    cancelAltTextedit(e) {
        this.isEditing=false;
        this.requestUpdate();
    }
    saveFile(e) {
        const postData = {
            uid: this.uid,
            altText: this.alternative
        }
        let self = this;
        new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_filelist_save_file'])
            .post(postData)
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                self.isEditing=false;
                self.isGenerated=false;
                self.hasGeneration=false;
                self.requestUpdate();
            })
            .catch(async function (response) {
                if (response.resolve) {
                    const resolved = await response.resolve();
                    const responseBody = JSON.parse(resolved);
                    Notification.error('fehler', responseBody.message);
                } else {
                    Notification.error('fehler', response);
                }
            });
    }
    acceptAltSuggestion(e) {
        Ai3Api.prototype.acceptAltSuggestion(this);
    }
    handleSelect(e) {
        this.isSelected = e.target.checked;
        this.dispatchEvent(new CustomEvent('fileSelect', {
            detail: {
                selected:this.isSelected,
                uid:this.uid
            },
            bubbles: true,
            composed: true
        }));
    }
    handleImageclick(e) {
        this.dispatchEvent(new CustomEvent('viewImage', {
            detail: {
                file: this,
            },
            bubbles: true,
            composed: true
        }));
    }

    render() {
        return html`
            <td>
                <div class="form-check m-0">
                    <input aria-label="${lll('tx_ai3.module.filemetadata.select')}" class="form-check-input"
                           name="ai3_batchprocess_selected"
                           type="checkbox"
                           .checked=${this.isSelected}
                           @change="${this.handleSelect}"
                    >
                </div>
            </td>
            <td>
                ${(this.isImage && this.thumbnailUrl)
                ? html`
                                                <img alt="${this.alternative}" class="img-fluid rounded"
                                                     src="${this.thumbnailUrl}"
                                                     @click="${this.handleImageclick}"
                                                     style="max-width: 150px; height: auto;"/>
                                            `
                : html`
                                                <div class="d-flex align-items-center justify-content-center bg-light border rounded"
                                                     style="width: 150px; height: 64px;">
                                                    ${Typo3Icon('mimetypes-other-other')}
                                                </div>
                                            `
            }
            </td>
            <td>${this.numrefs > 0
                    ? this.alternative === ''
                            ? html`<span class="badge badge-danger">${this.numrefs}</span>`
                            : html`<span class="badge badge-success">${this.numrefs}</span>`
                    : this.alternative === ''
                            ? html`<span class="badge badge-warning">${this.numrefs}</span>`
                            : html`<span class="badge badge-success">${this.numrefs}</span>`
            }</td>
            <td>
                <div class="fw-semibold">${this.title}</div>
                <div>${this.description}</div>
                <div class="text-muted small">${this.name}</div>
            </td>
            <td>
                ${this.isGenerated ? html`<span class="ki-label badge badge-info text-bg-info">${lll('tx_ai3.module.filemetadata.aigenerated')}</span>
                    ${this.isReviewed ? html`<span class="ki-label badge badge-success text-bg-success">${lll('tx_ai3.module.filemetadata.approved')}</span>`: ''}
                <br/>`
                :''}
                ${this.isEditing ? 
                        html`
                            <textarea style="width:100%; height: 8em"
                                      .value=${this.alternative}
                                      @change="${(e) =>
                                              this.alternative = e.target.value}"></textarea>
                            <button class="btn btn-sm btn-primary"
                                    @click="${this.saveFile}">speichern
                            </button>
                            <button class="btn btn-sm btn-secondary"
                                    @click="${this.cancelAltTextedit}">abbrechen
                            </button>
                        `:
                        html`<span @click="${this.handleAltTextedit}">
                                
                            ${this.alternative 
                                    ? this.alternative
                                    : html `<span class="badge">${lll('tx_ai3.module.filemetadata.addmanually')}</span>`
                            }
                            ${Typo3Icon('actions-open')}
                        </span>`
                }
               
                ${(!this.isGenerated && this.hasGeneration && !this.isEditing) ?
                html`
                    <div class="border-1 border-info border p-2">
                    <div class="row">
                        <div class="col">
                            <span class="ki-label badge badge-info text-bg-info">${lll('tx_ai3.module.filemetadata.badge.aisuggestion')}</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            ${this.isEditingSuggestion ? html`
                                        <textarea style="width:100%; height: 8em"
                                                  .value=${this.altSuggestion}
                                                  @change="${(e) =>
                                                          this.altSuggestion = e.target.value}"></textarea>
                                    `
                                    : html`<p class="">${this.altSuggestion}</p>`}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            ${this.isEditingSuggestion ? html`
                                <button class="btn btn-sm btn-primary"
                                        @click="${this.acceptModifiedAltSuggestion}">${lll('tx_ai3.module.filemetadata.action.approve')}
                                </button>
                                <button class="btn btn-sm btn-secondary"
                                        @click="${this.cancelSuggestionEdit}">abbrechen
                                </button>
                            `:html`

                            <button class="btn btn-sm btn-primary"
                                    @click="${this.acceptAltSuggestion}">${lll('tx_ai3.module.filemetadata.action.approve')}
                            </button>
                            <button class="btn btn-sm btn-secondary"
                                    @click="${this.handleEditSuggestion}">${lll('tx_ai3.module.filemetadata.action.editSuggestion')}
                            </button>`}
                        </div>
                    </div>
                    </div>
                `:''
            }
            </td>
            <td class="text-end">
                <div class="btn-group" role="group">

                    <button class="btn btn-sm btn-secondary"
                            title="${lll('tx_ai3.module.filemetadata.action.edit')}"
                            @click="${this.handleEditFile}">
                        ${Typo3Icon('actions-open')}
                        <span class="visually-hidden">${lll('tx_ai3.module.filemetadata.action.edit')}</span>
                    </button>
                    <button class="btn btn-sm btn-primary ai3-alttext-add-to-batch-btn"
                            @click="${this.handleGenerateTask}"
                            title="${this.inProgress ? lll('tx_ai3.module.filemetadata.status.queued') : lll('tx_ai3.module.filemetadata.action.generate')}" type="button">
                        ${this.inProgress ? Typo3Icon('actions-cpu') : Typo3Icon('actions-synchronize')}
                        <span class="visually-hidden">lll('tx_ai3.module.filemetadata.action.generate')}</span>
                    </button>
                </div>
            </td>
        `;
    }
}
customElements.define('file-element',fileElement);

