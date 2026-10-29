import localforage from 'localforage'
import axios from 'axios'

localforage.config({ name: 'NovustreamReader' })

const PENDING_KEY = 'pending_readings'

export async function saveReadingOffline(reading) {
    const pending = (await localforage.getItem(PENDING_KEY)) || []
    pending.push({ ...reading, synced: false, saved_at: new Date().toISOString() })
    await localforage.setItem(PENDING_KEY, pending)
    console.log('[Offline] Saved reading locally', reading.reference_no)
}

export async function getPendingReadings() {
    return (await localforage.getItem(PENDING_KEY)) || []
}

export async function syncReadings() {
    const list = await getPendingReadings()
    const unsynced = list.filter(r => !r.synced)
    if (!navigator.onLine || !unsynced.length) return

    try {
        const res = await axios.post('/api/readings/sync', { readings: unsynced })
        if (res.data.status === 'synced') {
            await localforage.setItem(PENDING_KEY, [])
            console.log('[Offline] Synced all pending readings.')
        }
    } catch (e) {
        console.warn('[Offline] Sync failed', e)
    }
}

// auto-sync when online
window.addEventListener('online', syncReadings)