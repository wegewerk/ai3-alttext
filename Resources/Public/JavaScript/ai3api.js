import AjaxRequest from "@typo3/core/ajax/ajax-request.js";

class Ai3Api {
    createAlttextTask(file) {
        if (file.inProgress) {
            return new Promise((resolve, reject) => {
                resolve();
            });
        }
        let postData = {
            file: file.uid,
        };

        return new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_filelist_create_task_alttext'])
            .post(postData)
            .then(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                file.inProgress = true;
            })
            .catch(async function (response) {
                const resolved = await response.resolve();
                const responseBody = JSON.parse(resolved);
                Notification.error('fehler', responseBody.message);
            });
    }

    acceptAltSuggestion(file) {
        const generationtaskData = {
            record_uid: file.uid
        }
        const filemetaData = {
            uid: file.uid,
            altText: file.altSuggestion
        }
        let self = this;
        return Promise.allSettled([
            new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_alttext_generation_review'])
                .post(generationtaskData),
            new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_filelist_save_file'])
                .post(filemetaData)
        ])
            .then(async function (responses) {
                for(let i = 0; i<responses.length; i++) {
                    let response = responses[i];
                    if(response.status !== 'fulfilled') {
                        const resolved = response.value.response;
                        if (response.bodyUsed) {
                            const responseBody = JSON.parse(resolved.body);
                            Notification.error('fehler', responseBody.message);
                        }
                    }
                }
                file.isGenerated=true;
                file.hasGeneration=false;
                file.alternative=file.altSuggestion;
                file.isReviewed=true;
            });
    }
    acceptAltSuggestionRecursive(folderIdentifier) {
        const payload = {
            folder: folderIdentifier
        }
        let self = this;
        return new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_folders_acceptAll_recursive'])
                .post(payload);
    }

    loadFolder(folderIdentifier) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_filelist'])
            .withQueryArguments({'folder': folderIdentifier})
            .get();
    }
    loadSubfolders(folderIdentifier) {
        return new AjaxRequest(TYPO3.settings.ajaxUrls['ai3_folders'])
            .withQueryArguments({'folder': folderIdentifier})
            .get();
    }
}

export {Ai3Api as default};
