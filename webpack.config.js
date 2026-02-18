const Encore = require('@terminal42/contao-build-tools');

module.exports = Encore()
    .setOutputPath('public/')
    .setPublicPath('/bundles/terminal42dcawizard')
    .addEntry('dcawizard', './assets/dcawizard.js')
    .getWebpackConfig()
;
