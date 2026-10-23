import {lll} from "@typo3/core/lit-helper.js";
import Notification from "@typo3/backend/notification.js";
import {ModuleStateStorage as ModuleState} from "@typo3/backend/storage/module-state-storage.js";
import {html, render} from 'lit-html';
import {LitElement } from 'lit-element';
import Ai3Api from './ai3api.js'
import "@wegewerk/ai3core/creditsElement.js";
import "./queueImageElement.js";
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";

class SubfoldersElement extends LitElement {

    static properties ={
        children: {type: Array},
        folder: {type: Object},
        waitforResult: {type: Boolean},
        activeAction: {type: String},
        pendingAction: {type: Object},
    }
    constructor() {
        super();
        this.children = [];
        this.folder = null;
        this.waitforResult = false;
        this.activeAction = null;
        this.pendingAction = null;
    }
    createRenderRoot() {
        return this.parentNode;
    }
    connectedCallback() {
        super.connectedCallback();
        this.reloadFolders();
    }

    isAffected(child) {
        if (!this.activeAction || !child) {
            return false;
        }
        if (this.activeAction === 'all') {
            return Boolean(child.hasImages || child.imageCount > 0);
        }
        if (this.activeAction === 'withoutAlttext') {
            return Boolean(child.hasImagesWithoutAlttext || child.imagesWithoutAlttextCount > 0);
        }
        if (this.activeAction === 'accept') {
            return Boolean(child.countGenerations > 0);
        }
        return false;
    }

    handleAcceptAll(e) {
        const count = this.folder?.countGenerations || 0;

        if (count === 0) {
            Notification.info(
                lll('tx_ai3.module.folderactions.header'),
                lll('tx_ai3.module.folderactions.notif.noSuggestions')
            );
            return;
        }

        const message = (lll('tx_ai3.module.folderactions.confirm.acceptAll') || lll('tx_ai3.module.folderactions.notif.acceptingAll')).replace('%cnt', count);

        this.activeAction = 'accept';
        this.pendingAction = {
            action: 'accept',
            count: count,
            message: message
        };
    }
    handleGenerateAll(filter, e) {
        const count = filter === 'withoutAlttext'
            ? (this.folder?.imagesWithoutAlttextCount || 0)
            : (this.folder?.imageCount || 0);

        if (count === 0) {
            Notification.info(
                lll('tx_ai3.module.folderactions.header'),
                lll('tx_ai3.module.folderactions.notif.noFiles')
            );
            return;
        }

        const messageKey = filter === 'withoutAlttext'
            ? 'tx_ai3.module.folderactions.confirm.generatingWithoutAlttext'
            : 'tx_ai3.module.folderactions.confirm.generatingAll';
        const fallbackMessageKey = filter === 'withoutAlttext'
            ? 'tx_ai3.module.folderactions.notif.generatingWithoutAlttext'
            : 'tx_ai3.module.folderactions.notif.generatingAll';
        const message = (lll(messageKey) || lll(fallbackMessageKey)).replace('%cnt', count);

        this.activeAction = filter;
        this.pendingAction = {
            action: filter,
            count: count,
            message: message
        };
    }
    handleCancelAction() {
        this.pendingAction = null;
        this.activeAction = null;
    }
    handleConfirmAction() {
        if (!this.pendingAction) {
            return;
        }
        const action = this.pendingAction.action;
        let self = this;
        this.pendingAction = null;
        this.waitforResult = true;

        if (action === 'accept') {
            Ai3Api.prototype.acceptAltSuggestionRecursive(self.folder.identifier).then(() => {
                self.waitforResult = false;
                self.activeAction = null;
                self.reloadFolders();
            }).catch(() => {
                self.waitforResult = false;
                self.activeAction = null;
            });
        } else {
            Ai3Api.prototype.createAlttextTasksRecursive(self.folder.identifier, action).then(() => {
                self.waitforResult = false;
                self.activeAction = null;
                self.reloadFolders();
            }).catch(() => {
                self.waitforResult = false;
                self.activeAction = null;
            });
        }
    }
    reloadFolders() {
        let self = this;
        if(this.folder) {
            self.waitforResult = true;
            return Ai3Api.prototype.loadSubfolders(this.folder.identifier)
                .then(async function (response) {
                    const resolved = await response.resolve();
                    const responseBody = JSON.parse(resolved);
                    self.children = responseBody.children;
                    console.log(self.children);
                    self.folder = responseBody.folder;
                    self.waitforResult = false;
                    self.activeAction = null;
                    self.pendingAction = null;
                    console.log(self.folder);
                }).catch(() => {
                    self.waitforResult = false;
                    self.activeAction = null;
                    self.pendingAction = null;
                });
        } else {
            return new Promise((resolve, reject) => { resolve();});
        }
    }

