var DcaWizard =
{

    /**
     * Open a modal window
     * @param object
     */
    openModalWindow: function(options)
    {
        var opt = options || {};
        var max = (window.getSize().y-180).toInt();
        var label = opt.applyLabel ? opt.applyLabel : Contao.lang.close;
        if (!opt.height || opt.height > max) opt.height = max;
        var M = new SimpleModal({
            'keyEsc': false, // see https://github.com/terminal42/contao-notification_center/issues/99
            'width': opt.width,
            'btn_ok': label,
            'draggable': false,
            'closeButton': false,
            'overlayOpacity': .5,
            'onShow': function() { document.body.setStyle('overflow', 'hidden'); },
            'onHide': function() { document.body.setStyle('overflow', 'auto'); }
        });
        M.addButton(label, 'btn', function() {
            var frm = null,
                frms = window.frames;
            for (var i=0; i<frms.length; i++) {
                if (frms[i].name == 'simple-modal-iframe') {
                    frm = frms[i];
                    break;
                }
            }
            if (frm === null) {
                alert('Could not find the SimpleModal frame');
                return;
            }

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
            this.hide();
        });
        M.show({
            'title': opt.title,
            'contents': '<iframe src="' + opt.url + '" name="simple-modal-iframe" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
            'model': 'modal'
        });
    }
}
