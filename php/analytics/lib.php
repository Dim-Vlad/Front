<?php
/**
 * Fonctions utilitaires pour le suivi de fréquentation interne.
 * Aucune IP n'est jamais stockée : elle sert uniquement, le temps
 * de la requête, à déduire un pays via la base GeoIP locale.
 */

function analytics_parse_ua(string $ua): array {
    if ($ua === '') return ['Inconnu', 'Inconnu'];

    if (str_contains($ua, 'Edg/'))                                          $browser = 'Edge';
    elseif (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera/'))      $browser = 'Opera';
    elseif (str_contains($ua, 'YaBrowser'))                                 $browser = 'Yandex';
    elseif (str_contains($ua, 'Chrome/') && !str_contains($ua, 'Chromium'))$browser = 'Chrome';
    elseif (str_contains($ua, 'Chromium/'))                                 $browser = 'Chromium';
    elseif (str_contains($ua, 'Firefox/'))                                  $browser = 'Firefox';
    elseif (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/')) $browser = 'Safari';
    else                                                                     $browser = 'Autre';

    if (str_contains($ua, 'Android'))                                       $os = 'Android';
    elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad'))      $os = 'iOS';
    elseif (str_contains($ua, 'Windows'))                                   $os = 'Windows';
    elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) $os = 'macOS';
    elseif (str_contains($ua, 'Linux'))                                     $os = 'Linux';
    else                                                                     $os = 'Autre';

    return [$browser, $os];
}

/**
 * Recherche dichotomique dans geoip-country.bin (plages d'IPv4 triées,
 * 10 octets par entrée : 4 = début, 4 = fin, 2 = code pays ISO).
 * Base dérivée de DB-IP Country Lite (CC BY 4.0, https://db-ip.com/).
 */
function geoip_lookup(string $ip): array {
    static $countryNames = null;
    if ($countryNames === null) {
        $json = @file_get_contents(__DIR__ . '/pays-fr.json');
        $countryNames = $json ? (json_decode($json, true) ?: []) : [];
    }

    $default = ['code' => '', 'nom' => 'Inconnu'];

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $default;
    }
    $target = ip2long($ip);
    if ($target === false) return $default;

    static $fh = false;
    if ($fh === false) {
        $fh = @fopen(__DIR__ . '/geoip-country.bin', 'rb');
    }
    if ($fh === null || $fh === false) return $default;

    $recSize = 10;
    $size    = filesize(__DIR__ . '/geoip-country.bin');
    $count   = intdiv($size, $recSize);
    $lo = 0; $hi = $count - 1;

    while ($lo <= $hi) {
        $mid = intdiv($lo + $hi, 2);
        fseek($fh, $mid * $recSize);
        $data = fread($fh, $recSize);
        if ($data === false || strlen($data) < $recSize) break;
        $row = unpack('Nstart/Nend/a2cc', $data);
        if ($target < $row['start']) {
            $hi = $mid - 1;
        } elseif ($target > $row['end']) {
            $lo = $mid + 1;
        } else {
            $code = trim($row['cc']);
            if ($code === '') return $default;
            $nom = $countryNames[$code] ?? $code;
            return ['code' => $code, 'nom' => $nom];
        }
    }
    return $default;
}

function analytics_client_ip(): string {
    $raw = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return trim(explode(',', $raw)[0]);
}

/**
 * Détection des robots d'indexation / outils automatisés via leur
 * User-Agent, pour ne pas fausser les statistiques de fréquentation
 * (même principe que les outils d'analytics classiques type Matomo).
 */
function analytics_is_bot(string $ua): bool {
    if ($ua === '') return true;
    return (bool)preg_match(
        '/bot|crawler|crawl|spider|slurp|facebookexternalhit|embedly|outbrain|' .
        'pingdom|uptimerobot|statuscake|site24x7|headlesschrome|phantomjs|lighthouse|' .
        'python-requests|curl\/|wget\/|scrapy|ccbot|gptbot|chatgpt-user|anthropic|' .
        'perplexity|bytespider|mediapartners/i',
        $ua
    );
}
