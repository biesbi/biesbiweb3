// Hızlı Ürün Arama ve Filtreleme - Backend Tabanlı
(function () {
  var searchTimeout = null;
  var currentCategory = null;
  var apiBase = '/api';
  var productsCache = null;
  var categoriesCache = null;

  // API Base URL'i otomatik tespit et
  function getApiBase() {
    var scripts = document.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
      var content = scripts[i].textContent;
      if (content && content.indexOf('apiBase') !== -1) {
        var match = content.match(/apiBase\s*=\s*['"]([^'"]+)['"]/);
        if (match) return match[1];
      }
    }
    return '/api';
  }

  apiBase = getApiBase();

  function normalize(text) {
    return String(text || '')
      .toLocaleLowerCase('tr-TR')
      .replace(/ı/g, 'i')
      .replace(/ğ/g, 'g')
      .replace(/ü/g, 'u')
      .replace(/ş/g, 's')
      .replace(/ö/g, 'o')
      .replace(/ç/g, 'c')
      .trim();
  }

  // Backend'den direkt kategori verilerini çek
  function loadCategories() {
    if (categoriesCache) {
      return Promise.resolve(categoriesCache);
    }

    return fetch(apiBase + '/products/categories/list')
      .then(function (response) {
        if (!response.ok) throw new Error('Categories fetch failed');
        return response.json();
      })
      .then(function (data) {
        categoriesCache = Array.isArray(data) ? data : [];
        return categoriesCache;
      })
      .catch(function (error) {
        console.error('Kategori yükleme hatası:', error);
        return [];
      });
  }

  // Backend'den direkt ürün ara - ÇOK HIZLI
  function searchProductsFromBackend(searchTerm) {
    clearTimeout(searchTimeout);

    if (!searchTerm || searchTerm.length < 2) {
      loadAllProducts();
      return;
    }

    // Arama göstergesini göster
    showLoadingIndicator();

    searchTimeout = setTimeout(function () {
      var url = apiBase + '/products?search=' + encodeURIComponent(searchTerm);

      fetch(url)
        .then(function (response) {
          if (!response.ok) throw new Error('Search failed');
          return response.json();
        })
        .then(function (products) {
          hideLoadingIndicator();
          renderProducts(Array.isArray(products) ? products : []);
        })
        .catch(function (error) {
          console.error('Arama hatası:', error);
          hideLoadingIndicator();
          showErrorMessage('Arama sırasında bir hata oluştu.');
        });
    }, 200); // Debounce 200ms - daha hızlı
  }

  // Backend'den kategori bazlı filtrele - ÇOK HIZLI
  function filterByCategory(categorySlugOrId) {
    if (!categorySlugOrId) {
      loadAllProducts();
      return;
    }

    currentCategory = categorySlugOrId;
    showLoadingIndicator();

    var url = apiBase + '/products?category=' + encodeURIComponent(categorySlugOrId);

    fetch(url)
      .then(function (response) {
        if (!response.ok) throw new Error('Category filter failed');
        return response.json();
      })
      .then(function (products) {
        hideLoadingIndicator();
        renderProducts(Array.isArray(products) ? products : []);
      })
      .catch(function (error) {
        console.error('Kategori filtreleme hatası:', error);
        hideLoadingIndicator();
        showErrorMessage('Ürünler yüklenirken bir hata oluştu.');
      });
  }

  // Tüm ürünleri yükle
  function loadAllProducts() {
    currentCategory = null;
    showLoadingIndicator();

    fetch(apiBase + '/products')
      .then(function (response) {
        if (!response.ok) throw new Error('Products fetch failed');
        return response.json();
      })
      .then(function (products) {
        hideLoadingIndicator();
        productsCache = Array.isArray(products) ? products : [];
        renderProducts(productsCache);
      })
      .catch(function (error) {
        console.error('Ürün yükleme hatası:', error);
        hideLoadingIndicator();
        showErrorMessage('Ürünler yüklenirken bir hata oluştu.');
      });
  }

  // Ürünleri render et - Mevcut grid'e yerleştir
  function renderProducts(products) {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    // Mevcut kartları gizle
    var existingCards = grid.querySelectorAll('.product-card');
    Array.prototype.forEach.call(existingCards, function (card) {
      card.style.display = 'none';
    });

    // Varolan mesajları temizle
    var messages = grid.querySelectorAll('.search-result-message, .loading-indicator');
    Array.prototype.forEach.call(messages, function (msg) {
      msg.remove();
    });

    if (products.length === 0) {
      var message = document.createElement('div');
      message.className = 'search-result-message';
      message.style.cssText =
        'grid-column: 1 / -1; ' +
        'text-align: center; ' +
        'padding: 60px 20px; ' +
        'font-size: 1.2rem; ' +
        'color: #666; ' +
        'background: #f8f9fa; ' +
        'border-radius: 12px; ' +
        'margin: 20px 0;';
      message.textContent = 'Aradığınız kriterlere uygun ürün bulunamadı.';
      grid.appendChild(message);
      return;
    }

    // Ürün ID'lerini topla
    var productIds = products.map(function (p) { return p.id || p.slug; });

    // Sadece eşleşen kartları göster
    var shownCount = 0;
    Array.prototype.forEach.call(existingCards, function (card) {
      var cardId = card.getAttribute('data-product-id') ||
                   card.getAttribute('data-slug') ||
                   card.querySelector('.product-title, h3, h4')?.textContent.trim();

      var matchFound = products.some(function (product) {
        var productTitle = product.title || product.name || '';
        var cardTitle = card.querySelector('.product-title, h3, h4')?.textContent.trim() || '';

        return normalize(productTitle) === normalize(cardTitle) ||
               product.id === cardId ||
               product.slug === cardId;
      });

      if (matchFound) {
        card.style.display = '';
        shownCount++;
      }
    });

    // Hiç kart gösterilememediyse mesaj göster
    if (shownCount === 0 && products.length > 0) {
      var infoMessage = document.createElement('div');
      infoMessage.className = 'search-result-message';
      infoMessage.style.cssText =
        'grid-column: 1 / -1; ' +
        'text-align: center; ' +
        'padding: 40px 20px; ' +
        'font-size: 1rem; ' +
        'color: #666;';
      infoMessage.textContent = products.length + ' ürün bulundu ancak sayfa yenilenmesi gerekebilir.';
      grid.appendChild(infoMessage);
    }
  }

  // Yükleniyor göstergesi
  function showLoadingIndicator() {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    var existing = grid.querySelector('.loading-indicator');
    if (existing) return;

    var indicator = document.createElement('div');
    indicator.className = 'loading-indicator';
    indicator.style.cssText =
      'position: fixed; ' +
      'top: 80px; ' +
      'right: 20px; ' +
      'background: rgba(255, 204, 0, 0.95); ' +
      'color: #000; ' +
      'padding: 12px 24px; ' +
      'border-radius: 25px; ' +
      'font-weight: bold; ' +
      'z-index: 9999; ' +
      'box-shadow: 0 4px 12px rgba(0,0,0,0.15); ' +
      'animation: fadeIn 0.2s;';
    indicator.textContent = '🔍 Aranıyor...';

    document.body.appendChild(indicator);
  }

  function hideLoadingIndicator() {
    var indicators = document.querySelectorAll('.loading-indicator');
    Array.prototype.forEach.call(indicators, function (indicator) {
      indicator.remove();
    });
  }

  function showErrorMessage(message) {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    var errorDiv = document.createElement('div');
    errorDiv.className = 'search-result-message error-message';
    errorDiv.style.cssText =
      'grid-column: 1 / -1; ' +
      'text-align: center; ' +
      'padding: 40px 20px; ' +
      'font-size: 1.1rem; ' +
      'color: #dc3545; ' +
      'background: #f8d7da; ' +
      'border: 1px solid #f5c6cb; ' +
      'border-radius: 8px;';
    errorDiv.textContent = message;
    grid.appendChild(errorDiv);
  }

  // Arama input'unu oluştur
  function createSearchInput() {
    var headers = document.querySelectorAll('.modern-header');
    if (!headers.length) return;

    Array.prototype.forEach.call(headers, function (header) {
      var actions = header.querySelector('.header-actions');
      if (!actions || actions.querySelector('.header-search-wrapper')) return;

      var searchWrapper = document.createElement('div');
      searchWrapper.className = 'header-search-wrapper';
      searchWrapper.style.cssText = 'position: relative; margin-right: 15px;';

      var searchInput = document.createElement('input');
      searchInput.type = 'text';
      searchInput.placeholder = 'Ürün ara...';
      searchInput.className = 'header-search-input';
      searchInput.style.cssText =
        'padding: 10px 40px 10px 15px; ' +
        'border: 2px solid rgba(255, 255, 255, 0.3); ' +
        'border-radius: 25px; ' +
        'background: rgba(255, 255, 255, 0.15); ' +
        'color: #fff; ' +
        'outline: none; ' +
        'width: 220px; ' +
        'font-size: 14px; ' +
        'transition: all 0.3s ease; ' +
        'backdrop-filter: blur(10px);';

      var searchIcon = document.createElement('span');
      searchIcon.innerHTML = '🔍';
      searchIcon.style.cssText =
        'position: absolute; ' +
        'right: 15px; ' +
        'top: 50%; ' +
        'transform: translateY(-50%); ' +
        'pointer-events: none; ' +
        'font-size: 18px;';

      searchInput.addEventListener('focus', function () {
        this.style.background = 'rgba(255, 255, 255, 0.25)';
        this.style.borderColor = '#ffcc00';
        this.style.width = '280px';
        this.style.boxShadow = '0 4px 12px rgba(255, 204, 0, 0.3)';
      });

      searchInput.addEventListener('blur', function () {
        if (!this.value) {
          this.style.background = 'rgba(255, 255, 255, 0.15)';
          this.style.borderColor = 'rgba(255, 255, 255, 0.3)';
          this.style.width = '220px';
          this.style.boxShadow = 'none';
        }
      });

      // Backend'den direkt ara
      searchInput.addEventListener('input', function () {
        searchProductsFromBackend(this.value);
      });

      searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(searchTimeout);
          searchProductsFromBackend(this.value);
        }
      });

      searchWrapper.appendChild(searchInput);
      searchWrapper.appendChild(searchIcon);
      actions.insertBefore(searchWrapper, actions.firstChild);
    });
  }

  // Scroll'da stil güncelle
  function updateSearchStyleOnScroll() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (mutation.attributeName === 'class') {
          var header = mutation.target;
          var searchInput = header.querySelector('.header-search-input');
          if (!searchInput) return;

          if (header.classList.contains('scrolled')) {
            searchInput.style.border = '2px solid rgba(0, 0, 0, 0.15)';
            searchInput.style.background = 'rgba(255, 255, 255, 0.9)';
            searchInput.style.color = '#111';
            searchInput.placeholder = 'Ürün ara...';
          } else {
            if (!searchInput.value) {
              searchInput.style.border = '2px solid rgba(255, 255, 255, 0.3)';
              searchInput.style.background = 'rgba(255, 255, 255, 0.15)';
              searchInput.style.color = '#fff';
            }
          }
        }
      });
    });

    var headers = document.querySelectorAll('.modern-header');
    Array.prototype.forEach.call(headers, function (header) {
      observer.observe(header, { attributes: true });
    });
  }

  // Kategori butonlarını dinle - Backend filtreleme
  function bindCategoryButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('.filter-btn, [data-category-slug], [data-category]');
      if (!button) return;

      var categorySlug =
        button.getAttribute('data-category-slug') ||
        button.getAttribute('data-category') ||
        normalize(button.textContent);

      // "Tümü" butonuysa tüm ürünleri göster
      if (categorySlug === 'tumu' || categorySlug === 'tümü' || categorySlug === 'all') {
        loadAllProducts();
        return;
      }

      // Backend'den kategori filtrele
      filterByCategory(categorySlug);
    });
  }

  // Sayfa yüklendiğinde
  document.addEventListener('DOMContentLoaded', function () {
    createSearchInput();
    updateSearchStyleOnScroll();
    bindCategoryButtons();
    loadCategories(); // Kategorileri ön yükle
  });

  // Global API
  window.productSearch = {
    search: searchProductsFromBackend,
    filterByCategory: filterByCategory,
    loadAll: loadAllProducts,
    getCategories: loadCategories
  };
})();
