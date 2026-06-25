import {lll} from "@typo3/core/lit-helper.js";
import {ModuleStateStorage as ModuleState} from "@typo3/backend/storage/module-state-storage.js";
import {html, render} from 'lit-html';
import {LitElement } from 'lit-element';
import Ai3Api from './ai3api.js'
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";

class SubfoldersElement extends LitElement {

    static properties ={
        children: {type: Array},
        folder: {type: Object},
        waitforResult: {type: Boolean},
    }
    constructor() {
        super();
        this.children = [];
        this.folder = null;
        this.waitforResult = false;
    }
    createRenderRoot() {
        return this.parentNode;
    }
    connectedCallback() {
        super.connectedCallback();
        this.reloadFolders();
    }

    handleAcceptAll(e) {
        let self = this;
        this.waitforResult = true;
        Ai3Api.prototype.acceptAltSuggestionRecursive(this.folder.identifier).then(() => {
            this.waitforResult = false;
            self.reloadFolders();
        });
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
                    self.folder = responseBody.folder;
                    self.waitforResult = false;
                })
        } else {
            return new Promise((resolve, reject) => { resolve();});
        }
    }

    render() {
        let self = this;
        return this.folder.name == null ? html`${lll('tx_ai3.module.folderactions.loading')} <typo3-backend-spinner size="small"></typo3-backend-spinner>`: html`
            <div class="mb-3 text-muted" data-content="folderMeta">
                <strong>${lll('tx_ai3.module.filemetadata.folder')}:</strong> ${this.folder.name} <span
                    class="badge">${this.folder.identifier}</span>
                <button class="btn btn-sm btn-default" @click="${this.reloadFolders.bind(this)}">${Typo3Icon('actions-refresh')}</button>
                <div class="row">
                    <div class="col">
                        <p>
                        ${this.children.length ? html`
                            ${lll('tx_ai3.module.folderactions.subfolders')}: ${this.folder.numSubfolders}
                                `:lll('tx_ai3.module.folderactions.nosubfolders')
                        }
                        </p>
                        ${this.folder.countGenerations > 0 ? html`
                            <button class="btn btn-sm btn-default" @click="${this.handleAcceptAll.bind(this)}">
                                ${Typo3Icon('actions-approve')}
                                ${lll('tx_ai3.module.folderactions.acceptAllGenerationsRecursive').replace('%cntGens', this.folder.countGenerations).replace('%cntFolder', this.folder.numSubfolders)}
                            </button>
                        `:lll('tx_ai3.module.folderactions.noGenerations').replace('%cntFolder', this.folder.numSubfolders)}
                        ${this.waitforResult ? html`
                            <typo3-backend-spinner size="small"></typo3-backend-spinner>
                        `:''}
                    </div>
                </div>
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
