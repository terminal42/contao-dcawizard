import './dcawizard.scss';

import { Application, Controller } from '@hotwired/stimulus';

const application = Application.start();
application.debug = process.env.NODE_ENV === 'development';
application.register(
    'terminal42--dcawizard',
    class extends Controller {
        static values = {
            id: String,
            class: String,
            title: String,
        };

        open(event) {
            const width = Math.min((window.getSize().x - 20).toInt(), 900);
            const height = (window.getSize().y - 137).toInt();

            let M;
            const closeModal = function (message) {
                if (message.data === 'closeModal') {
                    M.hide();
                }
            };

            M = new SimpleModal({
                width,
                hideFooter: true,
                draggable: false,
                overlayOpacity: 0.7,
                overlayClick: false,
                onShow: () => {
                    window.addEventListener('message', closeModal);
                    document.body.setStyle('overflow', 'hidden');
                },
                onHide: () => {
                    window.removeEventListener('message', closeModal);
                    document.body.setStyle('overflow', '');
                    AjaxRequest.displayBox(`${Contao.lang.loading} …`);
                    this.#reloadWidget();
                },
            });

            const url = event.currentTarget.getAttribute('href');
            const { form } = event.currentTarget;

            M.show({
                title: this.titleValue
                    ?.replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&apos;'),
                contents: `<iframe id="dcawizard_form" name="dcawizard_form" width="100%" height="${height}" frameborder="0"></iframe>`,
                model: 'modal',
            });

            if (url) {
                document.getElementById('dcawizard_form').src = `${url}&popup=1`;
            } else if (form) {
                const originalAction = form.action;
                form.action = `${form.action}&popup=1`;
                form.setAttribute('target', 'dcawizard_form');
                form.submit();

                setTimeout(() => {
                    form.action = originalAction;
                    form.removeAttribute('target');
                }, 100);
            }
        }

        request(event) {
            if (event.params.confirm && !window.confirm(event.params.confirm)) {
                return;
            }

            const url = event.currentTarget.getAttribute('href');
            const { form } = event.currentTarget;

            const params = {
                method: 'get',
                url,
                evalScripts: false,
                followRedirects: false,
                onRequest: AjaxRequest.displayBox(`${Contao.lang.loading} …`),
                onComplete: () => this.#reloadWidget(),
            };

            if (form) {
                params.method = form.method || 'get';
                params.url = form.action;
            }

            new Request.Contao(params).send();
        }

        #reloadWidget() {
            new Request.Contao({
                evalScripts: false,
                onSuccess: (txt, json) => {
                    $(`ctrl_${this.idValue}`).set('html', json.content);

                    if (json.javascript) {
                        Browser.exec(json.javascript);
                    }

                    AjaxRequest.hideBox();
                    window.fireEvent('ajax_change');
                },
            }).post({
                action: 'reloadDcaWizard',
                name: this.idValue,
                REQUEST_TOKEN: Contao.request_token,
                class: this.classValue,
            });
        }
    },
);
