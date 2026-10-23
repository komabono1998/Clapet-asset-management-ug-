<?php
/**
 * Clapet Asset Management UG - SMC — secure brief / enquiry intake.
 * Server-side validation, honeypot + rate limit, restricted uploads,
 * private storage (storage/ is denied over HTTP), unique reference numbers.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out(array $a, int $code = 200): never { http_response_code($code); echo json_encode($a); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'Method not allowed.'], 405);
if (!empty($_POST['website'])) out(['ok' => true, 'reference' => 'CAM-0000-00000']); // honeypot: pretend success

require __DIR__ . '/inc/settings.php';
$root = __DIR__ . '/storage';
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// simple rate limit: 8 submissions / hour / IP
$rl = $root . '/rl_' . md5($ip) . '.json';
$hits = is_file($rl) ? (json_decode((string)file_get_contents($rl), true) ?: []) : [];
$hits = array_values(array_filter($hits, fn($t) => $t > time() - 3600));
if (count($hits) >= 8) out(['ok' => false, 'error' => 'Too many submissions. Please try again later.'], 429);

$clean = static fn(string $v, int $max = 2000): string => mb_substr(trim(strip_tags($v)), 0, $max);

$services = ['property-management', 'construction-supervision', 'construction-contracts', 'land-verification', 'farm-management', 'general-supplies', 'consultation', 'listing', 'partner', 'general'];
$service  = $clean((string)($_POST['service'] ?? 'general'), 40);
if (!in_array($service, $services, true)) out(['ok' => false, 'error' => 'Unknown service selected.'], 422);

$name  = $clean((string)($_POST['full_name'] ?? ''), 120);
$email = trim((string)($_POST['email'] ?? ''));
$phone = $clean((string)($_POST['phone'] ?? ''), 40);
if ($name === '')                                   out(['ok' => false, 'error' => 'Please enter your name.'], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL))     out(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,40}$/', $phone)) out(['ok' => false, 'error' => 'Please enter a valid telephone number.'], 422);
if (isset($_POST['consent']) === false && ($_POST['form'] ?? '') === 'brief') out(['ok' => false, 'error' => 'Consent to the privacy notice is required.'], 422);

$skip = ['website', 'consent', 'form'];
$data = [];
foreach ($_POST as $k => $v) {
    if (in_array($k, $skip, true) || !is_string($v)) continue;
    $k = preg_replace('/[^a-z0-9_]/i', '', $k);
    if ($k !== '' && trim($v) !== '') $data[$k] = $clean($v);
}
$data['email'] = $email;

// uploads: pdf / images / office docs, max 5 MB each, max 5 files
$allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
$ref  = 'CAM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
$files = [];
if (!empty($_FILES['attachments']['name'][0])) {
    $n = min(count($_FILES['attachments']['name']), 5);
    @mkdir("$root/uploads/$ref", 0750, true);
    for ($i = 0; $i < $n; $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['attachments']['size'][$i] > 5 * 1024 * 1024) out(['ok' => false, 'error' => 'Each file must be 5 MB or smaller.'], 422);
        $ext = strtolower(pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) out(['ok' => false, 'error' => 'Allowed file types: PDF, JPG, PNG, DOCX, XLSX.'], 422);
        $stored = bin2hex(random_bytes(8)) . '.' . $ext;
        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], "$root/uploads/$ref/$stored")) {
            $files[] = ['original' => $clean($_FILES['attachments']['name'][$i], 120), 'stored' => $stored];
        }
    }
}

$record = ['reference' => $ref, 'received' => date('c'), 'service' => $service, 'status' => 'New enquiry',
           'ip_hash' => hash('sha256', $ip), 'data' => $data, 'files' => $files];
if (file_put_contents("$root/enquiries/$ref.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    out(['ok' => false, 'error' => 'We could not save your submission. Please contact us directly.'], 500);
}
$hits[] = time();
file_put_contents($rl, json_encode($hits));

// Optional staff notification (configure mail on the production host).
@mail($S['email'], "New enquiry $ref ($service)", "A new enquiry $ref was received. Review it in the admin dashboard.", 'From: no-reply@clapetassetmanagement.com');

out(['ok' => true, 'reference' => $ref]);
