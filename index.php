<?php
require_once 'assets/php/connect.php';
require_once 'assets/php/nav.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$namespace = isset($_GET['namespace']) ? trim($_GET['namespace']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? min(200, max(1, (int)$_GET['per_page'])) : 50;
$offset = ($page - 1) * $perPage;
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Build the query
$sql = "SELECT 
            n.id,
            n.hash,
            n.name,
            n.return_type,
            n.params,
            n.comment,
            n.build,
            n.gta_hash,
            n.gta_jhash,
            ns.name as namespace
        FROM natives n
        JOIN namespaces ns ON n.namespace_id = ns.id
        WHERE 1=1";

$params = [];

// Namespace filter
if (!empty($namespace)) {
    $sql .= " AND ns.name = ?";
    $params[] = $namespace;
}

// Search term
if (!empty($search)) {
    $terms = explode(' ', $search);
    $whereConditions = [];
    
    foreach ($terms as $term) {
        if (strlen($term) < 2) continue;
        $term = '%' . $term . '%';
        $whereConditions[] = "(n.name LIKE ? OR ns.name LIKE ? OR n.comment LIKE ? OR n.hash LIKE ?)";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    
    if (!empty($whereConditions)) {
        $sql .= " AND (" . implode(' OR ', $whereConditions) . ")";
    }
}

// Get total count
$countSql = str_replace(
    "SELECT 
            n.id,
            n.hash,
            n.name,
            n.return_type,
            n.params,
            n.comment,
            n.build,
            n.gta_hash,
            n.gta_jhash,
            ns.name as namespace",
    "SELECT COUNT(*) as total",
    $sql
);

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];

// Get paginated results
$sql .= " ORDER BY ns.name, n.name LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Get selected native details
$selectedNative = null;
if ($selectedId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.hash,
            n.name,
            n.return_type,
            n.params,
            n.comment,
            n.build,
            n.gta_hash,
            n.gta_jhash,
            ns.name as namespace
        FROM natives n
        JOIN namespaces ns ON n.namespace_id = ns.id
        WHERE n.id = ?
    ");
    $stmt->execute([$selectedId]);
    $selectedNative = $stmt->fetch();
} elseif (!empty($results)) {
    // Auto-select first result
    $selectedNative = $results[0];
    $selectedId = $selectedNative['id'];
}

// Get all namespaces for dropdown
$namespaceStmt = $pdo->query("SELECT id, name, native_count FROM namespaces ORDER BY name");
$namespaces = $namespaceStmt->fetchAll();

// Stats
$statsStmt = $pdo->query("SELECT COUNT(*) as total FROM natives");
$totalNatives = $statsStmt->fetch()['total'];

// Format params for display
function formatParams($paramsJson) {
    if (empty($paramsJson)) return [];
    $params = json_decode($paramsJson, true);
    if (!is_array($params)) return [];
    return $params;
}

// Format signature
function formatSignature($name, $namespace, $params) {
    $paramStr = '';
    if (!empty($params)) {
        $paramParts = array_map(function($p) {
            return $p['type'] . ' ' . $p['name'];
        }, $params);
        $paramStr = implode(', ', $paramParts);
    }
    return $namespace . '::' . $name . '(' . $paramStr . ')';
}

