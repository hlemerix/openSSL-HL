<?php
/**
 * sodiumEjemplo.php – Laboratorio de Libsodium (ext-sodium) en PHP
 * Cuatro operaciones: cifrado autenticado, descifrado, firma digital y hash de contraseñas.
 * Requiere extension=sodium en php.ini (incluida por defecto desde PHP 7.2).
 */

// Escapa texto antes de mostrarlo en HTML (evita XSS – OWASP)
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Pestaña activa (solo se aceptan valores conocidos)
$modo = $_GET['m'] ?? 'cifrar';
if (!in_array($modo, ['cifrar', 'descifrar', 'firmar', 'hash'], true)) { $modo = 'cifrar'; }

$sodiumOk = extension_loaded('sodium');
$accion   = $_POST['accion'] ?? '';
$res      = [];   // aquí se guardan los resultados para mostrarlos abajo

if ($sodiumOk && $_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------- 1. CIFRAR con contraseña ---------- */
    if ($accion === 'cifrar') {
        $texto = $_POST['texto'] ?? '';
        $pass  = $_POST['pass'] ?? '';

        $salt  = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);            // 16 bytes aleatorios
        $clave = sodium_crypto_pwhash(                                     // contraseña -> clave de 32 bytes (Argon2id)
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $pass, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
        $nonce   = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);       // 24 bytes, único por mensaje
        $cifrado = sodium_crypto_secretbox($texto, $nonce, $clave);        // XSalsa20 + Poly1305

        // Paquete = salt + nonce + cifrado, en base64 (fácil de copiar y guardar)
        $res = [
            'salt' => sodium_bin2hex($salt), 'nonce' => sodium_bin2hex($nonce),
            'cifrado' => sodium_bin2hex($cifrado),
            'paquete' => base64_encode($salt . $nonce . $cifrado),
        ];
        sodium_memzero($clave);
    }

    /* ---------- 2. DESCIFRAR un paquete ---------- */
    if ($accion === 'descifrar') {
        $pass    = $_POST['pass'] ?? '';
        $bin     = base64_decode(trim($_POST['paquete'] ?? ''), true);
        $alterar = isset($_POST['alterar']);
        $minimo  = SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

        if ($bin === false || strlen($bin) < $minimo) {
            $res = ['error' => 'El paquete no tiene un formato válido (base64 incompleto o dañado).'];
        } else {
            // Separar las tres partes del paquete
            $salt    = substr($bin, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $nonce   = substr($bin, SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cifrado = substr($bin, SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            if ($alterar) { $cifrado[0] = $cifrado[0] ^ "\x01"; }          // simula un atacante

            $clave = sodium_crypto_pwhash(
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $pass, $salt,
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
            );
            $claro = sodium_crypto_secretbox_open($cifrado, $nonce, $clave); // false si hubo alteración o clave mala
            $res = ['claro' => $claro, 'alterado' => $alterar];
            sodium_memzero($clave);
        }
    }

    /* ---------- 3a. FIRMAR (Ed25519) ---------- */
    if ($accion === 'firmar') {
        $mensaje = $_POST['mensaje'] ?? '';
        $par     = sodium_crypto_sign_keypair();                           // par de llaves nuevo
        $sk      = sodium_crypto_sign_secretkey($par);
        $pk      = sodium_crypto_sign_publickey($par);
        $firma   = sodium_crypto_sign_detached($mensaje, $sk);             // firma de 64 bytes
        $res = ['pk' => base64_encode($pk), 'firma' => base64_encode($firma), 'mensaje' => $mensaje];
        sodium_memzero($sk);                                               // la privada no se muestra ni se guarda
    }

    /* ---------- 3b. VERIFICAR firma ---------- */
    if ($accion === 'verificar') {
        $mensaje = $_POST['mensaje'] ?? '';
        $firma   = base64_decode(trim($_POST['firma'] ?? ''), true);
        $pk      = base64_decode(trim($_POST['pk'] ?? ''), true);
        if ($firma === false || $pk === false
            || strlen($firma) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $res = ['error' => 'Firma o clave pública con formato inválido.'];
        } else {
            $res = ['valida' => sodium_crypto_sign_verify_detached($firma, $mensaje, $pk)];
        }
    }

    /* ---------- 4a. HASH de contraseña (Argon2id) ---------- */
    if ($accion === 'hashear') {
        $pass = $_POST['pass'] ?? '';
        $res  = ['hash' => sodium_crypto_pwhash_str(
            $pass, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE)];
    }

    /* ---------- 4b. VERIFICAR hash ---------- */
    if ($accion === 'verificar_hash') {
        $res = ['coincide' => sodium_crypto_pwhash_str_verify(trim($_POST['hash'] ?? ''), $_POST['pass'] ?? '')];
    }
}

// Valores para rellenar formularios (arrastrados desde el resultado anterior)
$prePaquete = $_POST['paquete'] ?? '';
$preMsg = $_POST['mensaje'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Libsodium – Laboratorio</title>
<style>
 :root{--p:#4b1d6b;--g:#1e7a34;--bg:#f4f1f7;--ok:#0a7a2a;--bad:#b00020}
 *{box-sizing:border-box}
 body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:var(--bg);color:#222}
 header{background:linear-gradient(120deg,var(--p),var(--g));color:#fff;padding:28px 16px;text-align:center}
 header h1{margin:0;font-size:1.8rem} header p{margin:6px 0 0;opacity:.9}
 main{max-width:860px;margin:-18px auto 40px;padding:0 14px}
 nav{display:flex;gap:8px;flex-wrap:wrap;background:#fff;padding:10px;border-radius:12px;box-shadow:0 2px 8px #0002}
 nav a{flex:1;min-width:120px;text-align:center;padding:10px;border-radius:8px;text-decoration:none;color:var(--p);font-weight:600}
 nav a.on{background:var(--p);color:#fff} nav a:hover:not(.on){background:#ece4f3}
 .card{background:#fff;border-radius:12px;padding:20px;margin-top:16px;box-shadow:0 2px 8px #0002}
 .card h2{margin-top:0;color:var(--p)} .hint{color:#555;font-size:.92rem}
 label{display:block;margin:12px 0 4px;font-weight:600}
 input[type=text],input[type=password],textarea{width:100%;padding:10px;border:1px solid #bbb;border-radius:8px;font:inherit}
 textarea{font-family:Consolas,monospace;font-size:.85rem}
 button{margin-top:14px;padding:10px 22px;border:0;border-radius:8px;background:var(--g);color:#fff;font-size:1rem;cursor:pointer}
 button.sec{background:var(--p)} button:hover{filter:brightness(1.12)}
 pre{background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;font-size:.82rem}
 .badge{display:inline-block;padding:8px 14px;border-radius:20px;font-weight:700;margin-top:10px}
 .ok{background:#e1f6e6;color:var(--ok)} .bad{background:#fde3e7;color:var(--bad)}
 .chk{display:flex;align-items:center;gap:8px;margin-top:12px;font-weight:400} .chk input{width:auto}
 h4{margin:14px 0 4px;color:#333}
</style>
</head>
<body>
<header><h1>🔐 Libsodium en PHP</h1><p>Cifrado autenticado · Firma digital · Hash de contraseñas</p></header>
<main>
<nav>
 <a href="?m=cifrar"    class="<?= $modo==='cifrar'?'on':'' ?>">🔒 Cifrar</a>
 <a href="?m=descifrar" class="<?= $modo==='descifrar'?'on':'' ?>">🔓 Descifrar</a>
 <a href="?m=firmar"    class="<?= $modo==='firmar'?'on':'' ?>">✍️ Firma digital</a>
 <a href="?m=hash"      class="<?= $modo==='hash'?'on':'' ?>">🧂 Hash</a>
</nav>

<?php if (!$sodiumOk): ?>
 <div class="card"><span class="badge bad">La extensión sodium no está habilitada. Active extension=sodium en php.ini y reinicie Apache.</span></div>
<?php elseif ($modo === 'cifrar'): ?>
 <div class="card">
  <h2>Cifrado autenticado con contraseña</h2>
  <p class="hint">La contraseña se convierte en clave con <b>Argon2id</b>; el texto se cifra con <b>XSalsa20-Poly1305</b> (confidencialidad + integridad).</p>
  <form method="post" action="?m=cifrar">
   <input type="hidden" name="accion" value="cifrar">
   <label>Dato sensible</label><input type="text" name="texto" required value="<?= h($_POST['texto'] ?? '') ?>">
   <label>Contraseña</label><input type="password" name="pass" required>
   <button>Cifrar</button>
  </form>
 </div>
 <?php if (isset($res['paquete'])): ?>
 <div class="card">
  <h2>Resultado</h2>
  <h4>Salt (hex)</h4><pre><?= h($res['salt']) ?></pre>
  <h4>Nonce (hex)</h4><pre><?= h($res['nonce']) ?></pre>
  <h4>Mensaje cifrado (hex)</h4><pre><?= h($res['cifrado']) ?></pre>
  <h4>Paquete completo (salt + nonce + cifrado, base64)</h4><pre><?= h($res['paquete']) ?></pre>
  <form method="post" action="?m=descifrar">
   <input type="hidden" name="paquete" value="<?= h($res['paquete']) ?>">
   <button class="sec" name="accion" value="irdescifrar">Llevar a la pestaña Descifrar →</button>
  </form>
 </div>
 <?php endif; ?>

<?php elseif ($modo === 'descifrar'): ?>
 <div class="card">
  <h2>Descifrar un paquete</h2>
  <p class="hint">Pegue el paquete generado en "Cifrar". Marque la casilla para simular que un atacante altera 1 byte: Sodium lo detectará.</p>
  <form method="post" action="?m=descifrar">
   <input type="hidden" name="accion" value="descifrar">
   <label>Paquete (base64)</label><textarea name="paquete" rows="4" required><?= h($prePaquete) ?></textarea>
   <label>Contraseña</label><input type="password" name="pass" required>
   <label class="chk"><input type="checkbox" name="alterar" <?= isset($_POST['alterar'])?'checked':'' ?>> Simular alteración del mensaje cifrado</label>
   <button>Descifrar</button>
  </form>
 </div>
 <?php if ($accion === 'descifrar'): ?>
 <div class="card"><h2>Resultado</h2>
  <?php if (isset($res['error'])): ?><span class="badge bad">⚠ <?= h($res['error']) ?></span>
  <?php elseif ($res['claro'] === false): ?><span class="badge bad">❌ Rechazado: <?= $res['alterado'] ? 'el mensaje fue alterado' : 'contraseña incorrecta o mensaje alterado' ?></span>
  <?php else: ?><span class="badge ok">✅ Descifrado correcto</span><pre><?= h($res['claro']) ?></pre><?php endif; ?>
 </div>
 <?php endif; ?>

<?php elseif ($modo === 'firmar'): ?>
 <div class="card">
  <h2>Firma digital Ed25519</h2>
  <p class="hint">Se genera un par de llaves nuevo, se firma el mensaje con la privada y se entrega la pública para verificar.</p>
  <form method="post" action="?m=firmar">
   <input type="hidden" name="accion" value="firmar">
   <label>Mensaje a firmar</label><textarea name="mensaje" rows="3" required><?= h($preMsg ?: 'Este es un mensaje confidencial') ?></textarea>
   <button>Firmar</button>
  </form>
 </div>
 <?php if (isset($res['firma'])): ?>
 <div class="card"><h2>Firma generada</h2>
  <h4>Clave pública (base64)</h4><pre><?= h($res['pk']) ?></pre>
  <h4>Firma (base64)</h4><pre><?= h($res['firma']) ?></pre>
 </div>
 <?php endif; ?>
 <div class="card">
  <h2>Verificar firma</h2>
  <p class="hint">Pruebe cambiando una letra del mensaje: la verificación debe fallar.</p>
  <form method="post" action="?m=firmar">
   <input type="hidden" name="accion" value="verificar">
   <label>Mensaje</label><textarea name="mensaje" rows="2" required><?= h(isset($res['mensaje']) ? $res['mensaje'] : $preMsg) ?></textarea>
   <label>Firma (base64)</label><textarea name="firma" rows="2" required><?= h($res['firma'] ?? ($_POST['firma'] ?? '')) ?></textarea>
   <label>Clave pública (base64)</label><textarea name="pk" rows="2" required><?= h($res['pk'] ?? ($_POST['pk'] ?? '')) ?></textarea>
   <button class="sec">Verificar</button>
  </form>
  <?php if ($accion === 'verificar'): ?>
   <?php if (isset($res['error'])): ?><span class="badge bad">⚠ <?= h($res['error']) ?></span>
   <?php elseif ($res['valida']): ?><span class="badge ok">✅ Firma válida: el mensaje no fue alterado</span>
   <?php else: ?><span class="badge bad">❌ Firma inválida: el mensaje o la firma fueron modificados</span><?php endif; ?>
  <?php endif; ?>
 </div>

<?php else: /* hash */ ?>
 <div class="card">
  <h2>Hash de contraseñas con Argon2id</h2>
  <p class="hint">Cada hash incluye su propio salt: la misma contraseña genera hashes distintos.</p>
  <form method="post" action="?m=hash">
   <input type="hidden" name="accion" value="hashear">
   <label>Contraseña</label><input type="password" name="pass" required>
   <button>Generar hash</button>
  </form>
  <?php if (isset($res['hash'])): ?><h4>Hash (guárdelo en la base de datos)</h4><pre><?= h($res['hash']) ?></pre><?php endif; ?>
 </div>
 <div class="card">
  <h2>Verificar contraseña contra un hash</h2>
  <form method="post" action="?m=hash">
   <input type="hidden" name="accion" value="verificar_hash">
   <label>Hash</label><textarea name="hash" rows="2" required><?= h($res['hash'] ?? ($_POST['hash'] ?? '')) ?></textarea>
   <label>Contraseña a probar</label><input type="password" name="pass" required>
   <button class="sec">Verificar</button>
  </form>
  <?php if ($accion === 'verificar_hash'): ?>
   <?= $res['coincide'] ? '<span class="badge ok">✅ La contraseña coincide</span>' : '<span class="badge bad">❌ La contraseña NO coincide</span>' ?>
  <?php endif; ?>
 </div>
<?php endif; ?>
</main>
</body>
</html>