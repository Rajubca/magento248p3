define('Shatchi_SearchFilter3/js/chunky-loader', ['jquery'], function ($) {

    'use strict';

    return function (config) {
        const categoryId = config.categoryId;
        const productList = $('#product-list');
        let currentPage = 1;
        let loading = false;
        let stop = false;

        function loadNextChunk() {
            if (loading || stop) return;
            loading = true;

            $.ajax({
                url: '/searchfilter3/ajax/category',
                method: 'GET',
                data: {
                    category_id: categoryId,
                    page: currentPage
                },
                dataType: 'json',
                success: function (response) {
                    if (response && response.html) {
                        productList.append(response.html);
                    }
                    if (response && response.stop) {
                        stop = true;
                    } else {
                        currentPage++;
                        loadNextChunk(); // auto-load next chunk
                    }
                },
                error: function () {
                    console.error('Chunk load failed.');
                    stop = true;
                },
                complete: function () {
                    loading = false;
                }
            });
        }

        $(document).ready(function () {
            loadNextChunk();
        });
    };
});
