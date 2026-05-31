<?php
/**
 * Certificación QZ Tray — SYNTImeat
 * Verifica archivos, criptografía y endpoints del servidor.
 *
 * Uso:
 *   php artisan tinker --execute="require 'tests/qz_certification.php';"
 */

$pass     = 0;
$fail     = 0;
$failures = [];

function chk(string $label, bool $ok, int &$pass, int &$fail, array &$failures): void
{
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . "\n";
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        $failures[] = $label;
    }
}

echo "\n╔══════════════════════════════════════════════════════════╗\n";
echo "║   CERTIFICACIÓN QZ Tray — SYNTImeat                     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

// ─── 1. Archivos en disco ──────────────────────────────────────────────────
echo "── 1. Archivos en storage/app/qz/ ──\n";

$certPathTxt = storage_path('app/qz/digital-certificate.txt');
$certPath    = storage_path('app/qz/digital-certificate');
$keyPath     = storage_path('app/qz/private-key.pem');

$resolvedCert = file_exists($certPathTxt) ? $certPathTxt
    : (file_exists($certPath) ? $certPath : null);

chk('digital-certificate.txt existe', file_exists($certPathTxt), $pass, $fail, $failures);
chk('digital-certificate.txt tiene contenido',
    $resolvedCert !== null && strlen(file_get_contents($resolvedCert)) > 0,
    $pass, $fail, $failures);
chk('private-key.pem existe', file_exists($keyPath), $pass, $fail, $failures);

$keyContent = file_exists($keyPath) ? file_get_contents($keyPath) : '';
chk('private-key.pem tiene contenido', strlen($keyContent) > 0, $pass, $fail, $failures);

// ─── 2. OpenSSL: firma y codificación ─────────────────────────────────────
echo "\n── 2. Criptografía OpenSSL ──\n";

$certOk = ($resolvedCert !== null && strlen(file_get_contents($resolvedCert)) > 0);
$keyOk  = strlen($keyContent) > 0;

if ($certOk && $keyOk) {
    $pkeyId = openssl_pkey_get_private($keyContent);
    chk('openssl_pkey_get_private() ok', $pkeyId !== false, $pass, $fail, $failures);

    if ($pkeyId !== false) {
        $signature = '';
        $signed    = openssl_sign('test-payload', $signature, $pkeyId, OPENSSL_ALGO_SHA512);
        chk('openssl_sign("test-payload", SHA512) ok', $signed === true, $pass, $fail, $failures);
        chk('firma no está vacía', strlen($signature) > 0, $pass, $fail, $failures);

        $b64 = base64_encode($signature);
        chk('base64_encode(firma) no está vacío', strlen($b64) > 0, $pass, $fail, $failures);

        if (strlen($b64) > 0) {
            echo "    firma base64 (" . strlen($b64) . " chars): " . substr($b64, 0, 40) . "…\n";
        }
    }
} else {
    echo "  SKIP: archivos no disponibles — no se puede probar criptografía\n";
    $fail += 4; // contar los checks que se saltaron como fallos
    $failures[] = 'openssl_pkey_get_private() ok (SKIP — clave no disponible)';
    $failures[] = 'openssl_sign ok (SKIP)';
    $failures[] = 'firma no está vacía (SKIP)';
    $failures[] = 'base64_encode ok (SKIP)';
}

// ─── 3. Controlador: GET /qz/certificate ──────────────────────────────────
echo "\n── 3. QzController::certificate() ──\n";

try {
    $controller   = new \App\Http\Controllers\QzController();
    $certResponse = $controller->certificate();
    $status       = $certResponse->getStatusCode();
    $body         = $certResponse->getContent();

    chk('HTTP 200', $status === 200, $pass, $fail, $failures);
    chk('Content-Type: text/plain', str_contains($certResponse->headers->get('Content-Type', ''), 'text/plain'), $pass, $fail, $failures);
    chk('Cuerpo no vacío', strlen($body) > 0, $pass, $fail, $failures);

    if (strlen($body) > 0) {
        echo "    certificado (" . strlen($body) . " bytes): " . substr(trim($body), 0, 60) . "…\n";
    }
} catch (\Throwable $e) {
    chk('HTTP 200 (EXCEPCIÓN: ' . $e->getMessage() . ')', false, $pass, $fail, $failures);
    chk('Content-Type: text/plain', false, $pass, $fail, $failures);
    chk('Cuerpo no vacío', false, $pass, $fail, $failures);
}

// ─── 4. Controlador: POST /qz/sign ────────────────────────────────────────
echo "\n── 4. QzController::sign() ──\n";

try {
    $fakeRequest = \Illuminate\Http\Request::create(
        '/qz/sign',
        'POST',
        ['toSign' => 'test-payload-sign-check'],
    );

    $controller  = new \App\Http\Controllers\QzController();
    $signResponse = $controller->sign($fakeRequest);
    $signStatus   = $signResponse->getStatusCode();
    $signBody     = $signResponse->getContent();

    chk('HTTP 200', $signStatus === 200, $pass, $fail, $failures);
    chk('Respuesta no vacía', strlen($signBody) > 0, $pass, $fail, $failures);
    chk('Base64 válido (sin espacios ni saltos)', preg_match('/^[A-Za-z0-9+\/]+=*$/', $signBody) === 1, $pass, $fail, $failures);

    if (strlen($signBody) > 0) {
        echo "    firma (" . strlen($signBody) . " chars): " . substr($signBody, 0, 40) . "…\n";
    }
} catch (\Throwable $e) {
    chk('HTTP 200 (EXCEPCIÓN: ' . $e->getMessage() . ')', false, $pass, $fail, $failures);
    chk('Respuesta no vacía', false, $pass, $fail, $failures);
    chk('Base64 válido', false, $pass, $fail, $failures);
}

// ─── 5. Coherencia: misma firma para mismo payload ─────────────────────────
echo "\n── 5. Coherencia entre openssl_sign y /qz/sign ──\n";

if ($keyOk && $pkeyId !== false) {
    try {
        $payload      = 'coherence-test';
        $fakeReq2     = \Illuminate\Http\Request::create('/qz/sign', 'POST', ['toSign' => $payload]);
        $controller2  = new \App\Http\Controllers\QzController();
        $resp2        = $controller2->sign($fakeReq2);
        $fromEndpoint = $resp2->getContent();

        $sigDirect = '';
        openssl_sign($payload, $sigDirect, $pkeyId, OPENSSL_ALGO_SHA512);
        $fromDirect = base64_encode($sigDirect);

        chk('Firma del endpoint == firma directa (mismo key, mismo payload)', $fromEndpoint === $fromDirect, $pass, $fail, $failures);
    } catch (\Throwable $e) {
        chk('Coherencia (EXCEPCIÓN: ' . $e->getMessage() . ')', false, $pass, $fail, $failures);
    }
} else {
    echo "  SKIP: clave no disponible\n";
}

// ─── Resultado ─────────────────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n╔══════════════════════════════════════════════════════════╗\n";
if ($fail === 0) {
    echo "║  RESULTADO: PASS ✓  {$pass}/{$total} verificaciones OK              ║\n";
} else {
    printf("║  RESULTADO: FAIL ✗  %d/%d OK · %d fallos               ║\n", $pass, $total, $fail);
}
echo "╚══════════════════════════════════════════════════════════╝\n";

if (! empty($failures)) {
    echo "\nFALLOS:\n";
    foreach ($failures as $i => $f) {
        echo '  ' . ($i + 1) . ". {$f}\n";
    }
}
echo "\n";
