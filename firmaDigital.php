<?PHP
//firmaDigital.php
$opensslConfig = getenv('OPENSSL_CONF');
$keyOptions = array(
        'private_key_bits' => 2048,      // Tamaño de la llave
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    );
if ($opensslConfig !== false && $opensslConfig !== '') {
    $keyOptions['config'] = $opensslConfig;
}

$privateKey = openssl_pkey_new($keyOptions);
if ($privateKey === false) {
    die('No se pudo generar el par de claves: ' . openssl_error_string());
}

    // Guardar la llave privada en el archivo private.key. No compartir este archivo con nadie
    if (!openssl_pkey_export_to_file($privateKey, 'private.key', null, $keyOptions)) {
        die('No se pudo guardar la clave privada: ' . openssl_error_string());
    }

    // Generar la llave pública para la llave privada
    $a_key = openssl_pkey_get_details($privateKey);

    // Guardar la llave pública en un archivo public.key.
    // Envía este archivo a cualquiera que quiera enviarte datos encriptados
    if (file_put_contents('public.key', $a_key['key']) === false) {
        die('No se pudo guardar la clave pública.');
    }

    // Libera la llave privada
    // openssl_free_key($privateKey);   // COMENTADO: obsoleta desde PHP 8.0, el objeto se libera automáticamente

    // AGREGADO: mensaje de resultado y muestra de la llave pública
    echo "✅ Llaves generadas: private.key y public.key<br>";
    echo "<pre>" . htmlspecialchars($a_key['key']) . "</pre>";
	
	?>