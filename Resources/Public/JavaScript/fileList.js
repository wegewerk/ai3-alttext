import {lll} from "@typo3/core/lit-helper.js";
import Notification from "@typo3/backend/notification.js";
import {ModuleStateStorage as ModuleState} from "@typo3/backend/storage/module-state-storage.js";
import {html, render} from 'lit-html';
import {LitElement } from 'lit-element';
import ModalElement from "@typo3/backend/modal.js";
import Ai3Api from './ai3api.js'
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";

class FileListElement extends LitElement {

    static properties ={
        files: {type: Array},
        folder: {type: Object},
        modal: {type: Object},
        selectedFiles: {type: Array},
        previewImage: {type: String},
        previewTitle: {type: String},
        cost: {type: Number}
    }
    constructor() {
        super();
        this.files = [];
        this.folder = {};
        this.modal = {};
        this.selectedFiles = [];
        this.previewImage = null;
        this.previewTitle = null;
        this.cost=0;
    }
    createRenderRoot() {
        return this.parentNode;
    }
    connectedCallback() {
        super.connectedCallback();
        this.parentNode.addEventListener('fileSelect', this.handleFileSelect.bind(this));
        this.parentNode.addEventListener('viewImage', this.handleViewImage.bind(this));
        setInterval(this.reloadFilelist.bind(this),2500);
    }

    handleFileSelect(e) {
        let self = this;
        this.cost=0;
        this.files.map((file) => {
            if(file.uid == e.detail.uid) {
                file.isSelected=e.detail.selected
                self.selectedFiles[file.uid]=e.detail.selected;
            }
            if(file.isSelected) self.cost = parseInt(self.cost) + 3;
        } );
        this.requestUpdate();
    }
    handleViewImage(e) {
        this.previewImage = e.detail.file.publicUrl;
        this.previewTitle = e.detail.file.name;
        this.modal = ModalElement.advanced({
            content: html`<img style="width: 100%; height: 100%; object-fit: contain;" src="${this.previewImage}" alt="" >`,
            title: this.previewTitle,
            staticBackdrop: false,
            additionalCssClasses: ["modal-size-expand"],

        });
        this.requestUpdate();
    }

    handleCheckAll(e) {
        let self = this;
        this.files.map((file) => {
            file.isSelected=e.target.checked;
            self.handleFileSelect({detail: {
                    selected:file.isSelected,
                    uid:file.uid
            }});
        } );
        this.requestUpdate();
    }
    handleCheckAllUsedWithoutAlt(e) {
        let self = this;
        this.files.map((file) => {
            if(file.numrefs>0 && file.alternative==='') {
                file.isSelected=true
                self.handleFileSelect({detail: {
                        selected:file.isSelected,
                        uid:file.uid
                }});
            }
        } );
        this.requestUpdate();
    }
    handleCheckAllWithoutAlt(e) {
        let self = this;
        this.files.map((file) => {
            if(file.alternative==='') {
                file.isSelected=true
                self.handleFileSelect({detail: {
                        selected:file.isSelected,
                        uid:file.uid
                }});
            }
        } );
        this.requestUpdate();
    }
    reloadFilelist() {
        let self = this;
        if(this.folder) {
            return Ai3Api.prototype.loadFolder(this.folder.identifier)
                .then(async function (response) {
                    const resolved = await response.resolve();
                    const responseBody = JSON.parse(resolved);
                    self.files = responseBody.files;
                    self.folder = responseBody.folder;
                    self.files.map((file) => {
                        if(self.selectedFiles[file.uid]) {
                            file.isSelected=self.selectedFiles[file.uid];
                        }
                    })
                })
        } else {
            return new Promise((resolve, reject) => { resolve();});
        }
    }
    handleGenerateAll(e) {
        let self = this;
        this.files.map((file) => {
            if(file.isSelected) {
                    Ai3Api.prototype.createAlttextTask(file).then(() => {
                    file.isSelected=false;
                    self.selectedFiles[file.uid]=false;
                    self.requestUpdate();
                });
            }
        });
    }
    handleAcceptAll(e) {
        let self = this;
        this.files.map((file) => {
            if(file.isSelected || file.hasGeneration) {
                Ai3Api.prototype.acceptAltSuggestion(file).then(() => {
                    file.isSelected=false;
                    self.selectedFiles[file.uid]=false;
                    self.requestUpdate();
                });
            }
        });
    }

