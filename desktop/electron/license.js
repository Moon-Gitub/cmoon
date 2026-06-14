const crypto = require('crypto');

function hmacKey(deviceToken) {
    return crypto.createHash('sha256').update(deviceToken).digest('hex');
}

function verifyLicense(license, deviceToken) {
    if (! license || ! deviceToken) return null;

    const parts = license.split('.');
    if (parts.length !== 2) return null;

    const [body, sig] = parts;
    const expected = crypto.createHmac('sha256', hmacKey(deviceToken)).update(body).digest('hex');

    if (expected !== sig) return null;

    try {
        return JSON.parse(Buffer.from(body, 'base64').toString('utf8'));
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

module.exports = { verifyLicense, canSellNow };
