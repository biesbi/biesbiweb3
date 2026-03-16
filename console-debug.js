// ═══════════════════════════════════════════════
//  Admin Panel Fotoğraf Yükleme Debug Script
//  Kullanım: Bu dosyanın içeriğini kopyalayın ve
//  tarayıcı console'una yapıştırıp Enter'a basın
// ═══════════════════════════════════════════════

console.log('%c🐛 DEBUG SCRIPT BAŞLATILDI', 'background: #111827; color: #38bdf8; font-size: 16px; padding: 10px; font-weight: bold;');

// adminProductState'i kontrol et
if (typeof adminProductState !== 'undefined') {
    console.log('%c✅ adminProductState bulundu', 'color: #10b981; font-weight: bold;');
    console.log('adminProductState:', adminProductState);
    console.log('pendingUploads:', adminProductState.pendingUploads);
    console.log('pendingUploads.length:', adminProductState.pendingUploads.length);
    console.log('primaryIndex:', adminProductState.primaryIndex);
    console.log('currentProductId:', adminProductState.currentProductId);
    console.log('existingImages:', adminProductState.existingImages);
} else {
    console.log('%c❌ adminProductState bulunamadı!', 'color: #ef4444; font-weight: bold;');
}

// Galeri manager'ı kontrol et
const galleryManager = document.querySelector('[data-admin-gallery-manager]');
if (galleryManager) {
    console.log('%c✅ Gallery Manager bulundu', 'color: #10b981; font-weight: bold;');
    console.log('Gallery Manager:', galleryManager);
} else {
    console.log('%c❌ Gallery Manager bulunamadı!', 'color: #ef4444; font-weight: bold;');
}

// Gallery input'u kontrol et
const galleryInput = document.querySelector('.admin-gallery-input');
if (galleryInput) {
    console.log('%c✅ Gallery Input bulundu', 'color: #10b981; font-weight: bold;');
    console.log('Gallery Input:', galleryInput);

    // Input'a change event listener ekle
    galleryInput.addEventListener('change', function(e) {
        console.log('%c📸 Fotoğraf seçildi!', 'background: #facc15; color: #111827; font-size: 14px; padding: 8px; font-weight: bold;');
        console.log('Seçilen dosyalar:', e.target.files);
        console.log('Dosya sayısı:', e.target.files.length);

        setTimeout(() => {
            console.log('pendingUploads SONRASI:', adminProductState?.pendingUploads);
        }, 100);
    });
} else {
    console.log('%c❌ Gallery Input bulunamadı!', 'color: #ef4444; font-weight: bold;');
}

// uploadQueuedImages fonksiyonunu intercept et
if (typeof uploadQueuedImages !== 'undefined') {
    console.log('%c✅ uploadQueuedImages fonksiyonu bulundu', 'color: #10b981; font-weight: bold;');

    const originalUploadQueuedImages = uploadQueuedImages;
    window.uploadQueuedImages = function(productId) {
        console.log('%c🚀 uploadQueuedImages ÇAĞRILDI!', 'background: #7c3aed; color: white; font-size: 14px; padding: 8px; font-weight: bold;');
        console.log('Product ID:', productId);
        console.log('pendingUploads:', adminProductState?.pendingUploads);
        console.log('pendingUploads.length:', adminProductState?.pendingUploads?.length);

        if (!productId) {
            console.log('%c⚠️ Product ID YOK!', 'background: #dc2626; color: white; font-size: 12px; padding: 6px;');
        }

        if (!adminProductState?.pendingUploads?.length) {
            console.log('%c⚠️ pendingUploads BOŞ!', 'background: #dc2626; color: white; font-size: 12px; padding: 6px;');
        }

        const result = originalUploadQueuedImages.call(this, productId);

        result.then((uploadedItems) => {
            console.log('%c✅ uploadQueuedImages TAMAMLANDI', 'background: #10b981; color: white; font-size: 12px; padding: 6px;');
            console.log('Yüklenen öğeler:', uploadedItems);
        }).catch((error) => {
            console.log('%c❌ uploadQueuedImages HATA', 'background: #dc2626; color: white; font-size: 12px; padding: 6px;');
            console.log('Hata:', error);
        });

        return result;
    };
} else {
    console.log('%c❌ uploadQueuedImages fonksiyonu bulunamadı!', 'color: #ef4444; font-weight: bold;');
}

// Fetch isteklerini intercept et
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const url = args[0];

    // Upload endpoint'lerini logla
    if (typeof url === 'string' && url.includes('/upload/product-image')) {
        console.log('%c📤 UPLOAD İSTEĞİ', 'background: #0ea5e9; color: white; font-size: 12px; padding: 6px; font-weight: bold;');
        console.log('URL:', url);
        console.log('Method:', args[1]?.method || 'GET');
        console.log('Headers:', args[1]?.headers);
        console.log('Body:', args[1]?.body);

        if (args[1]?.body instanceof FormData) {
            console.log('FormData içeriği:');
            for (let [key, value] of args[1].body.entries()) {
                console.log(`  ${key}:`, value);
            }
        }
    }

    return originalFetch.apply(this, args).then(response => {
        if (typeof url === 'string' && url.includes('/upload/product-image')) {
            const clonedResponse = response.clone();
            clonedResponse.json().then(data => {
                console.log('%c📥 UPLOAD RESPONSE', 'background: #10b981; color: white; font-size: 12px; padding: 6px; font-weight: bold;');
                console.log('Status:', response.status);
                console.log('Data:', data);
            }).catch(() => {
                console.log('%c❌ Response JSON parse hatası', 'background: #dc2626; color: white; font-size: 12px; padding: 6px;');
            });
        }
        return response;
    });
};

// Form submit'i intercept et
const form = document.querySelector('.admin-form, form');
if (form) {
    console.log('%c✅ Form bulundu', 'color: #10b981; font-weight: bold;');

    form.addEventListener('submit', function(e) {
        console.log('%c📝 FORM SUBMIT!', 'background: #f59e0b; color: white; font-size: 14px; padding: 8px; font-weight: bold;');
        console.log('pendingUploads:', adminProductState?.pendingUploads);
        console.log('currentProductId:', adminProductState?.currentProductId);
    }, true); // true = capture phase
} else {
    console.log('%c❌ Form bulunamadı!', 'color: #ef4444; font-weight: bold;');
}

console.log('%c✅ DEBUG SCRIPT HAZIR - Şimdi fotoğraf seçip kaydet butonuna basın!', 'background: #10b981; color: white; font-size: 14px; padding: 10px; font-weight: bold;');
console.log('%cKonsolu takip edin, tüm işlemler loglanacak.', 'color: #64748b; font-style: italic;');
