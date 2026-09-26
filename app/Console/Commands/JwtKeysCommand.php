<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class JwtKeysCommand extends Command
{
    protected $signature = 'jwt:keys';

    protected $description = 'Generate an Ed25519 key pair for signing access tokens and print the env values';

    public function handle(): int
    {
        $keyPair = sodium_crypto_sign_keypair();

        $privateKey = base64_encode(sodium_crypto_sign_secretkey($keyPair));
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keyPair));

        $this->info('Add these to your .env (never commit the private key):');
        $this->newLine();
        $this->line("JWT_PRIVATE_KEY={$privateKey}");
        $this->line("JWT_PUBLIC_KEY={$publicKey}");

        return self::SUCCESS;
    }
}
