var DcaWizard =
{
    /**
     * Open a modal window
     *
     * @param {Object} options
     */
    openModalWindow: function(options)
    {
        var opt = options || {},
            maxWidth = (window.getSize().x - 20).toInt(),
            maxHeight = (window.getSize().y - 137).toInt(),
            label = opt.applyLabel ? opt.applyLabel : Contao.lang.close;
        if (!opt.width || opt.width > maxWidth) opt.width = Math.min(maxWidth, 900);
        if (!opt.height || opt.height > maxHeight) opt.height = maxHeight;

        var M = new SimpleModal({
            'keyEsc': false, // see https://github.com/terminal42/contao-notification_center/issues/99
            'width': opt.width,
            'draggable': false,
            'hideFooter': true,
            'overlayOpacity': .5,
            'onShow': function() {
                document.body.setStyle('overflow', 'hidden');

                window.addEventListener('message', function (message) {
                    if (message.data === 'closeModal') {
                        M.hide();
                    }
                });
            },
            'onHide': function() {
                document.body.setStyle('overflow', 'auto');

                new Request.Contao({
                    evalScripts: false,
                    onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
                    onSuccess: function(txt, json) {
                        $('ctrl_'+opt.id).set('html', json.content);
                        json.javascript && Browser.exec(json.javascript);
                        AjaxRequest.hideBox();
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
            'title': opt.title,
            'contents': '<iframe src="' + opt.url + '" name="simple-modal-iframe" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
            'model': 'modal'
        });
    }
};
