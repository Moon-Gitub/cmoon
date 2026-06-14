async function sha256Hex(text) {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2, '0')).join('');
}

async function hmacKey(deviceToken) {
    return sha256Hex(deviceToken);
}

async function verifyLicense(license, deviceToken) {
    if (! license || ! deviceToken) return null;

    const parts = license.split('.');
    if (parts.length !== 2) return null;

    const [body, sig] = parts;
    const keyStr = await hmacKey(deviceToken);
    const key = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(keyStr),
        { name: 'HMAC', hash: 'SHA-256' },
        false,
        ['sign'],
    );
    const mac = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(body));
    const expected = [...new Uint8Array(mac)].map(b => b.toString(16).padStart(2, '0')).join('');

    if (expected !== sig) return null;

    try {
        return JSON.parse(atob(body));
    } catch {
        return null;
    }
}

function canSellNow(payload) {
    if (! payload) return false;
    if (payload.blocked || payload.can_sell === false) return false;
    if (payload.valid_until && new Date(payload.valid_until) < new Date()) return false;
    return true;
}

window.cmoonLicense = { verifyLicense, canSellNow };
