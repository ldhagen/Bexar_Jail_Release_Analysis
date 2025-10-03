<?php
/**
 * Jail Release Data Analysis System - Memory Optimized
 */

// Increase memory limit
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');
set_time_limit(0);

class JailReleaseAnalyzer {
    private $data = [];
    private $trends = [];
    private $cacheFile = '/tmp/jail_analyzer_cache.json';
    private $maxRecordsInMemory = 50000;
    
    public function loadCSVFiles($directory, $forceReload = false) {
        if (!$forceReload && file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile) < 3600)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if ($cached && isset($cached['trends'])) {
                $this->trends = $cached['trends'];
                return $cached['record_count'] ?? 0;
            }
        }
        
        $files = glob($directory . '/*.csv');
        $totalRecords = 0;
        $trends = $this->initializeTrends();
        
        foreach ($files as $file) {
            $fileRecords = $this->processCSVFile($file, $trends);
            $totalRecords += $fileRecords;
            
            if ($totalRecords % 10000 === 0) {
                gc_collect_cycles();
            }
        }
        
        $this->trends = $this->finalizeTrends($trends, $totalRecords);
        
        file_put_contents($this->cacheFile, json_encode([
            'trends' => $this->trends,
            'record_count' => $totalRecords,
            'timestamp' => time()
        ]));
        
        return $totalRecords;
    }
    
    private function initializeTrends() {
        return [
            'release_types' => [],
            'offense_types' => [],
            'bond_types' => [],
            'courts' => [],
            'attorneys' => [],
            'bondsmen' => [],
            'releases_by_date' => [],
            'releases_by_month' => [],
            'bond_sum' => 0,
            'bond_count' => 0,
            'bond_ranges' => [
                '0-1000' => 0,
                '1001-5000' => 0,
                '5001-10000' => 0,
                '10001-25000' => 0,
                '25001-50000' => 0,
                '50001+' => 0
            ],
            'unique_inmates' => [],
            'unique_case_numbers' => []
        ];
    }
    
    private function processCSVFile($filename, &$trends) {
        $count = 0;
        
        if (($handle = fopen($filename, 'r')) !== FALSE) {
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) === count($headers)) {
                    $record = array_combine($headers, $row);
                    $this->updateTrends($record, $trends);
                    $count++;
                }
            }
            fclose($handle);
        }
        
        return $count;
    }
    
    private function updateTrends($record, &$trends) {
        $inmateName = trim($record['InmateName'] ?? '');
        if (!empty($inmateName)) {
            $trends['unique_inmates'][$inmateName] = true;
        }
        
        $caseNumber = trim($record['CaseNumber'] ?? '');
        if (!empty($caseNumber)) {
            $trends['unique_case_numbers'][$caseNumber] = true;
        }
        
        $releaseType = trim($record['ReleaseType'] ?? 'Unknown');
        if (empty($releaseType)) $releaseType = 'Unknown';
        $trends['release_types'][$releaseType] = ($trends['release_types'][$releaseType] ?? 0) + 1;
        
        $offense = trim($record['OffenseDescription'] ?? 'Unknown');
        if (empty($offense)) $offense = 'Unknown';
        $trends['offense_types'][$offense] = ($trends['offense_types'][$offense] ?? 0) + 1;
        
        $bondType = trim($record['BondType'] ?? 'Unknown');
        if (empty($bondType)) $bondType = 'Unknown';
        $trends['bond_types'][$bondType] = ($trends['bond_types'][$bondType] ?? 0) + 1;
        
        $court = trim($record['Court'] ?? 'Unknown');
        if (empty($court)) $court = 'Unknown';
        $trends['courts'][$court] = ($trends['courts'][$court] ?? 0) + 1;
        
        $attorney = trim($record['AttorneyName'] ?? 'Unknown');
        if (empty($attorney)) $attorney = 'Unknown';
        $trends['attorneys'][$attorney] = ($trends['attorneys'][$attorney] ?? 0) + 1;
        
        $bondsman = trim($record['BondsmanName'] ?? 'Unknown');
        if (empty($bondsman)) $bondsman = 'Unknown';
        $trends['bondsmen'][$bondsman] = ($trends['bondsmen'][$bondsman] ?? 0) + 1;
        
        $date = $record['ReleaseDate'] ?? '';
        if (!empty($date)) {
            $trends['releases_by_date'][$date] = ($trends['releases_by_date'][$date] ?? 0) + 1;
            
            $month = date('Y-m', strtotime($date));
            $trends['releases_by_month'][$month] = ($trends['releases_by_month'][$month] ?? 0) + 1;
        }
        
        $amount = floatval($record['BondAmount'] ?? 0);
        if ($amount > 0) {
            $trends['bond_sum'] += $amount;
            $trends['bond_count']++;
            
            if ($amount <= 1000) $trends['bond_ranges']['0-1000']++;
            elseif ($amount <= 5000) $trends['bond_ranges']['1001-5000']++;
            elseif ($amount <= 10000) $trends['bond_ranges']['5001-10000']++;
            elseif ($amount <= 25000) $trends['bond_ranges']['10001-25000']++;
            elseif ($amount <= 50000) $trends['bond_ranges']['25001-50000']++;
            else $trends['bond_ranges']['50001+']++;
        }
    }
    
    private function finalizeTrends($trends, $totalRecords) {
        arsort($trends['release_types']);
        arsort($trends['offense_types']);
        arsort($trends['bond_types']);
        arsort($trends['courts']);
        arsort($trends['attorneys']);
        arsort($trends['bondsmen']);
        arsort($trends['releases_by_date']);
        ksort($trends['releases_by_month']);
        
        $avgBond = $trends['bond_count'] > 0 ? $trends['bond_sum'] / $trends['bond_count'] : 0;
        $uniqueInmates = count($trends['unique_inmates']);
        $uniqueCases = count($trends['unique_case_numbers']);
        
        return [
            'total_releases' => $totalRecords,
            'unique_inmates' => $uniqueInmates,
            'unique_cases' => $uniqueCases,
            'release_types' => $trends['release_types'],
            'offense_types' => $trends['offense_types'],
            'bond_types' => $trends['bond_types'],
            'courts' => $trends['courts'],
            'attorneys' => $trends['attorneys'],
            'bondsmen' => $trends['bondsmen'],
            'releases_by_date' => $trends['releases_by_date'],
            'releases_by_month' => $trends['releases_by_month'],
            'average_bond' => $avgBond,
            'bond_ranges' => $trends['bond_ranges'],
            'top_offenses' => array_slice($trends['offense_types'], 0, 10, true),
            'busiest_days' => array_slice($trends['releases_by_date'], 0, 10, true),
        ];
    }
    
    public function search($directory, $criteria) {
        $results = [];
        $files = glob($directory . '/*.csv');
        $maxResults = 100;
        
        foreach ($files as $file) {
            if (count($results) >= $maxResults) break;
            
            if (($handle = fopen($file, 'r')) !== FALSE) {
                $headers = fgetcsv($handle);
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($results) >= $maxResults) break;
                    
                    if (count($row) === count($headers)) {
                        $record = array_combine($headers, $row);
                        
                        if ($this->matchesCriteria($record, $criteria)) {
                            $results[] = $record;
                        }
                    }
                }
                fclose($handle);
            }
        }
        
        return $results;
    }
    
    private function matchesCriteria($record, $criteria) {
        foreach ($criteria as $field => $value) {
            if (!empty($value)) {
                $recordValue = strtolower(trim($record[$field] ?? ''));
                $searchValue = strtolower(trim($value));
                
                if ($field === 'ReleaseDate' || $field === 'OffenseDate') {
                    if ($recordValue === $searchValue) {
                        continue;
                    }
                    
                    if (strpos($recordValue, $searchValue) !== false) {
                        continue;
                    }
                    
                    $recordDate = strtotime($recordValue);
                    $searchDate = strtotime($searchValue);
                    
                    if ($recordDate && $searchDate) {
                        if (date('Y-m-d', $recordDate) === date('Y-m-d', $searchDate)) {
                            continue;
                        }
                    }
                    
                    return false;
                } else {
                    if (strpos($recordValue, $searchValue) === false) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
    
    public function getCacheFile() {
        return $this->cacheFile;
    }
    
    public function getTrends() {
        return $this->trends;
    }
    
    public function clearCache() {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}

$analyzer = new JailReleaseAnalyzer();
$dataDirectory = '/var/www/html/data';
$message = '';

if (isset($_GET['ajax_load']) && $_GET['ajax_load'] === '1') {
    header('Content-Type: application/json');
    
    try {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        
        $count = $analyzer->loadCSVFiles($dataDirectory, isset($_GET['force']));
        $trends = $analyzer->getTrends();
        
        if ($count > 0 && !empty($trends)) {
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => 'Data loaded successfully',
                'trends_loaded' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No data loaded or trends empty'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'load_directory':
                $count = $analyzer->loadCSVFiles($dataDirectory, true);
                $trends = $analyzer->getTrends();
                $message = "Loaded " . number_format($count) . " records successfully!";
                break;
            
            case 'clear_cache':
                $analyzer->clearCache();
                $message = "Cache cleared successfully!";
                break;
                
            case 'search':
                $criteria = [
                    'InmateName' => $_POST['inmate_name'] ?? '',
                    'OffenseDescription' => $_POST['offense'] ?? '',
                    'Court' => $_POST['court'] ?? '',
                    'AttorneyName' => $_POST['attorney'] ?? '',
                    'ReleaseType' => $_POST['release_type'] ?? '',
                    'BondType' => $_POST['bond_type'] ?? '',
                    'ReleaseDate' => $_POST['release_date'] ?? '',
                ];
                $searchResults = $analyzer->search($dataDirectory, $criteria);
                break;
        }
    }
}

$cacheExists = file_exists($analyzer->getCacheFile());

if (!isset($searchResults)) {
    if ($cacheExists) {
        $count = $analyzer->loadCSVFiles($dataDirectory, false);
        if ($count > 0) {
            $trends = $analyzer->getTrends();
        }
    }
}

$trendsLoaded = isset($trends) && !empty($trends);
$topN = isset($_GET['top_n']) ? max(5, min(100, intval($_GET['top_n']))) : 10;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jail Release Analysis System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        h2 { color: #555; margin: 30px 0 15px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h3 { color: #666; margin: 20px 0 10px; }
        
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .btn-group { display: flex; gap: 10px; }
        .btn-secondary { background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-secondary:hover { background: #545b62; }
        
        .tabs { display: flex; gap: 10px; margin: 20px 0; border-bottom: 2px solid #ddd; flex-wrap: wrap; }
        .tab { padding: 10px 20px; cursor: pointer; background: #f0f0f0; border: none; border-radius: 4px 4px 0 0; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        
        .search-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0; }
        
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stat-value { font-size: 32px; font-weight: bold; }
        .stat-label { margin-top: 5px; opacity: 0.9; }
        
        .trend-list { list-style: none; max-height: 400px; overflow-y: auto; }
        .trend-list li { padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .trend-list li:hover { background: #f9f9f9; }
        
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; font-weight: bold; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        .table-container { max-height: 600px; overflow-y: auto; }
        
        .chart-container { margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 6px; }
        .bar { background: linear-gradient(90deg, #007bff 0%, #0056b3 100%); height: 30px; margin: 5px 0; border-radius: 3px; position: relative; min-width: 50px; cursor: pointer; transition: transform 0.2s; }
        .bar:hover { transform: translateX(5px); box-shadow: 0 2px 8px rgba(0,123,255,0.3); }
        .bar-label { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: white; font-weight: bold; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 20px); }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; border-radius: 8px; width: 80%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #007bff; }
        .modal-header h3 { margin: 0; color: #333; }
        .close { color: #aaa; font-size: 32px; font-weight: bold; cursor: pointer; line-height: 1; }
        .close:hover, .close:focus { color: #000; }
        .modal-body { font-size: 16px; line-height: 1.6; }
        .modal-stat { display: flex; justify-content: space-between; padding: 10px; background: #f8f9fa; margin: 10px 0; border-radius: 4px; }
        .modal-stat-label { font-weight: bold; color: #555; }
        .modal-stat-value { color: #007bff; font-weight: bold; }
        
        .alert { padding: 15px; margin: 20px 0; border-radius: 4px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .docker-badge { display: inline-block; background: #2496ed; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-left: 10px; }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,123,255,.3);
            border-radius: 50%;
            border-top-color: #007bff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .container { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .search-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <div>
                <h1>Jail Release Analysis <span class="docker-badge">Docker</span></h1>
                <p style="color: #666; margin-top: 5px;">Analyze trends across <?php echo count(glob($dataDirectory . '/*.csv')); ?> CSV files</p>
            </div>
            <div class="btn-group">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="load_directory">
                    <button type="submit" class="btn-secondary">Reload Data</button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="btn-secondary">Clear Cache</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($trendsLoaded): ?>
            <div class="alert">
                Data loaded: <?php echo number_format($count ?? 0); ?> records from cache
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('trends')">Trends</button>
            <button class="tab" onclick="showTab('search')">Search</button>
        </div>
        
        <div id="trends-tab" class="tab-content active">
            <?php if ($trendsLoaded && isset($trends) && !empty($trends)): ?>
                <h2>Overview Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="stat-value"><?php echo number_format($trends['total_releases']); ?></div>
                        <div class="stat-label">Total Release Records</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="stat-value"><?php echo number_format($trends['unique_inmates']); ?></div>
                        <div class="stat-label">Unique Individuals</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="stat-value"><?php echo number_format($trends['unique_cases']); ?></div>
                        <div class="stat-label">Unique Cases</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <div class="stat-value">$<?php echo number_format($trends['average_bond'], 0); ?></div>
                        <div class="stat-label">Average Bond</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="stat-value"><?php echo count($trends['offense_types']); ?></div>
                        <div class="stat-label">Offense Types</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                        <div class="stat-value"><?php echo count($trends['releases_by_month']); ?></div>
                        <div class="stat-label">Months Tracked</div>
                    </div>
                </div>
                
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <strong>Data Breakdown:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><strong><?php echo number_format($trends['total_releases']); ?></strong> total release records (each charge/booking creates a separate record)</li>
                        <li><strong><?php echo number_format($trends['unique_inmates']); ?></strong> unique individuals (same person may have multiple release records)</li>
                        <li><strong><?php echo number_format($trends['unique_cases']); ?></strong> unique case numbers</li>
                        <li>Average: <strong><?php echo number_format($trends['total_releases'] / max($trends['unique_inmates'], 1), 2); ?></strong> release records per person</li>
                    </ul>
                </div>
                
                <h2>Bond Amount Ranges</h2>
                <ul class="trend-list">
                    <?php foreach ($trends['bond_ranges'] as $range => $count): ?>
                        <li>
                            <span><strong>$<?php echo $range; ?></strong></span>
                            <strong><?php echo number_format($count); ?> releases</strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <h2>
                    Top Offenses
                    <select onchange="updateTopN(this.value)" style="float: right; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="5" <?php echo $topN === 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $topN === 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="20" <?php echo $topN === 20 ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo $topN === 50 ? 'selected' : ''; ?>>Top 50</option>
                        <option value="100" <?php echo $topN === 100 ? 'selected' : ''; ?>>Top 100</option>
                    </select>
                </h2>
                <div class="chart-container">
                <?php 
                $topOffenses = array_slice($trends['offense_types'], 0, $topN, true);
                if (!empty($topOffenses)) {
                    $maxOffense = max($topOffenses);
                    foreach ($topOffenses as $offense => $count) {
                        $width = ($count / $maxOffense) * 100;
                        $fullText = htmlspecialchars($offense);
                        $shortText = htmlspecialchars(substr($offense, 0, 50));
                        echo '<div class="bar" style="width: ' . $width . '%;" onclick="showModal(\'' . addslashes($fullText) . '\', ' . $count . ', \'Offense\')">';
                        echo '<span class="bar-label">' . $shortText . ' (' . number_format($count) . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
                
                <h2>
                    Top Courts
                    <select onchange="updateTopN(this.value)" style="float: right; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="5" <?php echo $topN === 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $topN === 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="20" <?php echo $topN === 20 ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo $topN === 50 ? 'selected' : ''; ?>>Top 50</option>
                        <option value="100" <?php echo $topN === 100 ? 'selected' : ''; ?>>Top 100</option>
                    </select>
                </h2>
                <div class="chart-container">
                <?php 
                $topCourts = array_slice($trends['courts'], 0, $topN, true);
                if (!empty($topCourts)) {
                    $maxCourt = max($topCourts);
                    foreach ($topCourts as $court => $count) {
                        $width = ($count / $maxCourt) * 100;
                        $fullText = htmlspecialchars($court);
                        $shortText = htmlspecialchars(substr($court, 0, 50));
                        echo '<div class="bar" style="width: ' . $width . '%;" onclick="showModal(\'' . addslashes($fullText) . '\', ' . $count . ', \'Court\')">';
                        echo '<span class="bar-label">' . $shortText . ' (' . number_format($count) . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
                
                <h2>
                    Top Attorneys
                    <select onchange="updateTopN(this.value)" style="float: right; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="5" <?php echo $topN === 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $topN === 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="20" <?php echo $topN === 20 ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo $topN === 50 ? 'selected' : ''; ?>>Top 50</option>
                        <option value="100" <?php echo $topN === 100 ? 'selected' : ''; ?>>Top 100</option>
                    </select>
                </h2>
                <div class="chart-container">
                <?php 
                $topAttorneys = array_slice($trends['attorneys'], 0, $topN, true);
                if (!empty($topAttorneys)) {
                    $maxAttorney = max($topAttorneys);
                    foreach ($topAttorneys as $attorney => $count) {
                        $width = ($count / $maxAttorney) * 100;
                        $fullText = htmlspecialchars($attorney);
                        $shortText = htmlspecialchars(substr($attorney, 0, 50));
                        echo '<div class="bar" style="width: ' . $width . '%;" onclick="showModal(\'' . addslashes($fullText) . '\', ' . $count . ', \'Attorney\')">';
                        echo '<span class="bar-label">' . $shortText . ' (' . number_format($count) . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
                
                <h2>
                    Top Bond Types
                    <select onchange="updateTopN(this.value)" style="float: right; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="5" <?php echo $topN === 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $topN === 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="20" <?php echo $topN === 20 ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo $topN === 50 ? 'selected' : ''; ?>>Top 50</option>
                        <option value="100" <?php echo $topN === 100 ? 'selected' : ''; ?>>Top 100</option>
                    </select>
                </h2>
                <div class="chart-container">
                <?php 
                $topBondTypes = array_slice($trends['bond_types'], 0, $topN, true);
                if (!empty($topBondTypes)) {
                    $maxBondType = max($topBondTypes);
                    foreach ($topBondTypes as $bondType => $count) {
                        $width = ($count / $maxBondType) * 100;
                        $fullText = htmlspecialchars($bondType);
                        $shortText = htmlspecialchars(substr($bondType, 0, 50));
                        echo '<div class="bar" style="width: ' . $width . '%;" onclick="showModal(\'' . addslashes($fullText) . '\', ' . $count . ', \'Bond Type\')">';
                        echo '<span class="bar-label">' . $shortText . ' (' . number_format($count) . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
                
                <h2>
                    Busiest Release Days
                    <select onchange="updateTopN(this.value)" style="float: right; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <option value="5" <?php echo $topN === 5 ? 'selected' : ''; ?>>Top 5</option>
                        <option value="10" <?php echo $topN === 10 ? 'selected' : ''; ?>>Top 10</option>
                        <option value="20" <?php echo $topN === 20 ? 'selected' : ''; ?>>Top 20</option>
                        <option value="50" <?php echo $topN === 50 ? 'selected' : ''; ?>>Top 50</option>
                        <option value="100" <?php echo $topN === 100 ? 'selected' : ''; ?>>Top 100</option>
                    </select>
                </h2>
                <div class="chart-container">
                <?php 
                $busiestDays = array_slice($trends['releases_by_date'], 0, $topN, true);
                if (!empty($busiestDays)) {
                    $maxDay = max($busiestDays);
                    foreach ($busiestDays as $date => $count) {
                        $width = ($count / $maxDay) * 100;
                        echo '<div class="bar" style="width: ' . $width . '%;">';
                        echo '<span class="bar-label">' . htmlspecialchars($date) . ' (' . number_format($count) . ' releases)</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
                
                <h2>Release Types</h2>
                <ul class="trend-list">
                    <?php foreach (array_slice($trends['release_types'], 0, 20, true) as $type => $count): ?>
                        <li>
                            <span><?php echo htmlspecialchars($type); ?></span>
                            <strong><?php echo number_format($count); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <h2>Monthly Release Trends</h2>
                <div class="chart-container">
                <?php 
                if (!empty($trends['releases_by_month'])) {
                    $maxMonthly = max($trends['releases_by_month']);
                    foreach ($trends['releases_by_month'] as $month => $count) {
                        $width = ($count / $maxMonthly) * 100;
                        echo '<div class="bar" style="width: ' . $width . '%;">';
                        echo '<span class="bar-label">' . htmlspecialchars($month) . ' (' . number_format($count) . ')</span>';
                        echo '</div>';
                    }
                }
                ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info" id="loading-message">
                    <div style="display: flex; align-items: center; gap: 15px; flex-direction: column; align-items: flex-start;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="loading-spinner" style="display: none;" id="spinner"></div>
                            <div>
                                <strong>Data Not Loaded</strong>
                                <p style="margin: 5px 0 0 0;" id="loading-status">
                                    Found <?php echo count(glob($dataDirectory . '/*.csv')); ?> CSV files ready to process.
                                </p>
                            </div>
                        </div>
                        <button onclick="startLoading()" id="load-button" style="background: #28a745; padding: 10px 20px;">
                            Start Loading Data
                        </button>
                    </div>
                </div>
                <script>
                    function startLoading() {
                        document.getElementById('spinner').style.display = 'inline-block';
                        document.getElementById('load-button').disabled = true;
                        document.getElementById('load-button').textContent = 'Loading...';
                        document.getElementById('loading-status').textContent = 'Processing CSV files... This may take 2-5 minutes.';
                        
                        const startTime = Date.now();
                        
                        fetch('?ajax_load=1', { 
                            method: 'GET',
                            cache: 'no-cache'
                        })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('HTTP error ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                const elapsed = Math.round((Date.now() - startTime) / 1000);
                                
                                if (data.success) {
                                    document.getElementById('loading-status').textContent = 
                                        `Loaded ${data.count.toLocaleString()} records in ${elapsed} seconds. Refreshing...`;
                                    document.getElementById('load-button').textContent = 'Complete';
                                    document.getElementById('spinner').style.display = 'none';
                                    
                                    setTimeout(() => {
                                        window.location.href = window.location.pathname + '?t=' + Date.now();
                                    }, 1500);
                                } else {
                                    document.getElementById('loading-status').textContent = 'Error: ' + data.error;
                                    document.getElementById('load-button').disabled = false;
                                    document.getElementById('load-button').textContent = 'Retry';
                                    document.getElementById('spinner').style.display = 'none';
                                }
                            })
                            .catch(error => {
                                document.getElementById('loading-status').textContent = 
                                    'Error loading data: ' + error.message + '. Check Docker logs: docker-compose logs';
                                document.getElementById('load-button').disabled = false;
                                document.getElementById('load-button').textContent = 'Retry';
                                document.getElementById('spinner').style.display = 'none';
                            });
                    }
                </script>
            <?php endif; ?>
        </div>
        
        <div id="search-tab" class="tab-content">
            <h2>Search Records</h2>
            
            <?php if (isset($trends) && !empty($trends['busiest_days'])): ?>
                <div style="background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <strong>Sample dates in your data:</strong>
                    <?php 
                    $sampleDates = array_slice(array_keys($trends['busiest_days']), 0, 5);
                    echo implode(', ', array_map('htmlspecialchars', $sampleDates));
                    ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="search">
                
                <div class="search-grid">
                    <div class="form-group">
                        <label>Inmate Name:</label>
                        <input type="text" name="inmate_name" placeholder="Search by name" value="<?php echo htmlspecialchars($_POST['inmate_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Offense:</label>
                        <input type="text" name="offense" placeholder="Search by offense" value="<?php echo htmlspecialchars($_POST['offense'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Court:</label>
                        <input type="text" name="court" placeholder="Search by court" value="<?php echo htmlspecialchars($_POST['court'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Attorney:</label>
                        <input type="text" name="attorney" placeholder="Search by attorney" value="<?php echo htmlspecialchars($_POST['attorney'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Release Type:</label>
                        <input type="text" name="release_type" placeholder="Search by release type" value="<?php echo htmlspecialchars($_POST['release_type'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bond Type:</label>
                        <input type="text" name="bond_type" placeholder="Search by bond type" value="<?php echo htmlspecialchars($_POST['bond_type'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Release Date:</label>
                        <input type="date" name="release_date" value="<?php echo htmlspecialchars($_POST['release_date'] ?? ''); ?>">
                        <small style="color: #666; display: block; margin-top: 5px;">Or enter partial: 2025-10, 2025, etc.</small>
                    </div>
                </div>
                
                <button type="submit">Search</button>
            </form>
            
            <?php if (isset($searchResults)): ?>
                <h3>Search Results: <?php echo count($searchResults); ?> records found</h3>
                
                <?php if (isset($_POST['release_date']) && !empty($_POST['release_date']) && count($searchResults) === 0): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px;">
                        <strong>No results for date: <?php echo htmlspecialchars($_POST['release_date']); ?></strong>
                        <p style="margin-top: 10px;">Try these formats:</p>
                        <ul style="margin-left: 20px; margin-top: 5px;">
                            <li>Full date: 10/02/2025 or 2025-10-02</li>
                            <li>Month: 10/2025 or 2025-10</li>
                            <li>Year: 2025</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (count($searchResults) > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Inmate Name</th>
                                    <th>Offense</th>
                                    <th>Release Date</th>
                                    <th>Release Type</th>
                                    <th>Bond Type</th>
                                    <th>Bond Amount</th>
                                    <th>Court</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['InmateName']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($result['OffenseDescription'], 0, 50)); ?></td>
                                        <td><?php echo htmlspecialchars($result['ReleaseDate']); ?></td>
                                        <td><?php echo htmlspecialchars($result['ReleaseType']); ?></td>
                                        <td><?php echo htmlspecialchars($result['BondType']); ?></td>
                                        <td>$<?php echo number_format(floatval($result['BondAmount']), 2); ?></td>
                                        <td><?php echo htmlspecialchars($result['Court']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="color: #666; margin-top: 10px;">Showing maximum 100 results for performance</p>
                <?php else: ?>
                    <p style="color: #666; margin-top: 20px;">No records found matching your criteria.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal for displaying full text -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-stat">
                    <span class="modal-stat-label" id="modalLabel">Item:</span>
                    <span class="modal-stat-value" id="modalText"></span>
                </div>
                <div class="modal-stat">
                    <span class="modal-stat-label">Count:</span>
                    <span class="modal-stat-value" id="modalCount"></span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function updateTopN(value) {
            const url = new URL(window.location);
            url.searchParams.set('top_n', value);
            window.location = url.toString();
        }
        
        function showModal(text, count, type) {
            document.getElementById('modalTitle').textContent = type + ' Details';
            document.getElementById('modalLabel').textContent = type + ':';
            document.getElementById('modalText').textContent = text;
            document.getElementById('modalCount').textContent = count.toLocaleString() + ' occurrences';
            document.getElementById('detailModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>