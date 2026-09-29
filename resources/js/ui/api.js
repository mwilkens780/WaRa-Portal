/**
 * Einheitlicher JSON-Request fuer alle Seiten.
 *
 *   const res = await api('/trainer/hall/bookings', { method: 'POST', body: {...} });
 *   if (!res.ok) showError(res.message);
 *
 * Wirft nie. Liefert { ok, status, data, message } mit lesbarer Meldung fuer
 * 422 (Validierung), 419 (Sitzung abgelaufen), 403, 409 und Netzfehler.
 * CSRF-Token und Header setzt der Helfer selbst.
 */
export async function api(url, { method = 'GET', body = null, headers = {} } = {}) {
    try {
        const r = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
                ...headers,
            },
            body: body == null ? null : body instanceof FormData ? body : JSON.stringify(body),
        });
        const data = await r.json().catch(() => ({}));
        let message = '';
        if (!r.ok) {
            message =
                r.status === 422 ? Object.values(data.errors ?? {}).flat().join(' ') || data.message
                : r.status === 419 ? 'Deine Sitzung ist abgelaufen. Bitte Seite neu laden und erneut anmelden.'
                : r.status === 403 ? 'Dafür fehlt dir die Berechtigung.'
                : data.message || `Aktion fehlgeschlagen (Fehler ${r.status}).`;
        }
        return { ok: r.ok, status: r.status, data, message };
    } catch (e) {
        return { ok: false, status: 0, data: {}, message: 'Keine Verbindung zum Server. Bitte erneut versuchen.' };
    }
}

window.api = api;
