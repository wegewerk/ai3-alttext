import Ajax from "@wegewerk/ai3core/ajax.js";
import ResponseHandling from "@wegewerk/ai3core/response-handling.js";

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

    this.inProgress = this.button.getAttribute('data-in-progress');
    this.isGenerated = this.button.getAttribute('data-is-generated');
    this.isReviewed = this.button.getAttribute('data-is-reviewed');

    this.initializeAddToBatchButton();
    this.initializeAiMarker();
  }

  initializeAddToBatchButton() {
    let self = this;

    if (self.inProgress) {
      self.button.querySelector('svg.icon-color').style.display = 'none';
      self.button.querySelector('span.icon-markup').classList.add('spinner-border', 'spinner-border-sm');
    }

    self.button.addEventListener("click", function (ev) {
      ev.preventDefault();

      if (self.inProgress) {
        console.log('AI-Generierung ist bereits in Arbeit.');
      } else {
        let id = self.id;
        let table = this.getAttribute('data-table');
        let fieldName = this.getAttribute('data-field-name');
        let fieldLabel = this.getAttribute('data-field-label');
        let postData = {
          record_uid: id,
          record_table: table,
          record_field: fieldName,
          fieldLabel: fieldLabel,
        };

        self.button.querySelector('svg.icon-color').style.display = 'none';
        self.button.querySelector('span.icon-markup').classList.add('spinner-border', 'spinner-border-sm');

        self.textarea.classList.remove('border-success', 'bg-success-subtle', 'border-warning', 'bg-warning-subtle');
        self.textarea.classList.add('border', 'border-info', 'bg-info-subtle');

        const reviewButton = document.querySelector('#alttext_review');
        if (reviewButton) {
          reviewButton.remove();
        }

        self.generatedBadgeHandler('info');

        self.generateAlttext(postData)
          .then((res) => {
            self.inProgress = true;
            self.checkAlttextGenerationStatus();

            ResponseHandling.handleSuccess(res, TYPO3.lang['tx_ai3.module.modal.alttextError']);
          })
          .catch(error => {
            clearInterval(self.intervalId);
            ResponseHandling.handleError(error, TYPO3.lang['tx_ai3.module.modal.alttextError']);
          });
      }
    });
  }

  initializeAiMarker() {
    let self = this;

    if (self.inProgress) {
      self.textarea.classList.add('border', 'border-info');
      self.generatedBadgeHandler('info');
    } else if (self.isGenerated) {
      if (self.isReviewed) {
        self.textarea.classList.add('border', 'border-success');
        self.generatedBadgeHandler('success');
      } else {
        self.textarea.classList.add('border', 'border-warning');
        self.generatedBadgeHandler('warning');
        self.reviewButtonInitialization();
      }
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

  checkAlttextGenerationStatus(data) {
    let self = this;

    const poll = async () => {
      const status = await Ajax.sendAjaxRequest('ai3_alttext_generation_status', {record_uid: self.id});
      if (!status) {
        return;
      }
      if (status.done) {
        clearInterval(self.intervalId);

        self.inProgress = false;
        if (typeof status.result === 'string') {
          self.textarea.value = status.result;
        }

        // Update AddToBatch button
        self.button.querySelector('span.icon-markup').classList.remove('spinner-border', 'spinner-border-sm');
        self.button.querySelector('svg.icon-color').style.display = '';

        // Update AI marker
        self.textarea.classList.remove('border-info', 'bg-info-subtle');
        if (status.reviewed) {
          self.textarea.classList.add('border-success', 'bg-success-subtle');
        } else {
          self.textarea.classList.add('border-warning', 'bg-warning-subtle');
        }

        // Update AI badge
        if (status.reviewed) {
          self.generatedBadgeHandler('success');
        } else {
          self.generatedBadgeHandler('warning');
        }

        // Handle review button
        if (!status.reviewed) {
          self.reviewButtonInitialization();
        }
      }
    };
    self.intervalId = setInterval(poll, 5000);
    poll();
  }

  reviewButtonInitialization() {
    let self = this;

    if (!document.querySelector('#alttext_review')) {
      self.textarea.insertAdjacentHTML('afterend', '<span style="position: absolute; bottom: 5px; left: 5px;"><button id="alttext_review" class="btn btn-success rounded-pill btn-sm px-1 py-1 pe-2" type="button"><typo3-backend-icon identifier="actions-check" size="small"></typo3-backend-icon> Als geprüft markieren</button></span>');
    }

    const reviewButton = document.querySelector('#alttext_review');
    self.reviewButtonEventListener(reviewButton);
  }

    reviewButtonEventListener(reviewButton) {
    let self = this;

    reviewButton.addEventListener('click', function () {
      this.querySelector('typo3-backend-icon').identifier = 'spinner-circle';

      let postData = {
        record_uid: self.id
      };

      self.reviewAlttext(postData)
        .then((res) => {
          self.textarea.classList.remove('border-warning', 'bg-warning-subtle');
          self.textarea.classList.add('border-success', 'bg-success-subtle');

          self.generatedBadgeHandler('success');

          this.style.display = 'none';

          clearInterval(self.intervalId);
          ResponseHandling.handleSuccess(res, TYPO3.lang['tx_ai3.module.modal.alttextError']);
        })
        .catch(error => {
          clearInterval(self.intervalId);
          ResponseHandling.handleError(error, TYPO3.lang['tx_ai3.module.modal.alttextError']);
        });
    })
  }

  generatedBadgeHandler(style) {
    let self = this;

    const generatedBadge = document.querySelector('#alttext_generated_badge');
    if (!generatedBadge) {
      self.textarea.insertAdjacentHTML('afterend', '<span id="alttext_generated_badge" class="ki-label badge badge-' + style + ' text-bg-' + style + '" style="position: absolute; bottom: 5px; right: 5px;">KI generiert</span>');
    }

    if (generatedBadge && style) {
      generatedBadge.classList.remove('badge-success', 'text-bg-success', 'badge-warning', 'text-bg-warning', 'badge-info', 'text-bg-info');
      generatedBadge.classList.add('badge-' + style, 'text-bg-' + style);

      if (style === 'info') {
        generatedBadge.innerHTML = 'Generierung in Arbeit';
      } else {
        generatedBadge.innerHTML = 'KI generiert';
      }
    }
  }
}

export default new AddToBatch();