// Get return type description
function getReturnDescription($type) {
    if (empty($type)) return 'void';
    $desc = [
        'BOOL' => 'Boolean (true/false)',
        'int' => 'Integer (number)',
        'float' => 'Float (decimal)',
        'void' => 'Void (no return)',
        'Hash' => 'Hash (integer)',
        'Ped' => 'Ped handle',
        'Vehicle' => 'Vehicle handle',
        'Entity' => 'Entity handle',
        'Object' => 'Object handle',
        'ScrHandle' => 'Script handle',
        'Any' => 'Any type',
        'const char*' => 'String (text)',
        'Vector3*' => 'Vector3 pointer',
        'Any*' => 'Pointer to any type',
        'int*' => 'Integer pointer',
        'float*' => 'Float pointer',
    ];
    return isset($desc[$type]) ? $desc[$type] : $type;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>RDR3 NativeDB Explorer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #0a0e1a;
            --bg-secondary: #111827;
            --bg-card: #1a233a;
            --bg-hover: #24304a;
            --bg-detail: #0f1525;
            --bg-nav: #0d1322;
            --text-primary: #e8edf5;
            --text-secondary: #8a9bb5;
            --text-muted: #5a6b85;
            --text-accent: #a78bfa;
            --accent: #6c8cff;
            --accent-hover: #5a7ae0;
            --accent-glow: rgba(108, 140, 255, 0.15);
            --border-color: #2a3a5a;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
            --radius: 12px;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --nav-height: 50px;
            --header-height: 110px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Navigation */
        .navbar {
            background: var(--bg-nav);
            border-bottom: 1px solid var(--border-color);
            padding: 0 20px;
            height: var(--nav-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            position: relative;
            z-index: 100;
        }
        
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .nav-brand .brand-icon {
            font-size: 20px;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }
        
        .nav-links li {
            position: relative;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .nav-links a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .nav-links a.active {
            background: var(--accent);
            color: white;
        }
        
        .nav-links a .nav-badge {
            font-size: 10px;
            background: var(--danger);
            color: white;
            padding: 1px 8px;
            border-radius: 12px;
            margin-left: 4px;
        }
        
        /* Dropdown - Fixed hover behavior */
        .nav-dropdown {
            position: relative;
        }
        
        .nav-dropdown .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 0;
            min-width: 200px;
            box-shadow: var(--shadow);
            margin-top: 4px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.25s ease;
            pointer-events: none;
        }
        
        /* Show dropdown on hover of the parent li */
        .nav-dropdown:hover .dropdown-menu,
        .nav-dropdown:focus-within .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        /* Keep dropdown visible when hovering over the menu itself */
        .nav-dropdown .dropdown-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .nav-dropdown .dropdown-menu a {
            padding: 8px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            display: block;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .nav-dropdown .dropdown-menu a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        /* For touch devices - click to toggle */
        .nav-dropdown .dropdown-toggle {
            cursor: pointer;
        }
        
        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 6px;
            background: none;
            border: none;
        }
        
        .hamburger span {
            display: block;
            width: 24px;
            height: 2px;
            background: var(--text-secondary);
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }
        
        /* Mobile Nav Overlay */
        .nav-overlay {
            display: none;
            position: fixed;
            top: var(--nav-height);
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99;
            backdrop-filter: blur(4px);
        }
        
        .nav-overlay.open {
            display: block;
        }
        
        .nav-mobile {
            display: none;
            position: fixed;
            top: var(--nav-height);
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 20px;
            z-index: 100;
            max-height: calc(100vh - var(--nav-height));
            overflow-y: auto;
            box-shadow: var(--shadow);
        }
        
        .nav-mobile.open {
            display: block;
        }
        
        .nav-mobile a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .nav-mobile a:hover,
        .nav-mobile a.active {
            background: var(--bg-hover);
            color: var(--text-primary);
        }
        
        .nav-mobile .mobile-submenu {
            padding-left: 20px;
            border-left: 2px solid var(--border-color);
            margin: 4px 0 4px 12px;
        }
        
        .nav-mobile .mobile-submenu a {
            font-size: 13px;
            padding: 6px 12px;
        }
        
        .nav-mobile .nav-divider {
            height: 1px;
            background: var(--border-color);
            margin: 8px 0;
        }
        
        /* Header */
        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 10px 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .header-stats {
            display: flex;
            gap: 16px;
        }
        
        .header-stats .stat {
            font-size: 11px;
            color: var(--text-secondary);
            text-align: center;
        }
        
        .header-stats .stat strong {
            display: block;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .search-row {
            display: flex;
            gap: 8px;
            flex: 1;
            max-width: 600px;
            min-width: 180px;
            align-items: center;
        }
        
        .search-wrapper {
            flex: 1;
            position: relative;
            min-width: 120px;
        }
        
        .search-wrapper input {
            width: 100%;
            padding: 8px 14px 8px 36px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-wrapper input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        .search-wrapper input::placeholder {
            color: var(--text-muted);
        }
        
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            pointer-events: none;
        }
        
        .search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            cursor: pointer;
            display: none;
            background: none;
            border: none;
            padding: 0;
        }
        
        .search-clear.visible {
            display: block;
        }
        
        .search-clear:hover {
            color: var(--text-primary);
        }
        
        .filter-select {
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 13px;
            cursor: pointer;
            min-width: 120px;
            max-width: 180px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238a9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 32px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        .filter-select option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        /* Main Layout */
        .main-layout {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Left Panel - Native List */
        .left-panel {
            width: 42%;
            min-width: 280px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: var(--bg-secondary);
        }
        
        .panel-header {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .panel-header .title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .panel-header .count {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .native-list {
            flex: 1;
            overflow-y: auto;
            padding: 4px 0;
        }
        
        .native-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .native-list::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        
        .native-list::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        .native-item {
            padding: 8px 16px;
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: all 0.15s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            min-height: 40px;
        }
        
        .native-item:hover {
            background: var(--bg-hover);
        }
        
        .native-item.active {
            background: var(--bg-card);
            border-left-color: var(--accent);
        }
        
        .native-item .item-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        
        .native-item .item-namespace {
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .native-item .item-hash {
            font-size: 10px;
            color: var(--text-muted);
            font-family: monospace;
            background: var(--bg-primary);
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        /* Right Panel - Native Detail */
        .right-panel {
            flex: 1;
            overflow-y: auto;
            background: var(--bg-detail);
            padding: 20px 28px;
        }
        
        .right-panel::-webkit-scrollbar {
            width: 6px;
        }
        
        .right-panel::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        
        .right-panel::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        .detail-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            text-align: center;
        }
        
        .detail-empty .icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .detail-empty h2 {
            font-size: 20px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .detail-empty p {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .detail-header {
            margin-bottom: 20px;
        }
        
        .detail-namespace {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .detail-name {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            word-break: break-word;
            margin-bottom: 4px;
        }
        
        .detail-signature {
            font-size: 14px;
            font-family: 'Courier New', monospace;
            color: var(--text-secondary);
            background: var(--bg-primary);
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .detail-hash {
            font-size: 13px;
            color: var(--text-muted);
            font-family: monospace;
            display: inline-block;
            background: var(--bg-primary);
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            margin-bottom: 12px;
        }
        
        .detail-hash:hover {
            border-color: var(--accent);
        }
        
        .detail-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 14px 18px;
            margin-bottom: 14px;
        }
        
        .detail-section .section-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .detail-section .section-title .badge {
            font-size: 11px;
            font-weight: 400;
            color: var(--text-muted);
            background: var(--bg-primary);
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .param-item {
            display: flex;
            gap: 12px;
            padding: 5px 0;
            border-bottom: 1px solid rgba(42, 58, 90, 0.3);
            font-size: 13px;
        }
        
        .param-item:last-child {
            border-bottom: none;
        }
        
        .param-type {
            color: var(--success);
            font-weight: 500;
            min-width: 80px;
            font-family: 'Courier New', monospace;
        }
        
        .param-name {
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
        }
        
        .return-value {
            font-size: 15px;
            padding: 6px 0;
        }
        
        .return-value .type {
            color: var(--warning);
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .return-value .desc {
            color: var(--text-secondary);
            font-size: 13px;
            margin-left: 8px;
        }
        
        .detail-comment {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .detail-comment strong {
            color: var(--text-primary);
        }
        
        .detail-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        
        .detail-actions .btn {
            font-size: 12px;
            padding: 6px 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }
        
        .btn:hover {
            background: var(--bg-hover);
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }
        
        .btn-success {
            background: var(--success);
            color: #0a0e1a;
            border-color: var(--success);
        }
        
        .btn-success:hover {
            opacity: 0.8;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 4px;
            padding: 8px 16px;
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        .pagination .btn {
            font-size: 12px;
            padding: 4px 10px;
            min-width: 32px;
            text-align: center;
        }
        
        .pagination .btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }
        
        .pagination .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            border-radius: var(--radius);
            color: var(--text-primary);
            box-shadow: var(--shadow);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            z-index: 999;
            font-size: 14px;
            max-width: 90vw;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        /* Loading/Empty states */
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            padding: 40px 20px;
        }
        
        .loading-state .icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        
        /* Responsive - Mobile */
        @media (max-width: 768px) {
            body {
                overflow: auto;
                height: auto;
            }
            
            /* Navigation */
            .nav-links {
                display: none;
            }
            
            .hamburger {
                display: flex;
            }
            
            .navbar {
                padding: 0 16px;
            }
            
            .nav-brand {
                font-size: 14px;
            }
            
            .nav-brand .brand-icon {
                font-size: 18px;
            }
            
            /* Header */
            .header {
                padding: 8px 14px;
                gap: 6px;
            }
            
            .header-top {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .header-stats .stat strong {
                font-size: 12px;
            }
            
            .header-stats .stat {
                font-size: 10px;
            }
            
            .search-row {
                max-width: 100%;
                width: 100%;
                flex-wrap: wrap;
            }
            
            .search-wrapper {
                flex: 1;
                min-width: 100px;
            }
            
            .filter-select {
                min-width: 100px;
                max-width: 100%;
                flex: 1;
            }
            
            /* Layout */
            .main-layout {
                flex-direction: column;
                height: auto;
            }
            
            .left-panel {
                width: 100%;
                min-width: unset;
                max-height: 45vh;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .right-panel {
                padding: 14px 16px;
                min-height: 50vh;
            }
            
            .detail-name {
                font-size: 20px;
            }
            
            .detail-signature {
                font-size: 12px;
                padding: 8px 12px;
            }
            
            .native-item {
                padding: 6px 12px;
                min-height: 36px;
            }
            
            .native-item .item-name {
                font-size: 12px;
            }
            
            .native-item .item-hash {
                font-size: 9px;
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                padding: 0 10px;
                height: 44px;
            }
            
            .header {
                padding: 6px 10px;
            }
            
            .header-stats {
                gap: 8px;
            }
            
            .right-panel {
                padding: 10px 12px;
                min-height: 40vh;
            }
            
            .detail-name {
                font-size: 17px;
            }
            
            .detail-section {
                padding: 10px 12px;
            }
            
            .param-item {
                font-size: 12px;
                flex-wrap: wrap;
                gap: 4px;
            }
            
            .param-type {
                min-width: 60px;
                font-size: 11px;
            }
            
            .param-name {
                font-size: 11px;
            }
            
            .detail-signature {
                font-size: 11px;
                padding: 6px 10px;
            }
            
            .detail-actions .btn {
                font-size: 11px;
                padding: 4px 10px;
            }
            
            .pagination .btn {
                font-size: 11px;
                padding: 3px 8px;
                min-width: 28px;
            }
            
            .search-wrapper input {
                font-size: 13px;
                padding: 6px 10px 6px 32px;
            }
            
            .filter-select {
                font-size: 12px;
                padding: 6px 10px;
                min-width: 80px;
            }
            
            .toast {
                font-size: 12px;
                padding: 10px 16px;
                bottom: 16px;
                right: 16px;
            }
        }
        
        /* Scrollbar global */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        ::selection {
            background: var(--accent);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="rdr2nativedb.php" class="nav-brand">
            <span class="brand-icon">🎮</span>
            RDR3 NativeDB
        </a>
        
        <ul class="nav-links">
            <?php foreach ($navItems as $key => $item): ?>
                <li class="<?php echo isset($item['submenu']) ? 'nav-dropdown' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                        <?php echo $item['label']; ?>
                        <?php if (isset($item['badge'])): ?>
                            <span class="nav-badge"><?php echo $item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php if (isset($item['submenu'])): ?>
                        <div class="dropdown-menu">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <a href="<?php echo $sub['url']; ?>"><?php echo $sub['label']; ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>
    
    <!-- Mobile Navigation -->
    <div class="nav-overlay" id="navOverlay"></div>
    <div class="nav-mobile" id="navMobile">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                <?php echo $item['label']; ?>
                <?php if (isset($item['badge'])): ?>
                    <span class="nav-badge"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </a>
            <?php if (isset($item['submenu'])): ?>
                <div class="mobile-submenu">
                    <?php foreach ($item['submenu'] as $sub): ?>
                        <a href="<?php echo $sub['url']; ?>"><?php echo $sub['label']; ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($key !== array_key_last($navItems)): ?>
                <div class="nav-divider"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Header -->
    <header class="header">
        <div class="header-top">
            <div class="header-stats">
                <div class="stat">
                    <strong><?php echo number_format($totalNatives); ?></strong>
                    Natives
                </div>
                <div class="stat">
                    <strong><?php echo number_format(count($namespaces)); ?></strong>
                    Namespaces
                </div>
                <div class="stat">
                    <strong id="resultCount"><?php echo number_format($total); ?></strong>
                    Results
                </div>
            </div>
        </div>
        
        <div class="search-row">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search natives, keywords, hashes..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off" />
                <button class="search-clear" id="searchClear" onclick="clearSearch()">✕</button>
            </div>
            <select class="filter-select" id="namespaceSelect">
                <option value="">All Namespaces</option>
                <?php foreach ($namespaces as $ns): ?>
                    <option value="<?php echo htmlspecialchars($ns['name']); ?>" <?php echo $namespace === $ns['name'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ns['name']); ?> (<?php echo $ns['native_count']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Left Panel -->
        <div class="left-panel">
            <div class="panel-header">
                <span class="title">📋 Natives</span>
                <span class="count" id="listCount"><?php echo number_format($total); ?> total</span>
            </div>
            
            <div class="native-list" id="nativeList">
                <?php if (empty($results)): ?>
                    <div class="loading-state">
                        <div class="icon">🔍</div>
                        <p>No results found</p>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:4px;">Try adjusting your search</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($results as $native): ?>
                        <div class="native-item <?php echo $native['id'] == $selectedId ? 'active' : ''; ?>" 
                             data-id="<?php echo $native['id']; ?>"
                             onclick="selectNative(<?php echo $native['id']; ?>)">
                            <span class="item-name"><?php echo htmlspecialchars($native['name']); ?></span>
                            <span class="item-namespace"><?php echo htmlspecialchars($native['namespace']); ?></span>
                            <span class="item-hash"><?php echo substr($native['hash'], 0, 10); ?>…</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($total > $perPage): 
                $totalPages = ceil($total / $perPage);
                $queryParams = $_GET;
                unset($queryParams['page']);
                $baseUrl = '?' . http_build_query($queryParams);
            ?>
            <div class="pagination" id="pagination">
                <a href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>" class="btn" <?php echo $page <= 1 ? 'disabled' : ''; ?>>‹</a>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <a href="<?php echo $baseUrl . '&page=1'; ?>" class="btn">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="btn" disabled>…</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?php echo $baseUrl . '&page=' . $i; ?>" class="btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="btn" disabled>…</span>
                    <?php endif; ?>
                    <a href="<?php echo $baseUrl . '&page=' . $totalPages; ?>" class="btn"><?php echo $totalPages; ?></a>
                <?php endif; ?>
                
                <a href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>" class="btn" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>›</a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Panel - Detail View -->
        <div class="right-panel" id="detailPanel">
            <?php if ($selectedNative): 
                $params = formatParams($selectedNative['params']);
                $signature = formatSignature($selectedNative['name'], $selectedNative['namespace'], $params);
                $returnDesc = getReturnDescription($selectedNative['return_type']);
            ?>
                <div class="detail-header">
                    <div class="detail-namespace"><?php echo htmlspecialchars($selectedNative['namespace']); ?></div>
                    <div class="detail-name"><?php echo htmlspecialchars($selectedNative['name']); ?></div>
                    <div class="detail-signature"><?php echo htmlspecialchars($signature); ?></div>
                    <div class="detail-hash" onclick="copyText('<?php echo htmlspecialchars($selectedNative['hash']); ?>')">
                        <?php echo htmlspecialchars($selectedNative['hash']); ?> 📋
                    </div>
                </div>
                
                <?php if (!empty($params)): ?>
                <div class="detail-section">
                    <div class="section-title">
                        📝 Parameters
                        <span class="badge"><?php echo count($params); ?></span>
                    </div>
                    <?php foreach ($params as $param): ?>
                        <div class="param-item">
                            <span class="param-type"><?php echo htmlspecialchars($param['type']); ?></span>
                            <span class="param-name"><?php echo htmlspecialchars($param['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="detail-section">
                    <div class="section-title">↩️ Return Value</div>
                    <div class="return-value">
                        <span class="type"><?php echo htmlspecialchars($selectedNative['return_type'] ?: 'void'); ?></span>
                        <span class="desc">— <?php echo htmlspecialchars($returnDesc); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($selectedNative['comment'])): ?>
                <div class="detail-section">
                    <div class="section-title">💬 Comment</div>
                    <div class="detail-comment"><?php echo nl2br(htmlspecialchars($selectedNative['comment'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-section">
                    <div class="section-title">ℹ️ Metadata</div>
                    <div style="display:grid; grid-template-columns: auto 1fr; gap: 4px 16px; font-size:13px; color:var(--text-secondary);">
                        <span style="font-weight:500;">Build:</span>
                        <span><?php echo htmlspecialchars($selectedNative['build'] ?: 'N/A'); ?></span>
                        
                        <?php if (!empty($selectedNative['gta_hash'])): ?>
                        <span style="font-weight:500;">GTA Hash:</span>
                        <span><?php echo htmlspecialchars($selectedNative['gta_hash']); ?></span>
                        <?php endif; ?>
                        
                        <?php if (!empty($selectedNative['gta_jhash'])): ?>
                        <span style="font-weight:500;">GTA JHash:</span>
                        <span><?php echo htmlspecialchars($selectedNative['gta_jhash']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
				<p style="font-size:13px; color:var(--text-muted); margin-top:8px;">Copy Native copies the entire native to clipboard (Ex: Namespace::Native(Ped param1, Hash param2))</p>
				
                <div class="detail-actions">
                    <button class="btn" onclick="copyText('<?php echo htmlspecialchars($selectedNative['hash']); ?>')">📋 Copy Hash</button>
                    <button class="btn btn-success" onclick="copyText('<?php echo htmlspecialchars($signature); ?>')">📋 Copy Native</button>
                </div>
            <?php else: ?>
                <div class="detail-empty">
                    <div class="icon">🎯</div>
                    <h2>Select a Native</h2>
                    <p>Click on any native from the list to view its details here.</p>
                    <p style="font-size:13px; color:var(--text-muted); margin-top:8px;">Search above to find specific natives.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <script>
        // Navigation toggle
        const hamburger = document.getElementById('hamburger');
        const navOverlay = document.getElementById('navOverlay');
        const navMobile = document.getElementById('navMobile');
        
        function toggleNav() {
            hamburger.classList.toggle('active');
            navOverlay.classList.toggle('open');
            navMobile.classList.toggle('open');
            document.body.style.overflow = navMobile.classList.contains('open') ? 'hidden' : '';
        }
        
        hamburger.addEventListener('click', toggleNav);
        navOverlay.addEventListener('click', toggleNav);
        
        // Close nav on link click (mobile)
        document.querySelectorAll('.nav-mobile a').forEach(link => {
            link.addEventListener('click', () => {
                if (navMobile.classList.contains('open')) {
                    toggleNav();
                }
            });
        });
        
        // State
        let searchTimeout = null;
        let currentSearch = '<?php echo addslashes($search); ?>';
        let currentNamespace = '<?php echo addslashes($namespace); ?>';
        let currentPage = <?php echo $page; ?>;
        
        // DOM elements
        const searchInput = document.getElementById('searchInput');
        const namespaceSelect = document.getElementById('namespaceSelect');
        const nativeList = document.getElementById('nativeList');
        const detailPanel = document.getElementById('detailPanel');
        const resultCount = document.getElementById('resultCount');
        const listCount = document.getElementById('listCount');
        const searchClear = document.getElementById('searchClear');
        const pagination = document.getElementById('pagination');
        
        // Debounced search
        // Fetch the normal PHP page in the background, then replace ONLY #nativeList.
        // The document itself is never navigated/reloaded, so the search input keeps focus.
        let searchController = null;
        let searchRequestId = 0;

        async function performSearch() {
            const searchVal = searchInput.value.trim();
            const namespaceVal = namespaceSelect.value;

            const params = new URLSearchParams(window.location.search);

            if (searchVal) {
                params.set('search', searchVal);
            } else {
                params.delete('search');
            }

            if (namespaceVal) {
                params.set('namespace', namespaceVal);
            } else {
                params.delete('namespace');
            }

            params.set('page', '1');
            params.set('per_page', '<?php echo $perPage; ?>');
            params.delete('id');

            const requestId = ++searchRequestId;

            if (searchController) {
                searchController.abort();
            }

            searchController = new AbortController();

            const wasFocused = document.activeElement === searchInput;
            const selectionStart = searchInput.selectionStart;
            const selectionEnd = searchInput.selectionEnd;

            try {
                const response = await fetch(
                    window.location.pathname + '?' + params.toString(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: searchController.signal,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error('Search request failed: HTTP ' + response.status);
                }

                const html = await response.text();

                if (requestId !== searchRequestId) {
                    return;
                }

                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newNativeList = doc.getElementById('nativeList');

                if (!newNativeList) {
                    throw new Error('The search response did not contain #nativeList.');
                }

                // Replace ONLY the native results. The rest of the page is untouched.
                nativeList.innerHTML = newNativeList.innerHTML;

                // Update the URL without navigating/reloading the page.
                window.history.replaceState(
                    null,
                    '',
                    window.location.pathname + '?' + params.toString()
                );

                currentSearch = searchVal;
                currentNamespace = namespaceVal;
                currentPage = 1;

                if (wasFocused) {
                    searchInput.focus({ preventScroll: true });

                    if (selectionStart !== null && selectionEnd !== null) {
                        const max = searchInput.value.length;
                        searchInput.setSelectionRange(
                            Math.min(selectionStart, max),
                            Math.min(selectionEnd, max)
                        );
                    }
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Native search failed:', error);
                    showToast('❌ Search failed. Please try again.');
                }
            }
        }

        // Live search on input
        searchInput.addEventListener('input', function() {
            const val = this.value.trim();
            searchClear.classList.toggle('visible', val.length > 0);
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 300);
        });
        
        // Namespace select change
        namespaceSelect.addEventListener('change', function() {
            performSearch();
        });
        
        // Clear search
        function clearSearch() {
            clearTimeout(searchTimeout);
            searchInput.value = '';
            searchClear.classList.remove('visible');

            performSearch().then(() => {
                searchInput.focus({ preventScroll: true });
                searchInput.setSelectionRange(0, 0);
            });
        }

        // Select native (for mobile touch)
        function selectNative(id) {
            const params = new URLSearchParams(window.location.search);
            params.set('id', id);
            params.set('page', currentPage);
            window.location.href = '?' + params.toString();
        }
        
        // Copy text to clipboard
        function copyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('✅ Copied: ' + text.substring(0, 50) + (text.length > 50 ? '...' : ''));
                }).catch(() => {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showToast('✅ Copied: ' + text.substring(0, 50) + (text.length > 50 ? '...' : ''));
            } catch (e) {
                showToast('❌ Failed to copy');
            }
            document.body.removeChild(textarea);
        }
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            clearTimeout(toast._timeout);
            toast._timeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+F / Cmd+F - focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            // Escape - clear search
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                clearSearch();
            }
        });
        
        // Auto-scroll selected item into view
        document.addEventListener('DOMContentLoaded', () => {
            const activeItem = document.querySelector('.native-item.active');
            if (activeItem) {
                activeItem.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            
            // Show clear button if search has value
            if (searchInput.value.trim().length > 0) {
                searchClear.classList.add('visible');
            }
        });
        
        // Handle pagination clicks without full page reload (progressive enhancement)
        document.querySelectorAll('.pagination .btn:not([disabled])').forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href.startsWith('?')) {
                    e.preventDefault();
                    // Preserve the selected native ID
                    const params = new URLSearchParams(href.substring(1));
                    const currentId = new URLSearchParams(window.location.search).get('id');
                    if (currentId) {
                        params.set('id', currentId);
                    }
                    window.location.href = '?' + params.toString();
                }
            });
        });
    </script>
</body>
</html>