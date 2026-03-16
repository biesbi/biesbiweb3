// PERFORMANS OPTİMİZASYONU - Hızlı Ürün Arama ve Filtreleme
(function () {
  var searchTimeout = null;
  var currentCategory = null;
  var apiBase = '/api';
  var productsCache = null;
  var categoriesCache = null;
  var currentPage = 1;
  var isLoading = false;
  var hasMore = true;

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

  // PERFORMANS OPTİMİZASYONU: Pagination ile ürün yükle
  function searchProductsFromBackend(searchTerm, append) {
    clearTimeout(searchTimeout);

    if (!searchTerm || searchTerm.length < 2) {
      loadAllProducts(false);
      return;
    }

    if (!append) {
      currentPage = 1;
      hasMore = true;
    }

    showLoadingIndicator();

    searchTimeout = setTimeout(function () {
      // LIMIT=20 eklendi - Sadece 20 ürün getir
      var url = apiBase + '/products?search=' + encodeURIComponent(searchTerm) +
                '&limit=20&page=' + currentPage;

      fetch(url)
        .then(function (response) {
          hasMore = response.headers.get('X-Has-More') === '1';
          if (!response.ok) throw new Error('Search failed');
          return response.json();
        })
        .then(function (products) {
          hideLoadingIndicator();
          renderProducts(Array.isArray(products) ? products : [], append);
          updateLoadMoreButton();
        })
        .catch(function (error) {
          console.error('Arama hatası:', error);
          hideLoadingIndicator();
          showErrorMessage('Arama sırasında bir hata oluştu.');
        });
    }, 200);
  }

  // PERFORMANS OPTİMİZASYONU: Kategori filtreleme + pagination
  function filterByCategory(categorySlugOrId, append) {
    if (!categorySlugOrId) {
      loadAllProducts(false);
      return;
    }

    if (!append) {
      currentCategory = categorySlugOrId;
      currentPage = 1;
      hasMore = true;
    }

    showLoadingIndicator();

    // LIMIT=20 eklendi
    var url = apiBase + '/products?category=' + encodeURIComponent(categorySlugOrId) +
              '&limit=20&page=' + currentPage;

    fetch(url)
      .then(function (response) {
        hasMore = response.headers.get('X-Has-More') === '1';
        if (!response.ok) throw new Error('Category filter failed');
        return response.json();
      })
      .then(function (products) {
        hideLoadingIndicator();
        renderProducts(Array.isArray(products) ? products : [], append);
        updateLoadMoreButton();
      })
      .catch(function (error) {
        console.error('Kategori filtreleme hatası:', error);
        hideLoadingIndicator();
        showErrorMessage('Ürünler yüklenirken bir hata oluştu.');
      });
  }

  // PERFORMANS OPTİMİZASYONU: İlk yüklemede 20 ürün
  function loadAllProducts(append) {
    if (!append) {
      currentCategory = null;
      currentPage = 1;
      hasMore = true;
    }

    showLoadingIndicator();

    // LIMIT=20 eklendi - İlk yüklemede sadece 20 ürün
    // "Tüm Ürünler" butonunda da pagination çalışır
    var url = apiBase + '/products?limit=20&page=' + currentPage;

    fetch(url)
      .then(function (response) {
        hasMore = response.headers.get('X-Has-More') === '1';
        if (!response.ok) throw new Error('Products fetch failed');
        return response.json();
      })
      .then(function (products) {
        hideLoadingIndicator();

        if (!append) {
          productsCache = Array.isArray(products) ? products : [];
        }

        renderProducts(Array.isArray(products) ? products : [], append);
        updateLoadMoreButton();
      })
      .catch(function (error) {
        console.error('Ürün yükleme hatası:', error);
        hideLoadingIndicator();
        showErrorMessage('Ürünler yüklenirken bir hata oluştu.');
      });
  }

  // "Daha Fazla Yükle" butonu güncelle
  function updateLoadMoreButton() {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    var existingBtn = grid.querySelector('.load-more-btn');
    if (existingBtn) {
      existingBtn.remove();
    }

    if (hasMore && !isLoading) {
      var btn = document.createElement('button');
      btn.className = 'load-more-btn';
      btn.textContent = 'Daha Fazla Ürün Yükle';
      btn.style.cssText =
        'grid-column: 1 / -1; ' +
        'padding: 15px 40px; ' +
        'margin: 20px auto; ' +
        'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); ' +
        'color: white; ' +
        'border: none; ' +
        'border-radius: 30px; ' +
        'font-size: 16px; ' +
        'font-weight: 600; ' +
        'cursor: pointer; ' +
        'box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); ' +
        'transition: all 0.3s ease;';

      btn.addEventListener('mouseenter', function () {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 6px 20px rgba(102, 126, 234, 0.6)';
      });

      btn.addEventListener('mouseleave', function () {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 15px rgba(102, 126, 234, 0.4)';
      });

      btn.addEventListener('click', function () {
        currentPage++;
        if (currentCategory) {
          filterByCategory(currentCategory, true);
        } else {
          loadAllProducts(true);
        }
      });

      grid.appendChild(btn);
    }
  }

  // LAZY LOADING: Ürünleri render et
  function renderProducts(products, append) {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    if (!append) {
      // Mevcut kartları gizle
      var existingCards = grid.querySelectorAll('.product-card');
      Array.prototype.forEach.call(existingCards, function (card) {
        card.style.display = 'none';
      });

      // Varolan mesajları temizle
      var messages = grid.querySelectorAll('.search-result-message, .loading-indicator, .load-more-btn');
      Array.prototype.forEach.call(messages, function (msg) {
        msg.remove();
      });
    }

    if (products.length === 0 && !append) {
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

    // Sadece eşleşen kartları göster + LAZY LOADING
    var existingCards = grid.querySelectorAll('.product-card');
    var shownCount = 0;

    Array.prototype.forEach.call(existingCards, function (card) {
      var cardTitle = card.querySelector('.product-title, h3, h4')?.textContent.trim() || '';
      var cardId = card.getAttribute('data-product-id') || card.getAttribute('data-slug');

      var matchFound = products.some(function (product) {
        var productTitle = product.title || product.name || '';
        return normalize(productTitle) === normalize(cardTitle) ||
               product.id === cardId ||
               product.slug === cardId;
      });

      if (matchFound) {
        card.style.display = '';
        shownCount++;

        // LAZY LOADING UYGULA
        applyLazyLoadingToCard(card);
      }
    });

    // Hiç kart gösterilemediysе mesaj göster
    if (shownCount === 0 && products.length > 0 && !append) {
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

  // SÜPER HIZLI LAZY LOADING - Agresif Optimizasyon
  function applyLazyLoadingToCard(card) {
    if (card.hasAttribute('data-lazy-applied')) return;
    card.setAttribute('data-lazy-applied', 'true');

    var images = card.querySelectorAll('img');

    Array.prototype.forEach.call(images, function (img) {
      if (img.hasAttribute('data-lazy-loaded')) return;

      var originalSrc = img.src || img.getAttribute('data-src');
      if (!originalSrc) return;

      // Hafif gri placeholder
      img.style.backgroundColor = '#f5f5f5';
      img.style.minHeight = '200px';

      // Intersection Observer - SADECE görünür olanları yükle
      if ('IntersectionObserver' in window) {
        if (!window.__productImageObserver) {
          window.__productImageObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                var image = entry.target;
                var src = image.getAttribute('data-original-src');

                if (src) {
                  image.src = src;
                  image.style.backgroundColor = 'transparent';
                  image.setAttribute('data-lazy-loaded', 'true');
                }

                window.__productImageObserver.unobserve(image);
              }
            });
          }, {
            rootMargin: '50px',
            threshold: 0.01
          });
        }

        // Mevcut src'yi sakla ve placeholder koy
        img.setAttribute('data-original-src', originalSrc);
        img.removeAttribute('src'); // SRC'yi kaldır - yüklemeyi durdur!
        window.__productImageObserver.observe(img);
      } else {
        // Eski tarayıcılar için direkt yükle
        img.setAttribute('data-lazy-loaded', 'true');
      }
    });
  }

  // Yükleniyor göstergesi
  function showLoadingIndicator() {
    isLoading = true;
    var existing = document.querySelector('.loading-indicator');
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
    indicator.textContent = '🔍 Yükleniyor...';

    document.body.appendChild(indicator);
  }

  function hideLoadingIndicator() {
    isLoading = false;
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

      searchInput.addEventListener('input', function () {
        searchProductsFromBackend(this.value, false);
      });

      searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(searchTimeout);
          searchProductsFromBackend(this.value, false);
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

  // Kategori butonlarını dinle
  function bindCategoryButtons() {
    document.addEventListener('click', function (event) {
      var button = event.target.closest('.filter-btn, [data-category-slug], [data-category]');
      if (!button) return;

      var categorySlug =
        button.getAttribute('data-category-slug') ||
        button.getAttribute('data-category') ||
        normalize(button.textContent);

      if (categorySlug === 'tumu' || categorySlug === 'tümü' || categorySlug === 'all') {
        loadAllProducts(false);
        return;
      }

      filterByCategory(categorySlug, false);
    });
  }

  // Sayfa yüklendiğinde - ANINDA LAZY LOADING UYGULA
  document.addEventListener('DOMContentLoaded', function () {
    createSearchInput();
    updateSearchStyleOnScroll();
    bindCategoryButtons();
    loadCategories();

    // HEMEN lazy loading uygula - hiç bekleme!
    applyLazyLoadingToAllCards();

    // Dinamik içerik için observer
    if (typeof MutationObserver !== 'undefined') {
      var gridObserver = new MutationObserver(function () {
        applyLazyLoadingToAllCards();
      });

      setTimeout(function () {
        var grid = document.querySelector('.products-grid');
        if (grid) {
          gridObserver.observe(grid, {
            childList: true,
            subtree: true
          });
        }
      }, 100);
    }
  });

  // Tüm kartlara lazy loading uygula
  function applyLazyLoadingToAllCards() {
    var grid = document.querySelector('.products-grid');
    if (!grid) return;

    var cards = grid.querySelectorAll('.product-card');
    Array.prototype.forEach.call(cards, applyLazyLoadingToCard);
  }

  // Global API
  window.productSearch = {
    search: searchProductsFromBackend,
    filterByCategory: filterByCategory,
    loadAll: loadAllProducts,
    getCategories: loadCategories,
    loadMore: function () {
      currentPage++;
      if (currentCategory) {
        filterByCategory(currentCategory, true);
      } else {
        loadAllProducts(true);
      }
    }
  };
})();
