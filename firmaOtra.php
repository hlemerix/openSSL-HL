<?php
//✅ ¿Qué puedes hacer con ese certificado?
/*certificados X.509 están orientados a HTTPS o servidores web,
pero también se usan en otras aplicaciones, como la firma y
verificación de archivos o datos.
Ese bloque que te generó es un certificado X.509 en formato PEM, 
y todo indica que la creación y firma del certificado fue exitosa
*/

define("OPEN_SSL_CERT_DAYS_VALID", 365);
$config = getenv('OPENSSL_CONF');
$configArgs = array(
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA
);
if ($config !== false && $config !== '') {
    $configArgs['config'] = $config;
}

$dn = array (
    "countryName" => "PA",
    "localityName" => "PA-8",
    "organizationName" => "MEF",
    "commonName" => "Irina Fong",
    "emailAddress" => "example@example.invalid"
);

$resourceNewKeyPair = openssl_pkey_new($configArgs);
if (!$resourceNewKeyPair) {
    echo "Error al generar la clave: " . openssl_error_string();
    exit;
}

$details = openssl_pkey_get_details($resourceNewKeyPair);
$publicKeyPem = $details['key'];

if (!openssl_pkey_export($resourceNewKeyPair, $privkey, null, $configArgs)) {
    echo "Error al exportar la clave privada: " . openssl_error_string();
    exit;
}

// ✅ Corrección importante: pasar el recurso, no el string
$csr = openssl_csr_new($dn, $resourceNewKeyPair, $configArgs);
if (!$csr) {
    echo "Error al crear la CSR: " . openssl_error_string();
    exit;
}

$sscert = openssl_csr_sign($csr, null, $resourceNewKeyPair, OPEN_SSL_CERT_DAYS_VALID, $configArgs);
if (!$sscert) {
    echo "Error al firmar la CSR: " . openssl_error_string();
    exit;
}

openssl_x509_export($sscert, $certout);
echo $certout;

if (!is_dir('keysCert')) {          // AGREGADO
    mkdir('keysCert', 0775, true);  // AGREGADO
}                                   // AGREGADO

file_put_contents('keysCert/certout.csr', $certout);
file_put_contents('keysCert/privkey.pem', $privkey);


?>