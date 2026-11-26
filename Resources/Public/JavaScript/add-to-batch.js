import Ajax from "@wegewerk/ai3core/ajax.js";
import ResponseHandling from "@wegewerk/ai3core/response-handling.js";
import Typo3Icon from "@wegewerk/ai3core/Typo3Icon.js";
import {lll} from "@typo3/core/lit-helper.js";
import {html, nothing, render} from 'lit-html';

class AddToBatch {
  constructor() {
    this.intervalId = null;

    this.button = document.querySelector('#alttext_add_to_batch');
    this.id = this.button.getAttribute('data-id');
    this.textarea = document.querySelector('[name="data[sys_file_metadata][' + this.id + '][alternative]"]');

    this.wrapper =
      this.textarea.closest('.form-wizards-item-element') || // TYPO3 v13 compatibility
      this.textarea.closest('.form-wizards-element');        // TYPO3 v12 compatibility

    if (this.wrapper) {
      this.wrapper.classList.add('position-relative');
    }

    this.textarea.style.height = (this.textarea.offsetHeight + 50) + 'px';

    this.inProgress = this.button.getAttribute('data-in-progress') === '1';
    this.isGenerated = this.button.getAttribute('data-is-generated') === '1';
    this.isReviewed = this.button.getAttribute('data-is-reviewed') === '1';
    this.hasGeneration = this.button.getAttribute('data-has-generation') === '1';
    this.altSuggestion = this.button.getAttribute('data-alt-suggestion') || '';

    this.isEditingSuggestion = false;
    this.editedSuggestion = this.altSuggestion;

    this.suggestionContainer = document.createElement('div');
    this.suggestionContainer.id = 'alttext_suggestion';
    this.textarea.insertAdjacentElement('afterend', this.suggestionContainer);

    this.initializeAddToBatchButton();
    this.updateFieldStyles();
    this.renderSuggestion();

    if (this.inProgress) {
      this.checkAlttextGenerationStatus();
    }
  }

  initializeAddToBatchButton() {
    if (this.inProgress) {
      this.showSpinner();
    }

    this.button.addEventListener("click", (ev) => {
      ev.preventDefault();

      if (this.inProgress) {
        console.log('AI-Generierung ist bereits in Arbeit.');
        return;
      }

      const postData = {
        record_uid: this.id,
        record_table: this.button.getAttribute('data-table'),
        record_field: this.button.getAttribute('data-field-name'),
        fieldLabel: this.button.getAttribute('data-field-label'),
      };

      this.inProgress = true;
      this.showSpinner();
      this.updateFieldStyles();
      this.renderSuggestion();

      this.generateAlttext(postData)
        .then((res) => {
          this.checkAlttextGenerationStatus();

          ResponseHandling.handleSuccess(res, TYPO3.lang['tx_ai3.module.modal.alttextError']);
        })
        .catch(error => {
          clearInterval(this.intervalId);
          this.inProgress = false;
          this.hideSpinner();
          this.updateFieldStyles();
          this.renderSuggestion();

          ResponseHandling.handleError(error, TYPO3.lang['tx_ai3.module.modal.alttextError']);
        });
    });
  }

  showSpinner() {
    const icon = this.button.querySelector('svg.icon-color');
    const markup = this.button.querySelector('span.icon-markup');

    if (icon) {
      icon.style.display = 'none';
    }
    if (markup) {
      markup.classList.add('spinner-border', 'spinner-border-sm');
    }
  }

  hideSpinner() {
    const icon = this.button.querySelector('svg.icon-color');
    const markup = this.button.querySelector('span.icon-markup');

    if (icon) {
      icon.style.display = '';
    }
    if (markup) {
      markup.classList.remove('spinner-border', 'spinner-border-sm');
    }
  }

  generateAlttext(data) {
    return new Promise(async (resolve, reject) => {
      let res = await Ajax.sendAjaxRequest('ai3_alttext_generation_create_task', data);
      resolve(res);
    });
  }

  reviewAlttext(data) {
    return new Promise(async (resolve, reject) => {
      let res = await Ajax.sendAjaxRequest('ai3_alttext_generation_review', data);
      resolve(res);
    });
  }

  checkAlttextGenerationStatus() {
    const poll = async () => {
      const status = await Ajax.sendAjaxRequest('ai3_alttext_generation_status', {record_uid: this.id});
      if (!status) {
        return;
      }

      if (!status.running && !status.done) {
        clearInterval(this.intervalId);
        this.inProgress = false;
        this.hideSpinner();
        this.updateFieldStyles();
        this.renderSuggestion();
        return;
      }

      if (status.done) {
        clearInterval(this.intervalId);

        this.inProgress = false;
        this.hideSpinner();

        this.altSuggestion = typeof status.result === 'string' ? status.result : '';
        this.hasGeneration = this.altSuggestion !== '';
        this.isGenerated = this.altSuggestion !== '' && this.textarea.value === this.altSuggestion;
        this.isReviewed = Boolean(status.reviewed);
        this.isEditingSuggestion = false;
        this.editedSuggestion = this.altSuggestion;

        this.updateFieldStyles();
        this.renderSuggestion();
      }
    };

    this.intervalId = setInterval(poll, 5000);
    poll();
  }

