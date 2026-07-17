<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once '../config/db_config.php';

if (
    empty($_SESSION['logged_in']) ||
    ($_SESSION['login_type'] ?? '') !== 'admin' ||
    !in_array((int)($_SESSION['role'] ?? 0), [1, 2, 3], true)
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '관리자 로그인이 필요합니다.']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '데이터베이스 연결에 실패했습니다.']);
    exit;
}

try {
    $summary = [
        'estimate_total' => 0,
        'pending' => 0,
        'contacted' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'today_count' => 0,
        'hall_count' => 0,
        'region_count' => 0,
        'product_count' => 0,
        'active_product_count' => 0,
        'priced_hall_count' => 0,
        'price_item_count' => 0
    ];

    $estimateSummary = $conn->query("
        SELECT
            COUNT(*) AS estimate_total,
            SUM(status = 'pending') AS pending,
            SUM(status = 'contacted') AS contacted,
            SUM(status = 'completed') AS completed,
            SUM(status = 'cancelled') AS cancelled,
            SUM(DATE(created_at) = CURDATE()) AS today_count
        FROM estimate_request
    ")->fetch_assoc();

    foreach (['estimate_total', 'pending', 'contacted', 'completed', 'cancelled', 'today_count'] as $key) {
        $summary[$key] = (int)($estimateSummary[$key] ?? 0);
    }

    $hallSummary = $conn->query("
        SELECT COUNT(*) AS hall_count, COUNT(DISTINCT NULLIF(addr_sido, '')) AS region_count
        FROM funeral_hall
        WHERE is_active = 1
    ")->fetch_assoc();
    $summary['hall_count'] = (int)($hallSummary['hall_count'] ?? 0);
    $summary['region_count'] = (int)($hallSummary['region_count'] ?? 0);

    $productSummary = $conn->query("
        SELECT COUNT(*) AS product_count, SUM(is_active = 1) AS active_product_count
        FROM funeral_products
    ")->fetch_assoc();
    $summary['product_count'] = (int)($productSummary['product_count'] ?? 0);
    $summary['active_product_count'] = (int)($productSummary['active_product_count'] ?? 0);

    $priceSummary = $conn->query("
        SELECT COUNT(*) AS price_item_count, COUNT(DISTINCT hall_id) AS priced_hall_count
        FROM funeral_hall_price
    ")->fetch_assoc();
    $summary['price_item_count'] = (int)($priceSummary['price_item_count'] ?? 0);
    $summary['priced_hall_count'] = (int)($priceSummary['priced_hall_count'] ?? 0);

    $recentEstimates = [];
    $recentResult = $conn->query("
        SELECT estimate_id, deceased_name, sido, sigungu, contact_name, contact_phone,
               funeral_period, status, created_at
        FROM estimate_request
        ORDER BY created_at DESC, estimate_id DESC
        LIMIT 10
    ");
    while ($row = $recentResult->fetch_assoc()) {
        $recentEstimates[] = [
            'estimate_id' => (int)$row['estimate_id'],
            'deceased_name' => $row['deceased_name'],
            'sido' => $row['sido'],
            'sigungu' => $row['sigungu'],
            'contact_name' => $row['contact_name'],
            'contact_phone' => $row['contact_phone'],
            'funeral_period' => $row['funeral_period'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }

    $trendMap = [];
    for ($offset = 6; $offset >= 0; $offset--) {
        $date = date('Y-m-d', strtotime("-{$offset} days"));
        $trendMap[$date] = 0;
    }
    $trendResult = $conn->query("
        SELECT DATE(created_at) AS request_date, COUNT(*) AS request_count
        FROM estimate_request
        WHERE created_at >= CURDATE() - INTERVAL 6 DAY
        GROUP BY DATE(created_at)
        ORDER BY request_date ASC
    ");
    while ($row = $trendResult->fetch_assoc()) {
        $trendMap[$row['request_date']] = (int)$row['request_count'];
    }
    $requestTrend = [];
    foreach ($trendMap as $date => $count) {
        $requestTrend[] = ['date' => $date, 'count' => $count];
    }

    $topRegions = [];
    $regionResult = $conn->query("
        SELECT sido, sigungu, COUNT(*) AS request_count
        FROM estimate_request
        WHERE COALESCE(sido, '') <> ''
        GROUP BY sido, sigungu
        ORDER BY request_count DESC, sido ASC, sigungu ASC
        LIMIT 5
    ");
    while ($row = $regionResult->fetch_assoc()) {
        $topRegions[] = [
            'label' => trim($row['sido'] . ' ' . ($row['sigungu'] ?? '')),
            'count' => (int)$row['request_count']
        ];
    }

    $conn->close();
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'recent_estimates' => $recentEstimates,
        'request_trend' => $requestTrend,
        'top_regions' => $topRegions,
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Admin dashboard error: ' . $error->getMessage());
    if (isset($conn)) $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '운영 현황을 불러오는 중 오류가 발생했습니다.']);
}
?>
