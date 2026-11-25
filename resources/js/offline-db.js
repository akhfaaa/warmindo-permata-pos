const DB_NAME = 'warmindo_permata_pos';
const DB_VERSION = 1;
const STORE_CATALOG = 'catalog_cache';
const STORE_QUEUE = 'offline_queue';

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_CATALOG)) {
                db.createObjectStore(STORE_CATALOG, { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains(STORE_QUEUE)) {
                db.createObjectStore(STORE_QUEUE, { keyPath: 'id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function generateUUIDv4() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

class OfflineManager {
    constructor() {
        this.isSyncing = false;
    }

    async saveCatalogCache(products, modifiers) {
        try {
            const db = await openDatabase();
            const tx = db.transaction(STORE_CATALOG, 'readwrite');
            const store = tx.objectStore(STORE_CATALOG);
            store.put({
                key: 'active_catalog',
                products,
                modifiers,
                updatedAt: new Date().toISOString(),
            });
        } catch (err) {
            console.warn('Gagal menyimpan cache katalog ke IndexedDB:', err);
        }
    }

    async getCatalogCache() {
        try {
            const db = await openDatabase();
            return await new Promise((resolve) => {
                const tx = db.transaction(STORE_CATALOG, 'readonly');
                const req = tx.objectStore(STORE_CATALOG).get('active_catalog');
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => resolve(null);
            });
        } catch {
            return null;
        }
    }

    async enqueueTransaction(payload, receiptPreview) {
        const db = await openDatabase();
        const record = {
            id: payload.id || generateUUIDv4(),
            payload: {
                ...payload,
                is_offline_synced: true,
                created_at: payload.created_at || new Date().toISOString(),
            },
            receiptPreview,
            queuedAt: new Date().toISOString(),
            status: 'PENDING',
            errorMessage: null,
        };

        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_QUEUE, 'readwrite');
            const req = tx.objectStore(STORE_QUEUE).put(record);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });

        await this.notifyQueueUpdated();
        return record;
    }

    async getAllQueued() {
        try {
            const db = await openDatabase();
            return await new Promise((resolve) => {
                const tx = db.transaction(STORE_QUEUE, 'readonly');
                const req = tx.objectStore(STORE_QUEUE).getAll();
                req.onsuccess = () => {
                    const items = (req.result || []).sort(
                        (a, b) => new Date(a.queuedAt) - new Date(b.queuedAt)
                    );
                    resolve(items);
                };
                req.onerror = () => resolve([]);
            });
        } catch {
            return [];
        }
    }

    async removeQueued(id) {
        const db = await openDatabase();
        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_QUEUE, 'readwrite');
            const req = tx.objectStore(STORE_QUEUE).delete(id);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    }

    async markQueueFailed(id, errorMessage) {
        const db = await openDatabase();
        const tx = db.transaction(STORE_QUEUE, 'readwrite');
        const store = tx.objectStore(STORE_QUEUE);
        const req = store.get(id);
        req.onsuccess = () => {
            if (req.result) {
                req.result.status = 'FAILED';
                req.result.errorMessage = errorMessage;
                store.put(req.result);
            }
        };
    }

    async notifyQueueUpdated() {
        const items = await this.getAllQueued();
        const pendingCount = items.filter((i) => i.status === 'PENDING').length;
        window.dispatchEvent(
            new CustomEvent('warmindo:sync-updated', {
                detail: { count: pendingCount, total: items.length, items },
            })
        );
        return pendingCount;
    }

    async syncQueue() {
        if (this.isSyncing || !navigator.onLine) {
            return { synced: 0 };
        }

        const items = await this.getAllQueued();
        const pendingItems = items.filter((i) => i.status === 'PENDING');
        if (pendingItems.length === 0) {
            return { synced: 0 };
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (!csrfMeta) return { synced: 0 };

        this.isSyncing = true;
        window.dispatchEvent(new CustomEvent('warmindo:sync-started'));

        let syncedCount = 0;

        try {
            for (const entry of pendingItems) {
                if (!navigator.onLine) break;

                try {
                    const response = await fetch('/pos/checkout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfMeta.content,
                        },
                        body: JSON.stringify(entry.payload),
                    });

                    if (response.ok) {
                        await this.removeQueued(entry.id);
                        syncedCount++;
                    } else if (response.status === 422) {
                        const errData = await response.json().catch(() => ({}));
                        await this.markQueueFailed(
                            entry.id,
                            errData.message || 'Data transaksi tidak valid.'
                        );
                    } else {
                        // Server error sementara: hentikan loop dan coba lagi nanti
                        break;
                    }
                } catch {
                    // Koneksi terputus di tengah sync: berhenti dan tunggu online kembali
                    break;
                }
            }
        } finally {
            this.isSyncing = false;
            await this.notifyQueueUpdated();
            if (syncedCount > 0) {
                window.dispatchEvent(
                    new CustomEvent('warmindo:sync-completed', {
                        detail: { syncedCount },
                    })
                );
            }
        }

        return { synced: syncedCount };
    }
}

window.WarmindoUUID = generateUUIDv4;
window.WarmindoOffline = new OfflineManager();