/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2012 Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://www.isotopeecommerce.com
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 */

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
        if (!opt.height || opt.height > max) opt.height = max;
        var M = new SimpleModal({
            'width': opt.width,
            'btn_ok': Contao.lang.close,
            'draggable': false,
            'overlayOpacity': .5,
            'onShow': function() { document.body.setStyle('overflow', 'hidden'); },
            'onHide': function() { document.body.setStyle('overflow', 'auto'); }
        });
        M.addButton(Contao.lang.apply, 'btn primary', function() {
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
            if (frm.document.location.href.indexOf('contao/main.php') == -1) {
                alert(Contao.lang.picker);
                return; // see #5704
            }
            new Request.Contao({
                evalScripts: false,
                onRequest: AjaxRequest.displayBox(Contao.lang.loading + ' …'),
                onSuccess: function(txt, json) {
                    $('ctrl_'+opt.id).set('html', json.content);
                    json.javascript && Browser.exec(json.javascript);
                    AjaxRequest.hideBox();
                }
            }).post({'action':'reloadDcaWizard', 'name':opt.id, 'REQUEST_TOKEN':Contao.request_token});
            this.hide();
        });
        M.show({
            'title': opt.title,
            'contents': '<iframe src="' + opt.url + '" name="simple-modal-iframe" width="100%" height="' + opt.height + '" frameborder="0"></iframe>',
            'model': 'modal'
        });
    }
}
