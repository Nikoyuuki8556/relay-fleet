<?php

namespace App\Services;

/**
 * 6.2節「見落としやすい点」: Discord Interactions EndpointはHTTP POSTごとに
 * X-Signature-Ed25519 と X-Signature-Timestamp ヘッダーを検証する必要がある。
 * PHP 8.3系はビルドにlibsodiumが含まれるのが通常のため sodium_crypto_sign_verify_detached() を使う。
 * 確度: 高（PHP公式マニュアル記載の標準的な実装方法）。ただし対象サーバのPHPビルドに
 * libsodium拡張が含まれているかは`php -m | grep sodium`で必ず事前確認すること。
 */
class DiscordSignatureVerifier
{
    public function verify(string $signature, string $timestamp, string $rawBody, string $publicKeyHex): bool
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        try {
            $sig = sodium_hex2bin($signature);
            $publicKey = sodium_hex2bin($publicKeyHex);
        } catch (\SodiumException) {
            return false;
        }

        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $timestamp.$rawBody, $publicKey);
    }
}
