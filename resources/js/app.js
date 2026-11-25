import './bootstrap';
import './offline-db';
import './thermal-printer';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Inisialisasi listener sinkronisasi antrean offline secara global
window.addEventListener('DOMContentLoaded', () => {
    if (window.WarmindoOffline) {
        window.WarmindoOffline.notifyQueueUpdated();
        if (navigator.onLine) {
            window.WarmindoOffline.syncQueue();
        }
    }
});

window.addEventListener('online', () => {
    if (window.WarmindoOffline) {
        window.WarmindoOffline.syncQueue();
    }
});

// Auto-retry sync berkala setiap 20 detik saat online
setInterval(() => {
    if (navigator.onLine && window.WarmindoOffline) {
        window.WarmindoOffline.syncQueue();
    }
}, 20000);