<?php
/**
 * Encriptacion.php – Cifrado simétrico con OpenSSL (openssl_encrypt / openssl_decrypt)
 * Pestañas: Cifrar y Descifrar. Algoritmos: AES-128-CBC, AES-256-CBC y AES-256-GCM.
 */

// Escapa texto antes de mostrarlo en HTML (evita XSS – OWASP)
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Algoritmos permitidos: longitud de clave en caracteres y si autentican (GCM)
$ALGOS = [
    'AES-128-CBC' => ['clave' => 16, 'gcm' => false, 'desc' => 'Clásico, 128 bits. Cifra pero NO detecta alteraciones.'],
    'AES-256-CBC' => ['clave' => 32, 'gcm' => false, 'desc' => 'Clave de 256 bits. Cifra pero NO detecta alteraciones.'],
    'AES-256-GCM' => ['clave' => 32, 'gcm' => true,  'desc' => 'Cifrado autenticado: cifra Y detecta alteraciones.'],
];

$modo   = ($_GET['m'] ?? 'cifrar') === 'descifrar' ? 'descifrar' : 'cifrar';
$accion = $_POST['accion'] ?? '';
$res    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---------- CIFRAR ---------- */
    if ($accion === 'cifrar') {
        $algo  = $_POST['algo'] ?? 'AES-128-CBC';
        $texto = $_POST['texto'] ?? '';
        $clave = $_POST['clave'] ?? '';

        if (!isset($ALGOS[$algo])) {
            $res = ['error' => 'Algoritmo no permitido.'];
        } elseif (strlen($clave) !== $ALGOS[$algo]['clave']) {
            $res = ['error' => "La clave de $algo debe tener exactamente {$ALGOS[$algo]['clave']} caracteres (tiene " . strlen($clave) . ")."];
        } else {
            // IV aleatorio: hace que el mismo texto cifre distinto cada vez
            $iv  = openssl_random_pseudo_bytes(openssl_cipher_iv_length($algo));
            $tag = '';
            if ($ALGOS[$algo]['gcm']) {
                $cifrado = openssl_encrypt($texto, $algo, $clave, OPENSSL_RAW_DATA, $iv, $tag); // $tag = etiqueta de autenticación
            } else {
                $cifrado = openssl_encrypt($texto, $algo, $clave, OPENSSL_RAW_DATA, $iv);
            }
            // Paquete = "algoritmo:" + base64(iv + tag + cifrado)
            $res = [
                'algo' => $algo, 'iv' => bin2hex($iv), 'tag' => bin2hex($tag),
                'cifrado' => base64_encode($cifrado),
                'paquete' => $algo . ':' . base64_encode($iv . $tag . $cifrado),
            ];
        }
    }

    /* ---------- DESCIFRAR ---------- */
    if ($accion === 'descifrar') {
        $clave   = $_POST['clave'] ?? '';
        $alterar = isset($_POST['alterar']);
        $partes  = explode(':', trim($_POST['paquete'] ?? ''), 2);
        $algo    = $partes[0];
        $bin     = isset($partes[1]) ? base64_decode($partes[1], true) : false;

        if (!isset($ALGOS[$algo]) || $bin === false) {
            $res = ['error' => 'Paquete inválido. Debe tener la forma ALGORITMO:base64 (genérelo en la pestaña Cifrar).'];
        } elseif (strlen($clave) !== $ALGOS[$algo]['clave']) {
            $res = ['error' => "La clave de $algo debe tener exactamente {$ALGOS[$algo]['clave']} caracteres."];
        } else {
            $ivLen  = openssl_cipher_iv_length($algo);
            $tagLen = $ALGOS[$algo]['gcm'] ? 16 : 0;
            if (strlen($bin) < $ivLen + $tagLen + 1) {
                $res = ['error' => 'El paquete está incompleto.'];
            } else {
                // Separar las partes del paquete
                $iv      = substr($bin, 0, $ivLen);
                $tag     = substr($bin, $ivLen, $tagLen);
                $cifrado = substr($bin, $ivLen + $tagLen);

                if ($alterar) { $iv[0] = $iv[0] ^ "\x01"; }               // simula un atacante: cambia 1 bit del IV

                if ($ALGOS[$algo]['gcm']) {
                    $claro = openssl_decrypt($cifrado, $algo, $clave, OPENSSL_RAW_DATA, $iv, $tag);
                } else {
                    $claro = openssl_decrypt($cifrado, $algo, $clave, OPENSSL_RAW_DATA, $iv);
                }
                $res = ['claro' => $claro, 'algo' => $algo, 'alterado' => $alterar, 'gcm' => $ALGOS[$algo]['gcm']];
            }
        }
    }
}
$algoSel = $_POST['algo'] ?? 'AES-128-CBC';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Encriptación con OpenSSL</title>
<style>
 :root{--p:#4b1d6b;--g:#1e7a34;--bg:#f4f1f7;--ok:#0a7a2a;--bad:#b00020}
 *{box-sizing:border-box}
 body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:var(--bg);color:#222}
 header{background:linear-gradient(120deg,var(--p),var(--g));color:#fff;padding:28px 16px;text-align:center}
 header h1{margin:0;font-size:1.8rem} header p{margin:6px 0 0;opacity:.9}
 main{max-width:820px;margin:-18px auto 40px;padding:0 14px}
 nav{display:flex;gap:8px;background:#fff;padding:10px;border-radius:12px;box-shadow:0 2px 8px #0002}
 nav a{flex:1;text-align:center;padding:10px;border-radius:8px;text-decoration:none;color:var(--p);font-weight:600}
 nav a.on{background:var(--p);color:#fff} nav a:hover:not(.on){background:#ece4f3}
 .card{background:#fff;border-radius:12px;padding:20px;margin-top:16px;box-shadow:0 2px 8px #0002}
 .card h2{margin-top:0;color:var(--p)} .hint{color:#555;font-size:.92rem}
 label{display:block;margin:12px 0 4px;font-weight:600}
 input[type=text],textarea,select{width:100%;padding:10px;border:1px solid #bbb;border-radius:8px;font:inherit;background:#fff}
 textarea{font-family:Consolas,monospace;font-size:.85rem}
 .row{display:flex;gap:8px} .row input{flex:1}
 button{margin-top:14px;padding:10px 22px;border:0;border-radius:8px;background:var(--g);color:#fff;font-size:1rem;cursor:pointer}
 button.sec{background:var(--p)} button.mini{margin:0;padding:0 14px;background:#6b6b6b;font-size:.85rem} button:hover{filter:brightness(1.12)}
 pre{background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;overflow-x:auto;white-space:pre-wrap;word-break:break-all;font-size:.82rem}
 .badge{display:inline-block;padding:8px 14px;border-radius:20px;font-weight:700;margin-top:10px}
 .ok{background:#e1f6e6;color:var(--ok)} .bad{background:#fde3e7;color:var(--bad)} .warn{background:#fff3d6;color:#8a5a00}
 .chk{display:flex;align-items:center;gap:8px;font-weight:400} .chk input{width:auto}
 h4{margin:14px 0 4px;color:#333} small{color:#666}
</style>
</head>
<body>
<header><h1>🔐 Encriptación con OpenSSL</h1><p>openssl_encrypt() · openssl_decrypt() · AES en modo CBC y GCM</p></header>
<main>
<nav>
 <a href="?m=cifrar"    class="<?= $modo==='cifrar'?'on':'' ?>">🔒 Cifrar</a>
 <a href="?m=descifrar" class="<?= $modo==='descifrar'?'on':'' ?>">🔓 Descifrar</a>
</nav>

<?php if (!extension_loaded('openssl')): ?>
 <div class="card"><span class="badge bad">La extensión openssl no está habilitada en php.ini.</span></div>

<?php elseif ($modo === 'cifrar'): ?>
 <div class="card">
  <h2>Cifrar un texto</h2>
  <form method="post" action="?m=cifrar">
   <input type="hidden" name="accion" value="cifrar">
   <label>Algoritmo</label>
   <select name="algo" id="algo">
    <?php foreach ($ALGOS as $nombre => $d): ?>
     <option value="<?= h($nombre) ?>" data-len="<?= $d['clave'] ?>" data-desc="<?= h($d['desc']) ?>" <?= $algoSel===$nombre?'selected':'' ?>><?= h($nombre) ?></option>
    <?php endforeach; ?>
   </select>
   <small id="desc"></small>
   <label>Texto a cifrar</label>
   <textarea name="texto" rows="3" required><?= h($_POST['texto'] ?? '') ?></textarea>
   <label>Clave <small id="lenInfo"></small></label>
   <div class="row">
    <input type="text" name="clave" id="clave" value="<?= h($_POST['clave'] ?? '') ?>" required autocomplete="off">
    <button type="button" class="mini" id="gen">🎲 Generar</button>
   </div>
   <button>Cifrar</button>
  </form>
 </div>
 <?php if (isset($res['error'])): ?>
  <div class="card"><span class="badge bad">⚠ <?= h($res['error']) ?></span></div>
 <?php elseif (isset($res['paquete'])): ?>
  <div class="card">
   <h2>Resultado</h2>
   <h4>Algoritmo</h4><pre><?= h($res['algo']) ?></pre>
   <h4>IV (hex) <small>– valor aleatorio, distinto en cada cifrado</small></h4><pre><?= h($res['iv']) ?></pre>
   <?php if ($res['tag'] !== ''): ?><h4>Etiqueta de autenticación GCM (hex)</h4><pre><?= h($res['tag']) ?></pre><?php endif; ?>
   <h4>Texto cifrado (base64)</h4><pre><?= h($res['cifrado']) ?></pre>
   <h4>Paquete completo <small>– cópielo para la pestaña Descifrar</small></h4><pre id="paq"><?= h($res['paquete']) ?></pre>
   <form method="post" action="?m=descifrar">
    <input type="hidden" name="paquete" value="<?= h($res['paquete']) ?>">
    <button class="sec" type="submit" name="accion" value="ir">Llevar a Descifrar →</button>
   </form>
  </div>
 <?php endif; ?>

<?php else: ?>
 <div class="card">
  <h2>Descifrar un paquete</h2>
  <p class="hint">Marque la casilla para simular que un atacante altera 1 bit del IV. Compare qué pasa con CBC y con GCM.</p>
  <form method="post" action="?m=descifrar">
   <input type="hidden" name="accion" value="descifrar">
   <label>Paquete (ALGORITMO:base64)</label>
   <textarea name="paquete" rows="4" required><?= h($_POST['paquete'] ?? '') ?></textarea>
   <label>Clave</label><input type="text" name="clave" required autocomplete="off" value="<?= h(($accion==='descifrar') ? ($_POST['clave'] ?? '') : '') ?>">
   <label class="chk" style="margin-top:12px"><input type="checkbox" name="alterar" <?= isset($_POST['alterar'])?'checked':'' ?>> Simular alteración del mensaje</label>
   <button>Descifrar</button>
  </form>
 </div>
 <?php if ($accion === 'descifrar'): ?>
 <div class="card"><h2>Resultado</h2>
  <?php if (isset($res['error'])): ?><span class="badge bad">⚠ <?= h($res['error']) ?></span>
  <?php elseif ($res['claro'] === false): ?>
   <span class="badge bad">❌ Rechazado: <?= $res['gcm'] ? 'la etiqueta GCM no coincide (clave incorrecta o mensaje alterado)' : 'padding inválido o clave incorrecta' ?></span>
  <?php else: ?>
   <span class="badge ok">✅ Descifrado</span>
   <pre><?= h($res['claro']) ?></pre>
   <?php if ($res['alterado'] && !$res['gcm']): ?>
    <span class="badge warn">⚠ Se alteró el mensaje y <?= h($res['algo']) ?> NO lo detectó: el texto salió modificado sin ningún error. Por eso se prefiere GCM o Libsodium.</span>
   <?php endif; ?>
  <?php endif; ?>
 </div>
 <?php endif; ?>
<?php endif; ?>
</main>

<script>
// Ajusta la ayuda de longitud de clave y genera claves aleatorias del largo correcto
const sel = document.getElementById('algo');
if (sel) {
  const clave = document.getElementById('clave'), info = document.getElementById('lenInfo'), desc = document.getElementById('desc');
  function actualizar() {
    const o = sel.options[sel.selectedIndex], n = parseInt(o.dataset.len);
    clave.maxLength = n; info.textContent = '(exactamente ' + n + ' caracteres)'; desc.textContent = o.dataset.desc;
  }
  sel.addEventListener('change', actualizar); actualizar();
  document.getElementById('gen').addEventListener('click', () => {
    const n = parseInt(sel.options[sel.selectedIndex].dataset.len), c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    const r = crypto.getRandomValues(new Uint32Array(n));
    clave.value = Array.from(r, v => c[v % c.length]).join('');
  });
}
</script>
</body>
</html>