  /**
   * Übernimmt den Vorschlag in das Alttext-Feld und markiert den Task als geprüft.
   */
  handleApproveSuggestion() {
    if (this.isEditingSuggestion) {
      this.altSuggestion = this.editedSuggestion;
    }

    this.applyAltSuggestion();
    this.approveAlttext();
  }

  /**
   * Markiert den bereits im Feld stehenden Alttext als geprüft.
   */
  handleReview() {
    this.approveAlttext();
  }

  approveAlttext() {
    this.reviewAlttext({
      record_uid: this.id,
      alttext: this.textarea.value,
    })
      .then((res) => {
        clearInterval(this.intervalId);

        this.inProgress = false;
        this.isGenerated = true;
        this.isReviewed = true;
        this.hasGeneration = false;
        this.isEditingSuggestion = false;

        this.updateFieldStyles();
        this.renderSuggestion();

        ResponseHandling.handleSuccess(res, TYPO3.lang['tx_ai3.module.modal.alttextError']);
      })
      .catch(error => {
        ResponseHandling.handleError(error, TYPO3.lang['tx_ai3.module.modal.alttextError']);
      });
  }

  handleEditSuggestion() {
    this.isEditingSuggestion = true;
    this.editedSuggestion = this.altSuggestion;
    this.renderSuggestion();
  }

  handleCancelSuggestionEdit() {
    this.isEditingSuggestion = false;
    this.editedSuggestion = this.altSuggestion;
    this.renderSuggestion();
  }

  applyAltSuggestion() {
    if (this.textarea.value === this.altSuggestion) {
      return;
    }

    this.textarea.value = this.altSuggestion;
    this.textarea.dispatchEvent(new Event('input', {bubbles: true}));
  }

  updateFieldStyles() {
    this.textarea.classList.remove(
      'border-success', 'bg-success-subtle',
      'border-warning', 'bg-warning-subtle',
      'border-info', 'bg-info-subtle',
      'border'
    );

    // Only use border colors: the Bootstrap "*-subtle" backgrounds are not
    // dark-mode aware in the TYPO3 backend and make the text unreadable.
    if (this.inProgress) {
      this.textarea.classList.add('border', 'border-info');
    } else if (this.isGenerated && this.isReviewed) {
      this.textarea.classList.add('border', 'border-success');
    } else if (this.isGenerated) {
      this.textarea.classList.add('border', 'border-warning');
    }
  }

  renderSuggestion() {
    render(this.buildSuggestionTemplate(), this.suggestionContainer);
  }

  buildSuggestionTemplate() {
    if (this.inProgress) {
      return html`
        <span class="ki-label badge badge-info text-bg-info">
          ${lll('tx_ai3.module.filemetadata.status.generating')}
        </span>
      `;
    }

    if (this.isGenerated) {
      return html`
        <div class="mt-2">
          <span class="ki-label badge badge-info text-bg-info">${lll('tx_ai3.module.filemetadata.aigenerated')}</span>
          ${this.isReviewed
            ? html`<span class="ki-label badge badge-success text-bg-success ms-1">${lll('tx_ai3.module.filemetadata.approved')}</span>`
            : html`
                <button id="alttext_review"
                        class="btn btn-success rounded-pill btn-sm px-1 py-1 pe-2 ms-1"
                        type="button"
                        @click=${() => this.handleReview()}>
                  ${Typo3Icon('actions-check')}
                  ${lll('tx_ai3.module.filemetadata.action.approve')}
                </button>
              `}
        </div>
      `;
    }

    if (this.hasGeneration) {
      return html`
        <div class="border border-info border-1 rounded p-2 mt-2">
          <div class="row">
            <div class="col">
              <span class="ki-label badge badge-info text-bg-info">${lll('tx_ai3.module.filemetadata.badge.aisuggestion')}</span>
            </div>
          </div>
          <div class="row">
            <div class="col">
              ${this.isEditingSuggestion
                ? html`
                    <textarea class="form-control mt-2"
                              rows="3"
                              .value=${this.editedSuggestion}
                              @input=${(e) => { this.editedSuggestion = e.target.value; }}></textarea>
                  `
                : html`<p class="mb-0 mt-2">${this.altSuggestion}</p>`}
            </div>
          </div>
          <div class="row">
            <div class="col mt-2">
              ${this.isEditingSuggestion
                ? html`
                    <button class="btn btn-sm btn-primary"
                            type="button"
                            @click=${() => this.handleApproveSuggestion()}>
                      ${lll('tx_ai3.module.filemetadata.action.approve')}
                    </button>
                    <button class="btn btn-sm btn-secondary"
                            type="button"
                            @click=${() => this.handleCancelSuggestionEdit()}>
                      ${lll('tx_ai3.module.folderactions.cancel')}
                    </button>
                  `
                : html`
                    <button class="btn btn-sm btn-primary"
                            type="button"
                            @click=${() => this.handleApproveSuggestion()}>
                      ${lll('tx_ai3.module.filemetadata.action.approve')}
                    </button>
                    <button class="btn btn-sm btn-secondary"
                            type="button"
                            @click=${() => this.handleEditSuggestion()}>
                      ${lll('tx_ai3.module.filemetadata.action.editSuggestion')}
                    </button>
                  `}
            </div>
          </div>
        </div>
      `;
    }

    return nothing;
  }
}

export default new AddToBatch();