    hasSelectedFiles(){
        return this.numSelectedFiles() > 0;
    }
    hasFilesWithGeneration() {
        return this.files.some(file => file.hasGeneration);
    }
    allFilesSelected(){
        return this.numSelectedFiles() === this.files.length;
    }
    numSelectedFiles(){
        return this.files.filter((file) => file.isSelected ).length;
    }
    render() {
        let self = this;
        return html`
            <div class="mb-3 text-muted" data-content="folderMeta">
                <strong>${lll('tx_ai3.module.filemetadata.folder')}:</strong> ${this.folder.name} <span
                    class="badge">${this.folder.identifier}</span>
                <button class="btn btn-sm btn-default" @click="${this.reloadFilelist.bind(this)}">${Typo3Icon('actions-refresh')}</button>
                ${this.files.length
                ? html`
                <div class="row">
                    <div class="col">
                        <div class="callout ${this.folder.numWithoutAlttext > 0 ? 'callout-warning' : 'callout-success'}">
                            <div class="callout-icon">
                                <span class="icon-emphasized">
                                    <span class="t3js-icon icon icon-size-small icon-state-default icon-actions-info">
                                        ${this.folder.numWithoutAlttext > 0 ? Typo3Icon('actions-exclamation'):Typo3Icon('actions-check')}
                                    </span>
                            </div>
                            <div class="callout-content">
                                <div class="callout-body">
                                    ${this.folder.numWithoutAlttext > 0
                                            ? html`${lll('tx_ai3.module.filemetadata.imageswithoutalt')}: ${this.folder.numWithoutAlttext}`
                                            :html`${lll('tx_ai3.module.filemetadata.allimageshavealt')}`}

                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-default"
                                @click="${this.handleCheckAllWithoutAlt.bind(self)}"
                                title="${lll('tx_ai3.module.filemetadata.selectallwithoutalt')}">
                            ${Typo3Icon('actions-selection-elements-all')} ${lll('tx_ai3.module.filemetadata.selectallwithoutalt')}
                        </button>
                    </div>
                    <div class="col">
                         <div class="callout ${this.folder.numUsedWithoutAlttext > 0 ? 'callout-danger' : 'callout-success'}">
                            <div class="callout-icon">
                                <span class="icon-emphasized">
                                    <span class="t3js-icon icon icon-size-small icon-state-default icon-actions-info">
                                        ${this.folder.numUsedWithoutAlttext > 0 ? Typo3Icon('actions-exclamation'):Typo3Icon('actions-check')}
                                    </span>
                            </div>
                            <div class="callout-content">
                                <div class="callout-body">
                                    ${this.folder.numUsedWithoutAlttext > 0 
                                            ? html`${lll('tx_ai3.module.filemetadata.imagesusedwithoutalt')}: ${this.folder.numUsedWithoutAlttext}`
                                            : html`${lll('tx_ai3.module.filemetadata.allusedimageshavealt')}`}
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-default"
                                @click="${this.handleCheckAllUsedWithoutAlt.bind(self)}"
                                title="${lll('tx_ai3.module.filemetadata.selectallusedwithoutalt')}">
                            ${Typo3Icon('actions-selection-elements-all')} ${lll('tx_ai3.module.filemetadata.selectallusedwithoutalt')}
                        </button>
                        
                    </div>
                </div>
                `:''}
            </div>
            ${!this.files.length
            ? html`
                <div class="alert alert-info">
                    ${lll('tx_ai3.module.filemetadata.emptyfolder')}
                </div>`
            : html`
            <div class="filelist-main"">
                <div class="multi-record-selection-actions-wrapper">
                ${this.hasSelectedFiles() || this.hasFilesWithGeneration()
                ? html`
                        <div class="row row-cols-auto gx-2 align-items-center">
                            <div class="col">
                                ${this.hasSelectedFiles() 
                                        ? html`<button class="btn btn-sm btn-default" @click="${this.handleGenerateAll.bind(self)}">${Typo3Icon('actions-cpu')} ${lll('tx_ai3.module.filemetadata.action.generateall',this.cost).replace('%s',this.cost)}</button>`:''}
                                ${this.hasFilesWithGeneration()
                                        ? html`<button class="btn btn-sm btn-default" @click="${this.handleAcceptAll.bind(self)}">${Typo3Icon('actions-approve') }${lll('tx_ai3.module.filemetadata.action.acceptall')}</button>`:''}
                            </div>
                        </div>`
                : ``}
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle ai3-files-table">
                        <colgroup>
                            <col style="width: 2%;">
                            <col style="width: 15%;">
                            <col style="width: 8%;">
                            <col style="width: 20%;">
                            <col style="width: 45%;">
                            <col style="width: 8%;">
                        </colgroup>
                        <thead>
                        <tr>
                            <th scope="col" style="width: 36px;">
                                <div class="form-check m-0">
                                    <input aria-label="Alle auswählen" class="form-check-input" id="select-all"
                                           @change="${this.handleCheckAll.bind(self)}"
                                           .checked="${this.allFilesSelected()}"
                                           type="checkbox">
                                </div>
                            </th>
                            <th></th>
                            <th>${lll('tx_ai3.module.filemetadata.column.usage')}</th>
                            <th>${lll('tx_ai3.module.filemetadata.column.title')}</th>
                            <th>${lll('tx_ai3.module.filemetadata.column.alt')}</th>
                            <th class="text-end">${lll('tx_ai3.module.filemetadata.column.actions')}</th>
                        </tr>
                        </thead>
                        <tbody>
                        ${this.files.map(function(file) {
                            return html`
                                <file-element
                                        ?inProgress=${file.inProgress}
                                        ?isGenerated=${file.isGenerated}
                                        ?isImage=${file.isImage}
                                        ?isReviewed=${file.isReviewed}
                                        ?hasGeneration=${file.hasGeneration}
                                        ?isSelected=${file.isSelected}
                                        uid=${file.uid}
                                        thumbnailUrl=${file.thumbnailUrl}
                                        publicUrl=${file.publicUrl}
                                        alternative=${file.alternative}
                                        title=${file.title}
                                        name=${file.name}
                                        description=${file.description}
                                        altSuggestion=${file.altSuggestion}
                                        editlink=${file.editlink}
                                        numrefs=${file.numrefs}
                                ></file-element>
                            `;
                        })}
        
                        </tbody>
                    </table>
                </div>
            </div>`}
            
        `;
    }
}
customElements.define('filelist-element',FileListElement);

class FileList {
    DOM_Content;

    constructor() {
        this.tpl_content = function (data) {
            return html`
                <filelist-element .files=${data.files} .folder=${data.folder}></filelist-element>
            `;
        }
        this.DOM_Content = document.querySelector('[data-content="files"]');
        let self = this;

        let moduleState = ModuleState.current("media");
        if (moduleState.identifier) {
            self.loadData(moduleState.identifier);
        } else {
            self.loadData('1:/')
        }

    }
    renderFilelist(data) {
        this.DOM_Content.innerHTML='';
        render(this.tpl_content(data),this.DOM_Content)
    }

    loadData(folderIdentifier) {
        let self = this;
        Ai3Api.prototype.loadFolder(folderIdentifier)
        .then(async function (response) {
                ModuleState.update("media", folderIdentifier);
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                self.renderFilelist(responseBody);
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

}

export default new FileList();
