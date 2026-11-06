<?php
/** Receives time-on-page and click events from assets/js/main.js (sendBeacon). Cookieless, no personal data. */
declare(strict_types=1);
require __DIR__ . '/inc/analytics.php';
http_response_code(204);
if (!env_bool('ANALYTICS_ENABLED', true)) exit;
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') exit;
if (($_SERVER['HTTP_DNT'] ?? '') === '1' || isset($_COOKIE['clapet_admin'])) exit;
if (track_is_bot($_SERVER['HTTP_USER_AGENT'] ?? '')) exit;

$type = (string)($_POST['t'] ?? '');
$page = preg_replace('/[^a-z0-9-]/i', '', (string)($_POST['p'] ?? ''));
if ($type === 'dwell') {
    track_event('dwell', '', $page, max(0, min(1800000, (int)($_POST['ms'] ?? 0))));
} elseif ($type === 'click') {
    $label = (string)($_POST['l'] ?? '');
    if (in_array($label, ['WhatsApp', 'Call', 'Email', 'Submit a brief', 'Consultation', 'Client portal', 'Service page'], true)) track_event('click', $label, $page);
}
