// Admin Panel Çoklu Görsel Düzeltmesi
// Bu dosyayı index.html'e <script> tag'i ile ekleyin

(function() {
  console.log('🔧 Çoklu görsel düzeltmesi yüklendi');

  // Form açıldığında çalışacak
  function enhanceImageInput() {
    // Tüm image input'ları bul
    var imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');

    imageInputs.forEach(function(input) {
      // Zaten düzeltilmişse atla
      if (input.dataset.multiImageEnhanced) return;
      input.dataset.multiImageEnhanced = '1';

      // Multiple özelliğini ekle
      input.multiple = true;

      console.log('✅ File input multiple yapıldı:', input);

      // Preview container oluştur
      var previewId = 'multi-image-preview-' + Date.now();
      var preview = document.createElement('div');
      preview.id = previewId;
      preview.style.cssText = `
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 15px;
        padding: 15px;
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        min-height: 50px;
      `;

      // Input'un sonrasına ekle
      var parent = input.parentElement;
      if (parent) {
        parent.appendChild(preview);

        // Info mesajı ekle
        var info = document.createElement('div');
        info.style.cssText = 'margin-top: 8px; font-size: 12px; color: #64748b;';
        info.innerHTML = '💡 <strong>Çoklu seçim yapabilirsiniz!</strong> İlk seçtiğiniz görsel kapak olacak.';
        parent.insertBefore(info, preview);
      }

      // Change event
      input.addEventListener('change', function(e) {
        var files = Array.from(e.target.files);
        console.log('📸 ' + files.length + ' görsel seçildi');

        // Preview'i temizle
        preview.innerHTML = '';

        if (files.length === 0) {
          preview.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">Görsel seçilmedi</div>';
          return;
        }

        // adminProductState'e ekle (eğer varsa)
        if (window.adminProductState) {
          window.adminProductState.pendingUploads = files.map(function(file) {
            return {
              file: file,
              url: URL.createObjectURL(file)
            };
          });
          window.adminProductState.primaryIndex = 0;
          console.log('✅ adminProductState güncellendi');
        }

        // Her dosya için preview oluştur
        files.forEach(function(file, index) {
          var container = document.createElement('div');
          container.style.cssText = `
            position: relative;
            border: 2px solid ${index === 0 ? '#fbbf24' : '#e2e8f0'};
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1;
            box-shadow: ${index === 0 ? '0 0 10px rgba(251, 191, 36, 0.5)' : 'none'};
          `;

          var img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';

          var label = document.createElement('div');
          label.textContent = index === 0 ? '⭐ Kapak' : (index + 1);
          label.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
          `;

          container.appendChild(img);
          container.appendChild(label);
          preview.appendChild(container);
        });
      });
    });
  }

  // Sayfa yüklendiğinde
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceImageInput);
  } else {
    enhanceImageInput();
  }

  // Mutation observer - yeni formlar eklendiğinde
  var observer = new MutationObserver(function(mutations) {
    enhanceImageInput();
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });

  // Her 2 saniyede bir kontrol et (fallback)
  setInterval(enhanceImageInput, 2000);

  console.log('✅ Çoklu görsel sistemi aktif');
})();
