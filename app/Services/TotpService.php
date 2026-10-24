<?php

namespace App\Services;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $bytes = random_bytes(20);
        $secret = $this->base32Encode($bytes);

        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = intdiv(time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->at($secret, $now + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    public function otpauthUrl(string $secret, string $account, string $issuer = 'POSMoon'): string
    {
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&period=30&digits=6';
    }

    public function at(string $secret, int $counter): string
    {
        $bin = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $time, $bin, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function recoveryCodes(int $cantidad = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    private function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $binary = str_pad($binary, (int) ceil(strlen($binary) / 5) * 5, '0');
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $binary = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
