<?php

namespace App\Service;

use App\Entity\Projets;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class MarketstackService
{
    private string $apiKey;
    private string $apiUrl = 'http://api.marketstack.com/v1';

    private const SECTOR_ETF = [
        'Technologie' => 'XLK',
        'Santé'       => 'XLV',
        'Finance'     => 'XLF',
        'Écologie'    => 'ICLN',
        'Éducation'   => 'EDU',
        'Commerce'    => 'XLY',
        'Artisanat'   => 'XLI',
        'Agriculture' => 'MOO',
    ];

    private const SECTOR_COMMODITY = [
        'Agriculture' => 'corn',
        'Écologie'    => 'crude_oil',
        'Technologie' => 'copper',
        'Finance'     => 'gold',
        'Artisanat'   => 'steel',
        'Commerce'    => 'natural_gas',
        'Santé'       => null,
        'Éducation'   => null,
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        string $marketstackApiKey,
    ) {
        $this->apiKey = $marketstackApiKey;
    }

    public function analyzeSectorTrend(Projets $projet): array
    {
        $sector    = $projet->getSecteur();
        $sectorKey = $this->normalizeSector($sector);
        $symbol    = self::SECTOR_ETF[$sectorKey] ?? null;

        $eod        = $symbol ? $this->fetchEodHistory($symbol)   : [];
        $latest     = $symbol ? $this->fetchLatestEod($symbol)    : null;
        $live       = $symbol ? $this->fetchLivePrice($symbol)    : null;
        $tickerInfo = $symbol ? $this->fetchTickerInfo($symbol)   : null;
        $splits     = $symbol ? $this->fetchRecentSplits($symbol) : [];
        $commName   = self::SECTOR_COMMODITY[$sectorKey] ?? null;
        $commodity  = $commName ? $this->fetchCommodity($commName) : null;
        $macro      = $this->fetchMacroIndex('sp500');

        $prices  = array_reverse(array_column($eod, 'close'));
        $volumes = array_reverse(array_column($eod, 'volume'));

        $momentum1w = $this->calcGrowth($prices, 5);
        $momentum1m = $this->calcGrowth($prices, 21);
        $volatility = $this->calcVolatility($prices);
        $volSignal  = $this->calcVolumeSignal($volumes);
        $rsi        = $this->calcRSI($prices);
        $trend      = $this->determineTrend($momentum1w, $momentum1m, $rsi);

        $scoreMomentum   = $this->scoreMomentum($momentum1w, $momentum1m);
        $scoreVolatility = $this->scoreVolatility($volatility);
        $scoreCommodity  = $commodity ? $this->scoreCommodity($commodity) : 50;
        $scoreMacro      = $macro     ? $this->scoreMacro($macro)         : 50;

        $composite = (int)(
            $scoreMomentum   * 0.40 +
            $scoreVolatility * 0.20 +
            $scoreCommodity  * 0.20 +
            $scoreMacro      * 0.20
        );

        $alerts         = $this->buildAlerts($momentum1w, $momentum1m, $volatility, $rsi, $splits, $commodity, $macro);
        $recommendation = $this->buildRecommendation($trend, $composite, $sector, $commodity, $macro);

        return [
            'symbol'          => $symbol,
            'sector'          => $sector,
            'current_price'   => $latest['close'] ?? ($live['price'] ?? null),
            'live_price'      => $live,
            'latest_eod'      => $latest,
            'growth_1w'       => round($momentum1w, 2),
            'growth_1m'       => round($momentum1m, 2),
            'trend'           => $trend,
            'volatility'      => round($volatility, 2),
            'rsi'             => round($rsi, 1),
            'volume_signal'   => $volSignal,
            'price_history'   => array_slice($prices, -10),
            'ticker_info'     => $tickerInfo,
            'recent_splits'   => $splits,
            'commodity'       => $commodity,
            'macro_index'     => $macro,
            'score_momentum'  => $scoreMomentum,
            'score_volatility'=> $scoreVolatility,
            'score_commodity' => $scoreCommodity,
            'score_macro'     => $scoreMacro,
            'score'           => $composite,
            'composite_score' => $composite,
            'alerts'          => $alerts,
            'recommendation'  => $recommendation,
            'analyzed_at'     => new \DateTime(),
        ];
    }

    private function fetchEodHistory(string $symbol): array
    {
        return $this->cache->get("ms_eod_{$symbol}_30d", function () use ($symbol) {
            try { $r = $this->get('/eod', ['symbols' => $symbol, 'limit' => 30, 'sort' => 'DESC']); return $r['data'] ?? []; }
            catch (\Exception $e) { $this->logger->error("EOD [{$symbol}]: " . $e->getMessage()); return []; }
        }, 3600);
    }

    private function fetchLatestEod(string $symbol): ?array
    {
        return $this->cache->get("ms_eod_latest_{$symbol}", function () use ($symbol) {
            try {
                $r = $this->get('/eod/latest', ['symbols' => $symbol]);
                $bar = $r['data'][0] ?? null;
                if (!$bar) return null;
                return ['close' => round((float)$bar['close'], 2), 'open' => round((float)$bar['open'], 2),
                    'high' => round((float)$bar['high'], 2), 'low' => round((float)$bar['low'], 2),
                    'volume' => (float)$bar['volume'], 'adj_close' => round((float)($bar['adj_close'] ?? $bar['close']), 2),
                    'date' => $bar['date'], 'exchange' => $bar['exchange_code'] ?? null];
            } catch (\Exception $e) { $this->logger->warning("EOD latest [{$symbol}]: " . $e->getMessage()); return null; }
        }, 1800);
    }

    private function fetchLivePrice(string $symbol): ?array
    {
        return $this->cache->get("ms_live_{$symbol}", function () use ($symbol) {
            try {
                $r = $this->get('/stockprice', ['ticker' => $symbol]);
                $d = $r['data'][0] ?? null;
                if (!$d) return null;
                return ['price' => $d['price'], 'currency' => $d['currency'] ?? 'USD',
                    'exchange' => $d['exchange_name'] ?? null, 'updated' => $d['trade_last'] ?? null];
            } catch (\Exception $e) { $this->logger->warning("Live [{$symbol}]: " . $e->getMessage()); return null; }
        }, 300);
    }

    private function fetchTickerInfo(string $symbol): ?array
    {
        return $this->cache->get("ms_tickerinfo_{$symbol}", function () use ($symbol) {
            try {
                $r = $this->get('/tickerinfo', ['ticker' => $symbol]);
                $d = $r['data'] ?? null;
                if (!$d) return null;
                return ['name' => $d['name'] ?? null, 'sector' => $d['sector'] ?? null,
                    'industry' => $d['industry'] ?? null, 'about' => $d['about'] ?? null, 'website' => $d['website'] ?? null];
            } catch (\Exception $e) { $this->logger->warning("TickerInfo [{$symbol}]: " . $e->getMessage()); return null; }
        }, 86400);
    }

    private function fetchRecentSplits(string $symbol): array
    {
        return $this->cache->get("ms_splits_{$symbol}", function () use ($symbol) {
            try {
                $dateFrom = (new \DateTime('-1 year'))->format('Y-m-d');
                $r = $this->get('/splits', ['symbols' => $symbol, 'date_from' => $dateFrom, 'limit' => 5]);
                return array_map(fn($s) => ['date' => $s['date'], 'factor' => $s['split_factor']], $r['data'] ?? []);
            } catch (\Exception $e) { $this->logger->warning("Splits [{$symbol}]: " . $e->getMessage()); return []; }
        }, 86400);
    }

    private function fetchCommodity(string $name): ?array
    {
        return $this->cache->get("ms_commodity_{$name}", function () use ($name) {
            try {
                $r = $this->get('/commodities', ['commodity_name' => $name]);
                $d = $r['data'][0] ?? null;
                if (!$d) return null;
                return ['name' => $d['commodity_name'] ?? $name, 'price' => $d['commodity_price'] ?? null,
                    'unit' => $d['commodity_unit'] ?? null, 'pct_day' => $d['percentage_day'] ?? null,
                    'pct_week' => $d['percentage_week'] ?? null, 'pct_month' => $d['percentage_month'] ?? null,
                    'pct_year' => $d['percentage_year'] ?? null];
            } catch (\Exception $e) { $this->logger->warning("Commodity [{$name}]: " . $e->getMessage()); return null; }
        }, 1800);
    }

    private function fetchMacroIndex(string $index): ?array
    {
        return $this->cache->get("ms_index_{$index}", function () use ($index) {
            try {
                $r = $this->get('/indexinfo', ['index' => $index]);
                $d = is_array($r) && isset($r[0]) ? $r[0] : null;
                if (!$d) return null;
                return ['benchmark' => $d['benchmark'] ?? $index, 'price' => $d['price'] ?? null,
                    'pct_day' => $d['percentage_day'] ?? null, 'pct_week' => $d['percentage_week'] ?? null,
                    'pct_month' => $d['percentage_month'] ?? null, 'pct_year' => $d['percentage_year'] ?? null];
            } catch (\Exception $e) { $this->logger->warning("Index [{$index}]: " . $e->getMessage()); return null; }
        }, 1800);
    }

    private function calcGrowth(array $prices, int $days): float
    {
        $n = count($prices);
        if ($n <= $days) return 0.0;
        $current = end($prices);
        $past    = $prices[$n - $days - 1];
        return $past > 0 ? (($current - $past) / $past) * 100 : 0.0;
    }

    private function calcVolatility(array $prices): float
    {
        $n = count($prices);
        if ($n < 2) return 0.0;
        $returns = [];
        for ($i = 1; $i < $n; $i++) {
            if ($prices[$i - 1] > 0) $returns[] = log($prices[$i] / $prices[$i - 1]);
        }
        if (empty($returns)) return 0.0;
        $mean     = array_sum($returns) / count($returns);
        $variance = array_sum(array_map(fn($r) => ($r - $mean) ** 2, $returns)) / count($returns);
        return sqrt($variance) * sqrt(252) * 100;
    }

    private function calcRSI(array $prices, int $period = 14): float
    {
        $n = count($prices);
        if ($n < $period + 1) return 50.0;
        $gains = $losses = [];
        for ($i = 1; $i <= $period; $i++) {
            $diff = $prices[$n - $period + $i - 1] - $prices[$n - $period + $i - 2];
            if ($diff > 0) $gains[] = $diff; else $losses[] = abs($diff);
        }
        $avgGain = $gains  ? array_sum($gains)  / $period : 0;
        $avgLoss = $losses ? array_sum($losses) / $period : 0;
        if ($avgLoss == 0) return 100.0;
        return 100 - (100 / (1 + ($avgGain / $avgLoss)));
    }

    private function calcVolumeSignal(array $volumes): int
    {
        if (empty($volumes)) return 100;
        $avg  = array_sum($volumes) / count($volumes);
        $last = end($volumes);
        return $avg > 0 ? (int)(($last / $avg) * 100) : 100;
    }

    private function determineTrend(float $w1, float $m1, float $rsi): string
    {
        $score = ($w1 * 0.5) + ($m1 * 0.3) + (($rsi - 50) * 0.1);
        if ($score > 3)  return 'bullish';
        if ($score < -3) return 'bearish';
        return 'neutral';
    }

    private function scoreMomentum(float $w1, float $m1): int
    {
        $s1 = min(100, max(0, ($w1 + 20) / 40 * 100));
        $s2 = min(100, max(0, ($m1 + 20) / 40 * 100));
        return (int)(($s1 * 0.6) + ($s2 * 0.4));
    }

    private function scoreVolatility(float $vol): int
    {
        if ($vol < 15) return 90;
        if ($vol < 25) return 70;
        if ($vol < 35) return 50;
        return 30;
    }

    private function scoreCommodity(array $c): int
    {
        $pct = (float)str_replace('%', '', $c['pct_month'] ?? '0');
        return min(100, max(0, (int)(50 + $pct * 2)));
    }

    private function scoreMacro(?array $m): int
    {
        if (!$m) return 50;
        $pct = (float)str_replace('%', '', $m['pct_month'] ?? '0');
        return min(100, max(0, (int)(50 + $pct * 3)));
    }

    private function buildAlerts(float $w1, float $m1, float $vol, float $rsi, array $splits, ?array $commodity, ?array $macro): array
    {
        $alerts = [];
        $rsiR = round($rsi, 0); $w1R = round($w1, 1); $volR = round($vol, 1);
        if ($rsi > 70)  $alerts[] = ['type' => 'warning', 'msg' => "Le marche est en surachat (RSI {$rsiR})."];
        if ($rsi < 30)  $alerts[] = ['type' => 'success', 'msg' => "Le marche est en survente (RSI {$rsiR})."];
        if ($vol > 35)  $alerts[] = ['type' => 'danger',  'msg' => "Secteur tres instable (volatilite {$volR}%)."];
        if ($vol < 12)  $alerts[] = ['type' => 'success', 'msg' => "Marche tres stable (volatilite {$volR}%)."];
        if ($w1 < -5)   $alerts[] = ['type' => 'danger',  'msg' => "Le secteur a perdu {$w1R}% cette semaine."];
        if ($w1 > 5)    $alerts[] = ['type' => 'success', 'msg' => "Le secteur a progresse de +{$w1R}% cette semaine."];
        if (!empty($splits)) $alerts[] = ['type' => 'info', 'msg' => count($splits) . " division(s) d'actions detectee(s)."];
        if ($commodity) {
            $cp = round((float)str_replace('%', '', $commodity['pct_month'] ?? '0'), 1);
            if ($cp > 10)  $alerts[] = ['type' => 'warning', 'msg' => "{$commodity['name']} +{$cp}% ce mois."];
            if ($cp < -10) $alerts[] = ['type' => 'success', 'msg' => "{$commodity['name']} {$cp}% ce mois."];
        }
        if ($macro) {
            $mp = round((float)str_replace('%', '', $macro['pct_month'] ?? '0'), 1);
            if ($mp < -5) $alerts[] = ['type' => 'danger',  'msg' => "S&P500 en baisse de {$mp}% ce mois."];
            if ($mp > 5)  $alerts[] = ['type' => 'success', 'msg' => "S&P500 en hausse de +{$mp}% ce mois."];
        }
        return $alerts;
    }

    private function buildRecommendation(string $trend, int $score, string $sector, ?array $commodity, ?array $macro): string
    {
        $parts = [];
        $parts[] = match($trend) {
            'bullish' => $score >= 70 ? "Le secteur {$sector} est en forte croissance." : "Le secteur {$sector} montre des signes positifs.",
            'bearish' => "Le secteur {$sector} est en recul en ce moment.",
            default   => "Le secteur {$sector} est stable.",
        };
        if ($macro) {
            $mp = round((float)str_replace('%', '', $macro['pct_month'] ?? '0'), 1);
            if ($mp > 3)      $parts[] = "Economie mondiale bien orientee (S&P500 +{$mp}% ce mois).";
            elseif ($mp < -3) $parts[] = "Economie mondiale sous pression (S&P500 {$mp}% ce mois).";
        }
        $parts[] = "Score composite : {$score}/100.";
        return implode(' ', $parts);
    }

    private function get(string $endpoint, array $params = []): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl . $endpoint, [
            'query'   => array_merge(['access_key' => $this->apiKey], $params),
            'timeout' => 12,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Marketstack HTTP {$response->getStatusCode()} on {$endpoint}");
        }
        $data = $response->toArray(false);
        if (isset($data['error'])) {
            throw new \RuntimeException("Marketstack error: " . ($data['error']['message'] ?? 'unknown'));
        }
        return $data;
    }

    private function normalizeSector(string $sector): string
    {
        $map = ['Santé' => 'Sante', 'Écologie' => 'Ecologie', 'Éducation' => 'Education'];
        return $map[$sector] ?? $sector;
    }

    public function getSupportedSectors(): array
    {
        return array_keys(self::SECTOR_ETF);
    }
}
