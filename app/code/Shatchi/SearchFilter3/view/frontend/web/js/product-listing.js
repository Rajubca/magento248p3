define(['jquery', 'mage/cookies', 'domReady!'], function ($) {
  'use strict';

  return function (config, element) {
    // ===== root element =====
    const $root = element ? $(element) : $('#product-list-container');
    if (!$root.length) { console.warn('[SF3] root not found'); return; }

    // ===== config =====
    const baseUrl = String(config.baseUrl || '');
    const categoryId = String(config.categoryId || '');
    const isLoggedIn = (config.isLoggedIn === 1 || config.isLoggedIn === '1' || config.isLoggedIn === true);
    const addToCartUrlPrefix = String(config.addToCartUrlPrefix || '');
    let maxPrice = Number(config.maxPrice) || 0;

    // ===== state =====
    let allProducts = [];
    let filteredProducts = [];
    let currentPage = 1;
    let productsPerPage = Number($('.pager-dropdown').first().val() || 12);
    let currentSort = $('.sorter-dropdown').first().val() || 'name_asc';
    let currentStockFilter = 'all';
    let priceMinInput, priceMaxInput;

    // fetch control
    let fetchInFlight = false;
    let fetchAbort = null;
    const MAX_TRIES = 3;
    const REQUEST_TIMEOUT = 15000; // ms
    const FORM_KEY_WAIT = 8000;    // ms

    // ===== clean previous bindings (critical when pjax swaps content) =====
    // If this is a re-init on the same DOM node, blow away old handlers first.
    try { $(window).off('.sf3'); $('body').off('.sf3'); $root.off('.sf3'); } catch (_) { }

    // mark current category on the node (helps us see category flips)
    $root.data('sf3Cat', categoryId);

    // ===== helpers =====
    function debounce(fn, wait) { let t; return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), wait); } }
    function showLoader() { document.getElementById('shatchi-loader-overlay')?.style.setProperty('display', 'flex'); }
    function hideLoader() { document.getElementById('shatchi-loader-overlay')?.style.setProperty('display', 'none'); }

    async function ensureFormKey(timeoutMs = FORM_KEY_WAIT) {
      const started = Date.now();
      const readKey = () =>
        $.mage.cookies.get('form_key') ||
        (typeof window.FORM_KEY !== 'undefined' ? window.FORM_KEY : '') ||
        (document.querySelector('input[name="form_key"]')?.value || '');
      let key = readKey();
      while (!key && (Date.now() - started) < timeoutMs) {
        await new Promise(r => setTimeout(r, 50));
        key = readKey();
      }
      return key || '';
    }

    function waitForImagesToLoad(container, done) {
      const images = container.querySelectorAll('img');
      let loaded = 0, total = images.length;
      if (!total) return done();
      images.forEach(img => {
        const cb = () => (++loaded === total) && done();
        if (img.complete) cb(); else { img.addEventListener('load', cb); img.addEventListener('error', cb); }
      });
    }

    function updatePriceLabel() {
      if (!priceMinInput || !priceMaxInput) return;
      $('#price-range-label').text(`£${priceMinInput.value} - £${priceMaxInput.value}`);
    }

    function updateTrack() {
      if (!priceMinInput || !priceMaxInput || !maxPrice) return;
      const min = parseInt(priceMinInput.value || 0, 10);
      const max = parseInt(priceMaxInput.value || maxPrice, 10);
      const minPct = Math.max(0, Math.min(100, (min / maxPrice) * 100));
      const maxPct = Math.max(0, Math.min(100, (max / maxPrice) * 100));
      $('.range-track').css('background',
        `linear-gradient(to right, #ddd ${minPct}%, #555 ${minPct}%, #555 ${maxPct}%, #ddd ${maxPct}%)`);
    }

    function updateSliderMaxPrice(products) {
      if (!priceMinInput || !priceMaxInput) return;
      const prices = products.map(p => Number(p.price)).filter(Number.isFinite);
      const newMax = prices.length ? Math.ceil(Math.max(...prices)) : (maxPrice || 0);
      if (!Number.isFinite(newMax) || newMax <= 0) return;
      maxPrice = newMax;
      priceMinInput.max = String(maxPrice);
      priceMaxInput.max = String(maxPrice);
      if (!priceMinInput.value) priceMinInput.value = '0';
      if (!priceMaxInput.value || isNaN(priceMaxInput.value)) priceMaxInput.value = String(maxPrice);
      updatePriceLabel(); updateTrack();
    }

    function filterProductsByPrice(products) {
      if (!priceMinInput || !priceMaxInput) return products;
      const minVal = Number.parseFloat(priceMinInput.value);
      const maxValFromInput = Number.parseFloat(priceMaxInput.value);
      const min = Number.isFinite(minVal) ? minVal : 0;
      const cap = Number.isFinite(maxValFromInput) ? maxValFromInput
        : (Number.isFinite(maxPrice) && maxPrice > 0 ? maxPrice : Number.MAX_SAFE_INTEGER);
      return products.filter(p => {
        const price = Number.parseFloat(p.price);
        return Number.isFinite(price) && price >= min && price <= cap;
      });
    }

    function filterProductsByStock(products) {
      if (currentStockFilter === 'all') return products;
      return products.filter(p => {
        const st = p.stock || 'in_stock';
        return (currentStockFilter === 'in' && st === 'in_stock') ||
          (currentStockFilter === 'out' && st === 'out_of_stock');
      });
    }

    function sortProducts(products) {
      const s = [...products];
      switch (currentSort) {
        case 'name_asc': return s.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { numeric: true, sensitivity: 'base' }));
        case 'name_desc': return s.sort((a, b) => (b.name || '').localeCompare(a.name || '', undefined, { numeric: true, sensitivity: 'base' }));
        case 'price_asc': return s.sort((a, b) => parseFloat(a.price || 0) - parseFloat(b.price || 0));
        case 'price_desc': return s.sort((a, b) => parseFloat(b.price || 0) - parseFloat(a.price || 0));
        default: return s.sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { numeric: true, sensitivity: 'base' }));
      }
    }

    // ── Per-image loader builder
    function createImageWithLoader(imgUrl, altText, width, height) {
      const wrap = document.createElement('div');
      wrap.className = 'sf3-img-wrap';

      const skel = document.createElement('div');
      skel.className = 'sf3-skeleton';
      const spin = document.createElement('div');
      spin.className = 'sf3-spinner';

      const img = document.createElement('img');
      img.className = 'sf3-img';
      img.alt = altText || '';
      img.decoding = 'async';
      img.loading = 'lazy';
      if (width) img.width = width;
      if (height) img.height = height;
      // defer real src to IntersectionObserver
      img.dataset.src = imgUrl;

      img.addEventListener('load', () => wrap.classList.add('sf3-loaded'));
      img.addEventListener('error', () => wrap.classList.add('sf3-loaded')); // optional: set fallback src

      wrap.appendChild(skel);
      wrap.appendChild(spin);
      wrap.appendChild(img);
      return wrap;
    }

    // one shared observer to swap data-src -> src as images near viewport
    let sf3ImgObserver;
    function getImgObserver() {
      if (sf3ImgObserver) return sf3ImgObserver;
      sf3ImgObserver = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          const img = entry.target;
          const src = img.dataset.src;
          if (src) { img.src = src; img.removeAttribute('data-src'); }
          obs.unobserve(img);
        });
      }, { rootMargin: '200px 0px', threshold: 0.01 });
      return sf3ImgObserver;
    }

    function renderProducts(products) {
      const $container = $root;
      const $toolbar = $('#toolbar-container');
      const $pager = $('#pagination-controls');

      $container.empty();

      if (!products.length) {
        $container.html('<p>No products match your filters.</p>');
        $toolbar.hide(); $pager.hide();
        return;
      }
      $toolbar.show().addClass('fade-in');
      $pager.show().addClass('fade-in');

      const frag = document.createDocumentFragment();
      const io = getImgObserver();

      products.forEach((p, i) => {
        const stockText = p.stock === 'in_stock' ? 'In Stock' : 'Out of Stock';
        // const imgSrc = baseUrl + 'media/catalog/product' + (p.image || '/placeholder.jpg');
        const rawImg = p.image || '/placeholder.jpg';
        const safeImg = rawImg.startsWith('/') ? rawImg : ('/' + rawImg);
        const imgSrc = baseUrl + 'media/catalog/product' + safeImg;

        const productId = p.id || '';
        const minQty = p.min_qty || 1;
        const productUrl = p.url_key || '#';
        const productName = p.name || 'Unknown Product';

        const priceHtml = isLoggedIn
          ? `<div class="price-box price-final_price">
           <span class="price-container price-final_price tax weee">
             <span class="price-wrapper">£${parseFloat(p.price || 0).toFixed(2)}</span>
           </span>
         </div>`
          : `<div class="price-box price-final_price">
           <span class="price-container price-final_price tax weee">
             <span class="price-wrapper">£<span class="price starprice">**˙**</span></span>
           </span>
         </div>`;

        const loginBtn = !isLoggedIn
          ? `<div class="callforprice-action callforprice-action-${productId}">
           <a href="${baseUrl}customer/account/login/" class="action primary"><span>Login for price</span></a>
         </div>` : '';

        const cartBtn = (isLoggedIn && p.stock === 'in_stock')
          ? `<div class="product-item-actions">
           <div class="actions-primary">
             <button type="button" class="action tocart primary add-to-cart-btn"
                     data-product-id="${productId}" data-min-qty="${minQty}">
               <span>Add to Cart</span>
             </button>
           </div>
         </div>`
          : (p.has_variants ? `
         <div class="product-item-actions">
           <div class="actions-primary">
             <a href="${productUrl}" class="action primary try-size-btn"><span>Try Different Size</span></a>
           </div>
         </div>` : '');

        const stockHtml = p.stock === 'out_of_stock' ? `<div class="stock unavailable">${stockText}</div>` : '';

        // ===== build DOM (no string concat for the image part) =====
        const li = document.createElement('li');
        li.className = 'item product product-item';
        li.style.animationDelay = `${i * 80}ms`;

        const info = document.createElement('div');
        info.className = 'product-item-info type1';
        info.setAttribute('data-container', 'product-grid');

        const photo = document.createElement('div');
        photo.className = 'product photo product-item-photo';

        const link = document.createElement('a');
        link.href = productUrl;
        link.tabIndex = -1;

        // per-image loader block
        const imgWrap = createImageWithLoader(imgSrc, productName, 600, 600);
        link.appendChild(imgWrap);
        photo.appendChild(link);

        const details = document.createElement('div');
        details.className = 'product details product-item-details';

        const strong = document.createElement('strong');
        strong.className = 'product name product-item-name';
        strong.innerHTML = `<a class="product-item-link" href="${productUrl}">${productName}</a>`;

        details.appendChild(strong);
        details.insertAdjacentHTML('beforeend', priceHtml + (isLoggedIn ? cartBtn : loginBtn) + stockHtml);

        info.appendChild(photo);
        info.appendChild(details);
        li.appendChild(info);
        frag.appendChild(li);

        // activate lazy for this image
        const img = imgWrap.querySelector('img.sf3-img');
        io.observe(img);
      });

      $container.append(frag);
    }

    function updatePaginationControls(total) {
      const totalPages = Math.max(1, Math.ceil(total / productsPerPage));
      $('#prev-page').prop('disabled', currentPage === 1);
      $('#next-page').prop('disabled', currentPage >= totalPages);
      $('#page-info').text(`Page ${currentPage} of ${totalPages}`);
    }

    function renderPage(page) {
      showLoader();
      currentPage = page;
      filteredProducts = filterProductsByPrice(filterProductsByStock(allProducts));
      const sorted = sortProducts(filteredProducts);
      const start = (page - 1) * productsPerPage;
      const end = start + productsPerPage;
      renderProducts(sorted.slice(start, end));
      // const node = $root.get(0);
      // waitForImagesToLoad(node, () => { updatePaginationControls(filteredProducts.length); hideLoader(); });
      updatePaginationControls(filteredProducts.length);
      hideLoader(); // overlay off; per-image skeletons remain until each image loads
    }

    // ===== fetch (single implementation) =====
    async function fetchProducts(tryNum = 1, reason = 'initial') {
      // abort any previous fetch tied to this instance
      if (fetchAbort) { try { fetchAbort.abort(); } catch (_) { } }
      fetchAbort = (window.AbortController ? new AbortController() : null);

      fetchInFlight = true;
      showLoader();

      const type = isLoggedIn ? 'customer' : 'guest';
      const formKey = await ensureFormKey(); // waits up to FORM_KEY_WAIT

      let timer;
      try {
        if (fetchAbort) {
          timer = setTimeout(() => fetchAbort.abort(), REQUEST_TIMEOUT);
        }

        const res = await fetch(`${baseUrl}searchfilter3/ajax/products`, {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
          },
          body:
            `category_id=${encodeURIComponent(categoryId)}` +
            `&type=${type}` +
            `&form_key=${encodeURIComponent(formKey)}` +
            `&_ts=${Date.now()}`,
          ...(fetchAbort ? { signal: fetchAbort.signal } : {})
        });

        clearTimeout(timer);

        const ct = res.headers.get('content-type') || '';
        const data = ct.includes('application/json') ? await res.json() : {};

        if (data && data.success && Array.isArray(data.products)) {
          allProducts = data.products;
          updateSliderMaxPrice(allProducts);
          renderPage(1);
          return;
        }

        if (data && /invalid form key/i.test(data.message || '')) {
          if (tryNum < MAX_TRIES) {
            return fetchProducts(tryNum + 1, 'invalid-form-key');
          }
        }

        throw new Error((data && data.message) || `Bad payload (status ${res.status})`);
      } catch (err) {
        if (tryNum < MAX_TRIES) {
          return fetchProducts(tryNum + 1, 'retry-after-error');
        }
        $root.html('<p>No products found.</p>');
        hideLoader();
      } finally {
        fetchInFlight = false;
      }
    }

    // ===== events =====
    function bindEvents() {
      // Add to cart (delegated)
      $root.on('click.sf3', '.add-to-cart-btn', function () {
        const productId = $(this).data('product-id');
        const minQty = $(this).data('min-qty') || 1;
        const formKey = $.mage.cookies.get('form_key');
        const addToCartUrl = `${baseUrl}checkout/cart/add/uenc/${addToCartUrlPrefix}/product/${productId}/`;
        showLoader();
        $.ajax({ url: addToCartUrl, type: 'POST', data: { product: productId, qty: minQty, form_key: formKey } })
          .always(() => hideLoader());
      });

      $('.sorter-dropdown').on('change.sf3', function () {
        currentSort = $(this).val();
        $('.sorter-dropdown').val(currentSort);
        renderPage(1);
      });

      $('.pager-dropdown').on('change.sf3', function () {
        productsPerPage = parseInt(this.value, 10) || 12;
        $('.pager-dropdown').val(productsPerPage);
        renderPage(1);
      });

      $('#prev-page').on('click.sf3', () => currentPage > 1 && renderPage(currentPage - 1));
      $('#next-page').on('click.sf3', () => renderPage(currentPage + 1));

      $('input[name="stock_filter"]').on('change.sf3', function () {
        currentStockFilter = this.value;
        renderPage(1);
      });

      $('#reset-filters').on('click.sf3', function () {
        if (!priceMinInput || !priceMaxInput) return;
        priceMinInput.value = '0';
        priceMaxInput.value = String(maxPrice || 0);
        $('input[name="stock_filter"][value="all"]').prop('checked', true);
        updatePriceLabel(); updateTrack(); renderPage(1);
      });

      // price inputs
      priceMinInput = document.getElementById('price-min');
      priceMaxInput = document.getElementById('price-max');
      if (priceMinInput && priceMaxInput) {
        priceMinInput.addEventListener('input', debounce(() => {
          if (+priceMinInput.value > +priceMaxInput.value) priceMinInput.value = priceMaxInput.value;
          updatePriceLabel(); updateTrack(); renderPage(1);
        }, 300));

        priceMaxInput.addEventListener('input', debounce(() => {
          if (+priceMaxInput.value < +priceMinInput.value) priceMaxInput.value = priceMinInput.value;
          updatePriceLabel(); updateTrack(); renderPage(1);
        }, 300));
      }
    }

    // ===== init flow =====
    bindEvents();
    updatePriceLabel();
    updateTrack();

    // Show loader immediately and kick fetch (no alerts needed)
    showLoader();
    fetchProducts(1, 'startup');

    // Fallbacks that won’t double-fire while a fetch is running
    $(window).on('load.sf3', function () {
      if (!fetchInFlight && (!Array.isArray(allProducts) || allProducts.length === 0)) {
        showLoader();
        fetchProducts(1, 'window-load');
      }
    });

    $('body').on('contentUpdated.sf3', function () {
      // Only refetch if our root is still in the DOM and nothing has loaded
      if ($root.length && $.contains(document, $root[0])) {
        if (!fetchInFlight && (!Array.isArray(allProducts) || allProducts.length === 0)) {
          showLoader();
          fetchProducts(1, 'contentUpdated');
        }
      }
    });

    // Cleanup if node is removed
    $root.on('remove', function () {
      try { $(window).off('.sf3'); $('body').off('.sf3'); $root.off('.sf3'); } catch (_) { }
      if (fetchAbort) { try { fetchAbort.abort(); } catch (_) { } }
    });
  };
});
