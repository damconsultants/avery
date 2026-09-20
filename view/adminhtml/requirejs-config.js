var config = {
    paths: {
        'bynderjs': 'DamConsultants_Avery/js/bynder',
        'select2': 'DamConsultants_Avery/js/select2'
    },
    shim: {
        'bynderjs': {
            deps: ['jquery']
        },
        'select2': {
            deps: ['jquery']
        },
    },
    map: {
        '*': {
            'Magento_PageBuilder/template/form/element/html-code.html': 'DamConsultants_Avery/template/form/element/html-code.html',
            'Magento_PageBuilder/js/form/element/html-code': 'DamConsultants_Avery/js/form/element/html-code',
            'Magento_PageBuilder/template/content-type/video/default/master.html': 'DamConsultants_Avery/template/content-type/video/default/master.html',
            'Magento_PageBuilder/template/content-type/video/default/preview.html': 'DamConsultants_Avery/template/content-type/video/default/preview.html',
        },
    }
};