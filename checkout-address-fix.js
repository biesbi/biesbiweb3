// Sipariş Formu Adres Düzeltmeleri
// Mahalle ve Sokak alanlarını her zaman manuel giriş olarak ayarlar
(function () {
  'use strict';

  // Form hazır olduğunda çalıştır
  function fixAddressFields() {
    var forms = document.querySelectorAll('form, [data-checkout-form]');
    if (!forms.length) {
      return;
    }

    Array.prototype.forEach.call(forms, function (form) {
      // Mahalle alanını düzelt
      var neighborhoodField = form.querySelector('#checkout-neighborhood, input[name="neighborhood"]');
      if (neighborhoodField) {
        // Eğer select ise input'a çevir
        if (neighborhoodField.tagName === 'SELECT') {
          var newInput = document.createElement('input');
          newInput.type = 'text';
          newInput.name = neighborhoodField.name || 'neighborhood';
          newInput.id = neighborhoodField.id || 'checkout-neighborhood';
          newInput.className = neighborhoodField.className;
          newInput.placeholder = 'Mahalle adını giriniz';
          newInput.required = true;
          newInput.disabled = false;

          // Mevcut değeri koru
          if (neighborhoodField.value) {
            newInput.value = neighborhoodField.value;
          }

          // data-* attribute'larını kopyala
          Array.prototype.forEach.call(neighborhoodField.attributes, function (attr) {
            if (attr.name.startsWith('data-')) {
              newInput.setAttribute(attr.name, attr.value);
            }
          });

          neighborhoodField.parentNode.replaceChild(newInput, neighborhoodField);
          neighborhoodField = newInput;
        }

        // Her durumda aktif ve manuel giriş yap
        neighborhoodField.disabled = false;
        neighborhoodField.required = true;
        neighborhoodField.type = 'text';
        neighborhoodField.placeholder = 'Mahalle adını giriniz';
        neighborhoodField.removeAttribute('list'); // Autocomplete listesini kaldır
        neighborhoodField.removeAttribute('readonly');
      }

      // Sokak alanını düzelt
      var streetField = form.querySelector('input[name="street"]');
      if (streetField) {
        streetField.disabled = false;
        streetField.required = true;
        streetField.placeholder = 'Sokak / Cadde adını giriniz';
        streetField.removeAttribute('readonly');
      }

      // İlçe seçimi değiştiğinde alanları kilitlemeyi engelle
      var districtSelect = form.querySelector('#checkout-district, select[name="district"]');
      if (districtSelect) {
        // Mevcut event listener'ları override et
        var newDistrictSelect = districtSelect.cloneNode(true);
        districtSelect.parentNode.replaceChild(newDistrictSelect, districtSelect);

        newDistrictSelect.addEventListener('change', function () {
          // İlçe değişse bile mahalle ve sokak alanlarını aktif tut
          if (neighborhoodField) {
            neighborhoodField.disabled = false;
          }
          if (streetField) {
            streetField.disabled = false;
          }
        });
      }
    });
  }

  // Form alanlarını sürekli kontrol et ve düzelt
  function keepFieldsActive() {
    var forms = document.querySelectorAll('form, [data-checkout-form]');

    Array.prototype.forEach.call(forms, function (form) {
      var neighborhood = form.querySelector('#checkout-neighborhood, input[name="neighborhood"]');
      var street = form.querySelector('input[name="street"]');

      if (neighborhood && neighborhood.disabled) {
        neighborhood.disabled = false;
      }
      if (street && street.disabled) {
        street.disabled = false;
      }
    });
  }

  // Sayfa yüklendiğinde
  document.addEventListener('DOMContentLoaded', function () {
    fixAddressFields();

    // Her 500ms'de bir kontrol et (aggressive fix)
    var intervalId = setInterval(keepFieldsActive, 500);

    // 30 saniye sonra interval'i durdur
    setTimeout(function () {
      clearInterval(intervalId);
    }, 30000);
  });

  // MutationObserver ile DOM değişikliklerini izle
  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function (mutations) {
      var shouldFix = false;

      mutations.forEach(function (mutation) {
        if (mutation.addedNodes.length || mutation.attributeName === 'disabled') {
          shouldFix = true;
        }
      });

      if (shouldFix) {
        fixAddressFields();
      }
    });

    // Body'yi izle
    if (document.body) {
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'readonly']
      });
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        observer.observe(document.body, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ['disabled', 'readonly']
        });
      });
    }
  }

  // Global fonksiyon olarak export et
  window.fixCheckoutAddress = {
    fix: fixAddressFields,
    keepActive: keepFieldsActive
  };

  console.log('✅ Adres alanları düzeltmesi aktif - Mahalle ve Sokak her zaman manuel girilebilir');
})();
