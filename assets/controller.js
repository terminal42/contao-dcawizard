import { Controller } from "@hotwired/stimulus";

export default class WidgetController extends Controller {
    open(event) {
        let options;

        try {
            options = JSON.parse(event.currentTarget.dataset.dcawizardOptions);
        } catch {
            console.error('Could not parse JSON options for DCA wizard: ' + event.currentTarget.dataset.dcawizardOptions);

            return;
        }

        let opt = options || {},
            maxWidth = (window.getSize().x - 20).toInt(),
            maxHeight = (window.getSize().y - 137).toInt();
        if (!opt.width || opt.width > maxWidth) opt.width = Math.min(maxWidth, 900);
        if (!opt.height || opt.height > maxHeight) opt.height = maxHeight;

        const M = new SimpleModal({
            'width': opt.width,
            'hideFooter': true,
            'draggable': false,
            'overlayOpacity': .7,
            'overlayClick': false,
            'onShow': function() { document.body.setStyle('overflow', 'hidden'); },
            'onHide': function() {
                document.body.setStyle('overflow', 'auto');

                new Request.Contao({
                    evalScripts: false,
                    onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
                    onSuccess: function (txt, json) {
                        $('ctrl_' + opt.id).set('html', json.content);
                        json.javascript && Browser.exec(json.javascript);
                        AjaxRequest.hideBox();
                        window.fireEvent('ajax_change');
                    }
                }).post({
                    'action': 'reloadDcaWizard',
                    'name': opt.id,
                    'REQUEST_TOKEN': Contao.request_token,
                    'class': opt.class
                });
            }
        });

        M.show({
            'title': opt.title?.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;'),
            'contents': '<iframe src="' + opt.url + '" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
            'model': 'modal'
        });
    }
}
