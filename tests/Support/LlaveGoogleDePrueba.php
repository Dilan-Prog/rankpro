<?php

namespace Tests\Support;

/** Par RSA generado en el momento: ninguna llave real de Google toca los tests. */
class LlaveGoogleDePrueba
{
    public const CORREO = 'qa-gsc@qa-proyecto.iam.gserviceaccount.com';

    private static ?array $par = null;

    /** @return array{privada: string, publica: string} */
    public static function par(): array
    {
        if (self::$par === null) {
            $opciones = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
            $recurso = @openssl_pkey_new($opciones);
            if ($recurso === false) {
                // PHP en Windows suele no encontrar openssl.cnf: se le da uno mínimo.
                $cnf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rankpro-gsc-openssl.cnf';
                file_put_contents($cnf, "[req]
distinguished_name=dn
[dn]
");
                $opciones['config'] = $cnf;
                $recurso = openssl_pkey_new($opciones);
            }
            openssl_pkey_export($recurso, $privada, null, array_intersect_key($opciones, ['config' => 1]));
            self::$par = ['privada' => $privada, 'publica' => openssl_pkey_get_details($recurso)['key']];
        }

        return self::$par;
    }

    /** JSON con la forma del que descarga Google Cloud. */
    public static function json(array $sobrescribir = []): string
    {
        return json_encode(array_merge([
            'type' => 'service_account',
            'project_id' => 'qa-proyecto',
            'private_key_id' => 'abc123',
            'private_key' => self::par()['privada'],
            'client_email' => self::CORREO,
            'client_id' => '1234567890',
        ], $sobrescribir));
    }
}
