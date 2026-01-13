// var config = {
//     paths: {
//         'wow_book': 'Shatchi_Catalogue/js/wow_book.min',
        
//     },
//     shim: {
//         'wow_book': {
//             deps: ['jquery'],
//             exports: 'jQuery.fn.wowBook'
//         }
        
//     }
// };
var config = {
    paths: {
        'jquery191': 'Shatchi_Catalogue/js/jquery-1.9.1.min', // Use CDN
        'wow_book': 'Shatchi_Catalogue/js/wow_book.min'
    },
    shim: {
        'jquery191': {
            exports: '$'
        },
        'wow_book': {
            deps: ['jquery191'],
            exports: 'jQuery.fn.wowBook'
        }
    }
};