    handleProcessQueue() {
        let self = this;
        this.waitforResult = true;
        this.activeAction = 'processQueue';
        Ai3Api.prototype.processQueue()
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                self.waitforResult = false;
                self.activeAction = null;
                Notification.success(
                    lll('tx_ai3.module.folderactions.header'),
                    lll('tx_ai3.module.folderactions.notif.queueProcessed').replace('%cnt', responseBody.processed ?? 0)
                );
                self.reloadFolders();
            })
            .catch(async function (response) {
                self.waitforResult = false;
                self.activeAction = null;
                let message = lll('tx_ai3.module.folderactions.notif.queueProcessError');
                try {
                    const resolved = await response.resolve();
                    const responseBody = JSON.parse(resolved);
                    if (responseBody.message) {
                        message = responseBody.message;
                    }
                } catch (e) {
                    message = message || lll('tx_ai3.module.folderactions.notif.queueProcessError');
                }
                Notification.error(lll('tx_ai3.module.folderactions.header'), message);
            });
    }

    renderConfirmAlert() {
        if (!this.pendingAction) {
            return '';
        }
        return html`
            <div class="alert alert-info mb-0" role="alert">
                <h5 class="alert-heading mb-2">${lll('tx_ai3.module.folderactions.confirmTitle') || 'Confirm Action'}</h5>
                <p class="mb-3">${this.pendingAction.message}</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" @click="${this.handleConfirmAction.bind(this)}">
                        ${lll('tx_ai3.module.folderactions.confirm') || 'OK'}
                    </button>
                    <button class="btn btn-sm btn-default" @click="${this.handleCancelAction.bind(this)}">
                        ${lll('tx_ai3.module.folderactions.cancel') || 'Cancel'}
                    </button>
                </div>
            </div>
        `;
    }

    render() {
        let self = this;
        const isRootAffected = this.isAffected(this.folder);
        const inProgressFiles = (this.folder.queueFiles || []).filter((file) => file.inProgress);
        const hasQueue = this.folder.countPending > 0 || inProgressFiles.length > 0;
        const nothingToDo = !hasQueue && !(this.folder.imagesWithoutAlttextCount > 0);
        return this.folder.name == null ? html`${lll('tx_ai3.module.folderactions.loading')} <typo3-backend-spinner size="small"></typo3-backend-spinner>`: html`
            <div class="mb-3 text-muted" data-content="folderMeta">
                <div class="row">
                    <div class="col col-auto">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>${lll('tx_ai3_alttext.module.actionmenu.folders')}</th>
                                        ${this.folder.countGenerations > 0 ? html`
                                            <th class="text-end">${lll('tx_ai3.module.folderactions.awaitungApprovalShort')}</th>
                                        ` : ''}
                                        <th class="text-end">${lll('tx_ai3.module.folderactions.generateAllShort')}</th>
                                        <th class="text-end">${lll('tx_ai3.module.folderactions.generateWithoutAlttextShort')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="transition: background-color 0.2s ease;">
                                        <td class="${isRootAffected ? 'bg-warning fw-bold' : ''}">
                                            ${Typo3Icon('actions-folder')} <span class="badge ms-1">${this.folder.identifier}</span>
                                            <button class="btn btn-sm btn-link p-0 ms-1 text-decoration-none" title="Refresh" @click="${this.reloadFolders.bind(this)}">${Typo3Icon('actions-refresh')}</button>
                                        </td>
                                        ${this.folder.countGenerations > 0 ? html`
                                            <td class="text-end ${isRootAffected ? 'bg-warning fw-bold' : ''}">
                                                <span class="badge badge-info text-bg-info ms-1"
                                                      title="${this.folder.countGenerations} AI generations">${this.folder.countGenerations}</span>
                                            </td>
                                        ` : ''}
                                        <td class="text-end ${isRootAffected ? 'bg-warning fw-bold' : ''}">
                                            <span class="badge ${this.folder.imageCount > 0 ? 'badge-secondary text-bg-secondary' : 'badge-light text-bg-light text-muted'}">${this.folder.imageCount}</span>
                                        </td>
                                        <td class="text-end ${isRootAffected ? 'bg-warning fw-bold' : ''}">
                                            ${this.folder.imagesWithoutAlttextCount > 0 ? html`
                                                <span class="badge badge-warning text-bg-warning">${this.folder.imagesWithoutAlttextCount}</span>
                                            ` : html`
                                                <span class="badge badge-light text-bg-light text-muted">0</span>
                                            `}
                                        </td>
                                    </tr>
                                    ${this.children.map((child) => {
                                        const affected = this.isAffected(child);
                                        return html`
                                            <tr style="transition: background-color 0.2s ease;">
                                                <td class="ps-3 ${affected ? 'bg-warning fw-bold' : ''}">
                                                    ${Typo3Icon('actions-folder')} <span class="ms-1">${child.name}</span>
                                                </td>
                                                ${this.folder.countGenerations > 0 ? html` <td class="text-end ${affected ? 'bg-warning fw-bold' : ''}">
                                                    ${child.countGenerations > 0 ? html`
                                                        <span class="badge badge-info text-bg-info ms-1" title="${child.countGenerations} AI generations">${child.countGenerations}</span>
                                                    ` : ''}
                                                </td>
                                                ` : ''}
                                                <td class="text-end ${affected ? 'bg-warning fw-bold' : ''}">
                                                    <span class="badge ${child.imageCount > 0 ? 'badge-secondary text-bg-secondary' : 'badge-light text-bg-secondary'}">${child.imageCount}</span>
                                                </td>
                                                <td class="text-end ${affected ? 'bg-warning fw-bold' : ''}">
                                                    ${child.imagesWithoutAlttextCount > 0 ? html`
                                                        <span class="badge badge-warning text-bg-warning">${child.imagesWithoutAlttextCount}</span>
                                                    ` : html`
                                                        <span class="badge badge-light text-bg-secondary">0</span>
                                                    `}
                                                </td>
                                            </tr>
                                        `;
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col">
                        ${this.pendingAction && this.pendingAction.action !== 'accept' ? this.renderConfirmAlert() : html`
                            <fieldset class="mb-3">
                                <legend class="form-legend">${lll('tx_ai3.module.folderactions.generateTitle')}</legend>
                                <div class="mb-2">
                                    <button class="btn btn-sm btn-default" @click=${this.handleGenerateAll.bind(this, 'withoutAlttext')}>
                                        ${Typo3Icon('actions-plus')}
                                        ${lll('tx_ai3.module.folderactions.generateAll')}
                                    </button>
                                </div>
                            </fieldset>
                        `}
                    </div>
                </div>
                ${((this.folder.queueFiles || []).filter((file) => file.hasGeneration && !file.inProgress).length > 0 || this.pendingAction?.action === 'accept') ? html`
                    <div class="row">
                        <div class="col mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">${lll('tx_ai3.module.folderactions.awaitingApprovalTitle').replace('%cnt', this.folder.countGenerations)}</h3>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap gap-3">
                                        ${(this.folder.queueFiles || []).filter((file) => file.hasGeneration && !file.inProgress).map((file) => html`
                                            <queue-image-element
                                                .file="${file}"
                                                badge-text="${lll('tx_ai3.module.folderactions.queueAwaitingApproval')}"
                                                badge-class="text-bg-info"
                                                show-suggestion></queue-image-element>
                                        `)}
                                    </div>
                                </div>
                                ${this.pendingAction?.action === 'accept' ? html`
                                    <div class="card-footer">${this.renderConfirmAlert()}</div>
                                ` : (this.folder.countGenerations > 0 ? html`
                                    <div class="card-footer">
                                        <button class="btn btn-sm btn-default" @click="${this.handleAcceptAll.bind(this)}">
                                            ${Typo3Icon('actions-approve')}
                                            ${lll('tx_ai3.module.folderactions.generationsPending').replace('%cnt', this.folder.countGenerations)}
                                        </button>
                                    </div>
                                `: '')}
                            </div>
                        </div>
                    </div>
                ` : ''}
                ${hasQueue ? html`
                <div class="row">
                    <div class="col mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">${lll('tx_ai3.module.folderactions.queue')}</h3>
                            </div>
                            <div class="card-body">
                                ${this.folder.countPending > 0 ? html`
                                    <span>${lll('tx_ai3.module.folderactions.tasksPending').replace('%cnt', this.folder.countPending)}</span>
                                `:''}
                                ${inProgressFiles.length > 0 ? html`
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        ${inProgressFiles.map((file) => html`
                                            <queue-image-element
                                                .file="${file}"
                                                badge-text="${ lll('tx_ai3.module.folderactions.' + (file.isPending ? 'queuePending': 'queueProcessing')) }"
                                                badge-class="text-bg-secondary"></queue-image-element>
                                        `)}
                                    </div>
                                ` : ''}
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-default" ?disabled="${this.waitforResult}" @click="${this.handleProcessQueue.bind(this)}">
                                            ${Typo3Icon('actions-open')}
                                            ${lll('tx_ai3.module.folderactions.editQueue') || 'Edit queue now'}
                                        </button>
                                    </div>
                                    <div class="col-auto">
                                        ${this.waitforResult ? html`
                                            <div class="mt-2">
                                                <typo3-backend-spinner size="small"></typo3-backend-spinner>
                                            </div>
                                        `:''}
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
                ${nothingToDo ? html`
                <div class="row">
                    <div class="col mb-3">
                        <div class="card">
                            <div class="card-body text-muted">
                                ${lll('tx_ai3.module.folderactions.nothingToDo')}
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>

        `;
    }
}
customElements.define('subfolders-element',SubfoldersElement);

class Subfolders {
    DOM_Content;

    constructor() {
        this.tpl_content = function (data) {
            return html`
                <subfolders-element .children=${data.children} .folder=${data.folder}></subfolders-element>
            `;
        }
        this.DOM_Content = document.querySelector('[data-content="folders"]');
        let self = this;

        let moduleState = ModuleState.current("media");
        if (moduleState.identifier) {
            self.createSubfolderElement(moduleState.identifier);
        } else {
            self.createSubfolderElement('1:/')
        }

    }

    createSubfolderElement(folderIdentifier) {
        ModuleState.update("media", folderIdentifier);
        this.DOM_Content.innerHTML='';
        render(this.tpl_content({
            children:[],
            folder: {
                identifier: folderIdentifier,
                name: null
            }
        }),this.DOM_Content)
    }

}

export default new Subfolders();
