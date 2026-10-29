import localforage from 'localforage';

localforage.config({ name: 'StaritaReadingApp' });

const STORAGE_KEY = 'offline_readings';

export async function saveOffline(payload) {
    const list = (await localforage.getItem(STORAGE_KEY)) || [];
    list.push({ ...payload, synced: false, saved_at: new Date().toISOString() });
    await localforage.setItem(STORAGE_KEY, list);
    alert('Reading saved locally (offline mode).');
}

export async function syncOfflineReadings() {
    const list = (await localforage.getItem(STORAGE_KEY)) || [];
    const unsynced = list.filter(i => !i.synced);
    if (!navigator.onLine || !unsynced.length) return;

    for (const item of unsynced) {
        try {
            await $.ajax({
                url: '/admin/reading',
                method: 'POST',
                data: item,
            });
            console.log('[SYNC] Sent', item.account_no);
            item.synced = true;
        } catch (err) {
            console.warn('[SYNC FAILED]', item.account_no, err);
        }
    }

    await localforage.setItem(STORAGE_KEY, list.filter(i => !i.synced));
}

window.addEventListener('online', syncOfflineReadings);