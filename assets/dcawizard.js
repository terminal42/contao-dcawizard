/* eslint-disable no-alert,no-console,no-undef */

import './dcawizard.scss';

import { Application, Controller } from '@hotwired/stimulus';

const application = Application.start();
application.debug = process.env.NODE_ENV === 'development';
application.register('terminal42--dcawizard', class extends Controller {
    delete(event) {
        const options = this.#getOptions(event);

        if (options === null) {
            return;
        }

        if (!window.confirm(options.confirm)) {
            return;
        }

        new Request.Contao({
            method: 'get',
            url: options.url,
            evalScripts: false,
            followRedirects: false,
            onRequest: AjaxRequest.displayBox(`${Contao.lang.loading} …`),
            onComplete: () => this.#reloadWidget(options),
        }).send();
    }

    open(event) {
        const options = this.#getOptions(event);

        if (options === null) {
            return;
        }

        const maxWidth = (window.getSize().x - 20).toInt();
        const maxHeight = (window.getSize().y - 137).toInt();

        if (!options.width || options.width > maxWidth) {
            options.width = Math.min(maxWidth, 900);
        }

        if (!options.height || options.height > maxHeight) {
            options.height = maxHeight;
        }

        const M = new SimpleModal({
            width: options.width,
            hideFooter: true,
            draggable: false,
            overlayOpacity: 0.7,
            overlayClick: false,
            onShow: () => document.body.setStyle('overflow', 'hidden'),
            onHide: () => {
                document.body.setStyle('overflow', 'auto');
                AjaxRequest.displayBox(`${Contao.lang.loading} …`);
                this.#reloadWidget(options);
            },
        });

        M.show({
            title: options.title?.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;'),
            contents: `<iframe src="${options.url}" width="100%" height="${options.height}" frameborder="0"></iframe>`,
            model: 'modal',
        });
    }

    #getOptions(event) {
        try {
            return JSON.parse(event.currentTarget.dataset.dcawizardOptions);
        } catch {
            console.error(`Could not parse JSON options for DCA wizard: ${event.currentTarget.dataset.dcawizardOptions}`);

            return null;
        }
    }

    #reloadWidget(options) {
        new Request.Contao({
            evalScripts: false,
            onSuccess: (txt, json) => {
                $(`ctrl_${options.id}`).set('html', json.content);

                if (json.javascript) {
                    Browser.exec(json.javascript);
                }

                AjaxRequest.hideBox();
                window.fireEvent('ajax_change');
            },
        }).post({
            action: 'reloadDcaWizard',
            name: options.id,
            REQUEST_TOKEN: Contao.request_token,
            class: options.class,
        });
    }
});
