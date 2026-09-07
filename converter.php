<?php
require_once 'assets/php/nav.php';

// Handle AJAX conversion requests
if (isset($_POST['action']) && $_POST['action'] === 'convert') {
    header('Content-Type: application/json');
    
    $input = $_POST['input'] ?? '';
    $inputFormat = $_POST['inputFormat'] ?? 'auto';
    $outputFormat = $_POST['outputFormat'] ?? 'json';
    
    if (empty($input)) {
        echo json_encode(['error' => 'Please provide input data']);
        exit;
    }
    
    try {
        // Parse input based on format
        $items = parseInput($input, $inputFormat);
        
        if (empty($items)) {
            echo json_encode(['error' => 'No items found in the input data']);
            exit;
        }
        
        // Convert to requested output format
        $output = convertOutput($items, $outputFormat);
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'output' => $output,
            'count' => count($items)
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Parse input from various formats
function parseInput($input, $format) {
    $items = [];
    
    // Try to detect format if auto
    if ($format === 'auto') {
        $format = detectFormat($input);
    }
    
    switch ($format) {
        case 'url':
            return parseUrl($input);
            
        case 'json':
            return parseJson($input);
            
        case 'ini':
            return parseIni($input);
            
        case 'cpp':
            return parseCpp($input);
            
        case 'lua':
            return parseLua($input);
            
        case 'txt':
        default:
            return parseTxt($input);
    }
}

function detectFormat($input) {
    $input = trim($input);
    
    // Check if it's a URL
    if (filter_var($input, FILTER_VALIDATE_URL)) {
        return 'url';
    }
    
    // Check if it's JSON
    if (strpos($input, '{') === 0 || strpos($input, '[') === 0) {
        json_decode($input);
        if (json_last_error() === JSON_ERROR_NONE) {
            return 'json';
        }
    }
    
    // Check if it's INI-like format
    if (preg_match('/^\[.*\]$/m', $input)) {
        return 'ini';
    }
    
    // Check if it's C++ format (contains :: or ->)
    if (preg_match('/::|->/', $input)) {
        return 'cpp';
    }
    
    // Check if it's Lua format
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*=\s*\{/m', $input)) {
        return 'lua';
    }
    
    return 'txt';
}

function parseUrl($url) {
    $content = @file_get_contents($url);
    if ($content === false) {
        throw new Exception("Failed to fetch URL: " . $url);
    }
    
    // Detect format of the fetched content
    $format = detectFormat($content);
    return parseInput($content, $format);
}

function parseJson($json) {
    $data = json_decode($json, true);
    if ($data === null) {
        throw new Exception("Invalid JSON format");
    }
    
    $items = [];
    
    // Handle different JSON structures
    if (isset($data['items']) && is_array($data['items'])) {
        // Format: {"items": ["item1", "item2"]}
        foreach ($data['items'] as $item) {
            if (is_array($item) && isset($item['name'])) {
                $items[] = ['name' => $item['name'], 'hash' => $item['hash'] ?? ''];
            } else {
                $items[] = ['name' => (string)$item, 'hash' => ''];
            }
        }
    } else if (isset($data['data']) && is_array($data['data'])) {
        // Format: {"data": [{"name": "item1", "hash": "0x123"}]}
        foreach ($data['data'] as $item) {
            if (is_array($item) && isset($item['name'])) {
                $items[] = ['name' => $item['name'], 'hash' => $item['hash'] ?? ''];
            } else {
                $items[] = ['name' => (string)$item, 'hash' => ''];
            }
        }
    } else if (isset($data['peds']) && is_array($data['peds'])) {
        // RDR3 Discoveries format
        foreach ($data['peds'] as $key => $value) {
            if (is_array($value) && isset($value['name'])) {
                $items[] = ['name' => $value['name'], 'hash' => $key];
            } else if (is_string($value)) {
                $items[] = ['name' => $value, 'hash' => ''];
            }
        }
    } else {
        // Try to extract any array
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['name'])) {
                $items[] = ['name' => $value['name'], 'hash' => $value['hash'] ?? $key];
            } else if (is_string($value) && is_numeric($key)) {
                $items[] = ['name' => $value, 'hash' => ''];
            } else if (is_string($value)) {
                $items[] = ['name' => $value, 'hash' => $key];
            } else if (is_numeric($value) && is_string($key)) {
                $items[] = ['name' => $key, 'hash' => $value];
            }
        }
    }
    
    return $items;
}

function parseIni($ini) {
    $lines = explode("\n", $ini);
    $items = [];
    $currentSection = '';
    $currentItems = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === ';' || $line[0] === '#') continue;
        
        if (preg_match('/^\[(.*?)\]$/', $line, $matches)) {
            // New section
            if (!empty($currentItems)) {
                foreach ($currentItems as $item) {
                    $items[] = ['name' => $item, 'hash' => ''];
                }
                $currentItems = [];
            }
            $currentSection = $matches[1];
        } else if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // GTAV ObjectList.ini format: "hash = name"
            if (preg_match('/^0x[a-fA-F0-9]+$/', $key)) {
                $items[] = ['name' => $value, 'hash' => $key];
            } else {
                // Generic INI format
                if (!empty($value)) {
                    $items[] = ['name' => $value, 'hash' => $key];
                } else {
                    $currentItems[] = $key;
                }
            }
        } else if (!empty($line)) {
            // Simple list item
            $currentItems[] = $line;
        }
    }
    
    // Add remaining items
    foreach ($currentItems as $item) {
        $items[] = ['name' => $item, 'hash' => ''];
    }
    
    return $items;
}

function parseCpp($cpp) {
    $lines = explode("\n", $cpp);
    $items = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Match patterns like: namespace::Name, or Name->Method
        if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\s*::\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\s*\([^)]*\))?)/', $line, $matches)) {
            $name = $matches[1] . '::' . $matches[2];
            $items[] = ['name' => $name, 'hash' => ''];
        } else if (preg_match('/([a-zA-Z_][a-zA-Z0-9_]*)\s*->\s*([a-zA-Z_][a-zA-Z0-9_]*(?:\s*\([^)]*\))?)/', $line, $matches)) {
            $name = $matches[1] . '->' . $matches[2];
            $items[] = ['name' => $name, 'hash' => ''];
        } else if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $line)) {
            // Function declaration
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $matches)) {
                $items[] = ['name' => $matches[1] . '()', 'hash' => ''];
            }
        } else if (!empty($line) && !preg_match('/^\/\//', $line) && !preg_match('/^\/\*/', $line)) {
            // Simple identifier
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)$/', $line)) {
                $items[] = ['name' => $line, 'hash' => ''];
            }
        }
    }
    
    return $items;
}

function parseLua($lua) {
    $lines = explode("\n", $lua);
    $items = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '-' || $line[0] === '#') continue;
        
        // Match table assignments: key = "value"
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*["\']([^"\']*)["\']/', $line, $matches)) {
            $items[] = ['name' => $matches[2], 'hash' => $matches[1]];
        } else if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([0-9a-fA-Fx]+)/', $line, $matches)) {
            // key = hash or number
            $items[] = ['name' => $matches[1], 'hash' => $matches[2]];
        } else if (preg_match('/^["\']([^"\']*)["\']\s*=\s*([0-9a-fA-Fx]+)/', $line, $matches)) {
            // "name" = hash
            $items[] = ['name' => $matches[1], 'hash' => $matches[2]];
        } else if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*\{/', $line, $matches)) {
            // Table start
            $items[] = ['name' => $matches[1], 'hash' => ''];
        } else if (!empty($line) && !preg_match('/^--/', $line) && !preg_match('/^local/', $line)) {
            // Try to extract quoted strings
            if (preg_match('/["\']([^"\']*)["\']/', $line, $matches)) {
                $items[] = ['name' => $matches[1], 'hash' => ''];
            } else if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)$/', $line)) {
                $items[] = ['name' => $line, 'hash' => ''];
            }
        }
    }
    
    return $items;
}

function parseTxt($txt) {
    $lines = explode("\n", $txt);
    $items = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#' || $line[0] === ';' || $line[0] === '/') continue;
        
        // Try to extract hash and name
        if (preg_match('/^(0x[a-fA-F0-9]+)\s+["\']?([^"\']+)["\']?/', $line, $matches)) {
            $items[] = ['name' => trim($matches[2]), 'hash' => $matches[1]];
        } else if (preg_match('/^["\']?([^"\']+)["\']?\s*[=:]\s*(0x[a-fA-F0-9]+)/', $line, $matches)) {
            $items[] = ['name' => trim($matches[1]), 'hash' => $matches[2]];
        } else if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*[=:]\s*([0-9a-fA-Fx]+)/', $line, $matches)) {
            $items[] = ['name' => trim($matches[1]), 'hash' => $matches[2]];
        } else if (!empty($line)) {
            // Just a plain item name
            $items[] = ['name' => $line, 'hash' => ''];
        }
    }
    
    return $items;
}

function convertOutput($items, $format) {
    switch ($format) {
        case 'json':
            return json_encode(['items' => $items, 'count' => count($items)], JSON_PRETTY_PRINT);
            
        case 'ini':
            $output = "; Converted List - Generated " . date('Y-m-d H:i:s') . "\n";
            foreach ($items as $item) {
                if (!empty($item['hash'])) {
                    $output .= $item['hash'] . " = " . $item['name'] . "\n";
                } else {
                    $output .= $item['name'] . " = \n";
                }
            }
            return $output;
            
        case 'lua':
            $output = "-- Converted List - Generated " . date('Y-m-d H:i:s') . "\n";
            $output .= "local items = {\n";
            foreach ($items as $item) {
                if (!empty($item['hash'])) {
                    $output .= "    ['" . addslashes($item['name']) . "'] = '" . $item['hash'] . "',\n";
                } else {
                    $output .= "    '" . addslashes($item['name']) . "',\n";
                }
            }
            $output .= "}\n";
            $output .= "return items";
            return $output;
            
        case 'cpp':
            $output = "// Converted List - Generated " . date('Y-m-d H:i:s') . "\n";
            $output .= "const char* itemList[] = {\n";
            foreach ($items as $item) {
                $output .= "    \"" . addslashes($item['name']) . "\",\n";
            }
            $output .= "};\n";
            return $output;
            
        case 'csv':
            $output = "Name,Hash\n";
            foreach ($items as $item) {
                $output .= '"' . addslashes($item['name']) . '",' . $item['hash'] . "\n";
            }
            return $output;
            
        case 'php':
            $output = "<?php\n// Converted List - Generated " . date('Y-m-d H:i:s') . "\n";
            $output .= "return [\n";
            foreach ($items as $item) {
                if (!empty($item['hash'])) {
                    $output .= "    '" . addslashes($item['name']) . "' => '" . $item['hash'] . "',\n";
                } else {
                    $output .= "    '" . addslashes($item['name']) . "',\n";
                }
            }
            $output .= "];\n";
            return $output;
            
        case 'txt':
        default:
            $output = "";
            foreach ($items as $item) {
                if (!empty($item['hash'])) {
                    $output .= $item['hash'] . " = " . $item['name'] . "\n";
                } else {
                    $output .= $item['name'] . "\n";
                }
            }
            return $output;
    }
}

// If not an AJAX request, display the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>List Converter - Tools</title>
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
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow: auto;
        }
        
        /* Navigation - Fixed dropdown behavior */
        .navbar {
            background: var(--bg-nav);
            border-bottom: 1px solid var(--border-color);
            padding: 0 20px;
            height: var(--nav-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            position: sticky;
            top: 0;
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
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .hamburger {
                display: flex;
            }
            .navbar {
                padding: 0 16px;
            }
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 24px;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--text-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-header p {
            color: var(--text-secondary);
            font-size: 15px;
            margin-top: 4px;
        }
        
        .converter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 1024px) {
            .converter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
        }
        
        .panel-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .panel-title .badge {
            font-size: 11px;
            font-weight: 400;
            color: var(--text-muted);
            background: var(--bg-primary);
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        textarea {
            width: 100%;
            min-height: 300px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            padding: 12px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            transition: all 0.3s ease;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        textarea::placeholder {
            color: var(--text-muted);
        }
        
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            align-items: center;
        }
        
        .controls-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .controls-group label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        select {
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 13px;
            cursor: pointer;
        }
        
        select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        .btn {
            padding: 8px 20px;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        .btn-danger:hover {
            opacity: 0.8;
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            padding: 12px 0;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 12px;
        }
        
        .stat-item {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .stat-item strong {
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .output-area {
            position: relative;
        }
        
        .output-area .copy-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 12px;
            font-size: 12px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
        }
        
        .loading-spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid var(--border-color);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
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
        
        .toast.error {
            border-color: var(--danger);
        }
        
        .toast.success {
            border-color: var(--success);
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }
            
            .page-header h1 {
                font-size: 22px;
            }
            
            .panel {
                padding: 14px;
            }
            
            textarea {
                min-height: 200px;
                font-size: 12px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .controls-group {
                justify-content: space-between;
            }
            
            select {
                flex: 1;
            }
            
            .btn {
                flex: 1;
                text-align: center;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
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
    <!-- Navigation - Same as main page -->
    <nav class="navbar">
        <a href="converter.php" class="nav-brand">
            <span class="brand-icon">♻️</span>
            List Converter
        </a>
        
        <ul class="nav-links">
            <?php foreach ($navItems as $key => $item): ?>
                <li class="<?php echo isset($item['submenu']) ? 'nav-dropdown' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>">
                        <?php echo $item['label']; ?>
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

    <div class="container">
        <div class="page-header">
            <h1>♻️ List Converter</h1>
            <p>Convert item lists between different formats. Supports JSON, INI, C++, Lua, and plain text.</p>
        </div>
        
        <div class="converter-grid">
            <!-- Input Panel -->
            <div class="panel">
                <div class="panel-title">
                    📥 Input
                    <span class="badge">Supports URL, JSON, INI, C++, Lua, TXT</span>
                </div>
                
                <textarea id="inputArea" placeholder="Paste your list here or enter a URL...&#10;&#10;Examples:&#10;https://raw.githubusercontent.com/.../peds_list.php&#10;{&quot;items&quot;: [&quot;item1&quot;, &quot;item2&quot;]}&#10;[Peds]&#10;0x123456 = PedName"></textarea>
                
                <div class="controls">
                    <div class="controls-group">
                        <label>Input Format:</label>
                        <select id="inputFormat">
                            <option value="auto">Auto Detect</option>
                            <option value="url">URL</option>
                            <option value="json">JSON</option>
                            <option value="ini">INI</option>
                            <option value="cpp">C++</option>
                            <option value="lua">Lua</option>
                            <option value="txt">Plain Text</option>
                        </select>
                    </div>
                    
                    <div class="controls-group">
                        <button class="btn btn-primary" onclick="convert()">🔄 Convert</button>
                        <button class="btn btn-danger" onclick="clearAll()">🗑️ Clear</button>
                        <button class="btn" onclick="loadExample()">📄 Load Example</button>
                    </div>
                </div>
            </div>
            
            <!-- Output Panel -->
            <div class="panel">
                <div class="panel-title">
                    📤 Output
                    <span class="badge" id="itemCount">0 items</span>
                </div>
                
                <div class="stats-bar" id="statsBar" style="display:none;">
                    <div class="stat-item">
                        <strong id="statItems">0</strong> items found
                    </div>
                    <div class="stat-item">
                        <strong id="statWithHash">0</strong> with hashes
                    </div>
                    <div class="stat-item">
                        <strong id="statWithoutHash">0</strong> without hashes
                    </div>
                </div>
                
                <div class="output-area">
                    <textarea id="outputArea" placeholder="Converted output will appear here..." readonly></textarea>
                    <button class="btn btn-success copy-btn" onclick="copyOutput()" style="display:none;">📋 Copy</button>
                </div>
                
                <div class="controls">
                    <div class="controls-group">
                        <label>Output Format:</label>
                        <select id="outputFormat">
                            <option value="txt">Plain Text</option>
                            <option value="json">JSON</option>
                            <option value="ini">INI</option>
                            <option value="lua">Lua</option>
                            <option value="cpp">C++ Array</option>
                            <option value="csv">CSV</option>
                            <option value="php">PHP Array</option>
                        </select>
                    </div>
                    
                    <div class="controls-group">
                        <button class="btn btn-success" onclick="copyOutput()">📋 Copy Output</button>
                        <button class="btn" onclick="downloadOutput()">💾 Download</button>
                    </div>
                </div>
                
                <div class="loading" id="loadingIndicator">
                    <span class="loading-spinner"></span>
                    Processing...
                </div>
            </div>
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
        
        document.querySelectorAll('.nav-mobile a').forEach(link => {
            link.addEventListener('click', () => {
                if (navMobile.classList.contains('open')) toggleNav();
            });
        });
        
        // Touch support for dropdowns on mobile
        document.querySelectorAll('.nav-dropdown > a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    const menu = parent.querySelector('.dropdown-menu');
                    if (menu) {
                        const isOpen = menu.style.opacity === '1';
                        // Close all others
                        document.querySelectorAll('.nav-dropdown .dropdown-menu').forEach(m => {
                            if (m !== menu) {
                                m.style.opacity = '0';
                                m.style.visibility = 'hidden';
                                m.style.pointerEvents = 'none';
                            }
                        });
                        // Toggle this one
                        if (isOpen) {
                            menu.style.opacity = '0';
                            menu.style.visibility = 'hidden';
                            menu.style.pointerEvents = 'none';
                        } else {
                            menu.style.opacity = '1';
                            menu.style.visibility = 'visible';
                            menu.style.pointerEvents = 'auto';
                        }
                    }
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-dropdown')) {
                document.querySelectorAll('.nav-dropdown .dropdown-menu').forEach(menu => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.pointerEvents = 'none';
                });
            }
        });
        
        // Converter functions
        function showToast(message, type = '') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            clearTimeout(toast._timeout);
            toast._timeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        function showLoading(show) {
            document.getElementById('loadingIndicator').style.display = show ? 'block' : 'none';
        }
        
        function clearAll() {
            document.getElementById('inputArea').value = '';
            document.getElementById('outputArea').value = '';
            document.getElementById('statsBar').style.display = 'none';
            document.querySelector('.copy-btn').style.display = 'none';
            document.getElementById('itemCount').textContent = '0 items';
            showToast('Cleared all fields');
        }
        
        function loadExample() {
            const example = `https://raw.githubusercontent.com/femga/rdr3_discoveries/refs/heads/master/peds/peds_list.lua`;
            document.getElementById('inputArea').value = example;
            showToast('Example URL loaded. Click Convert to process.');
        }
        
        async function convert() {
            const input = document.getElementById('inputArea').value.trim();
            const inputFormat = document.getElementById('inputFormat').value;
            const outputFormat = document.getElementById('outputFormat').value;
            
            if (!input) {
                showToast('Please enter some input data or a URL', 'error');
                return;
            }
            
            showLoading(true);
            document.getElementById('outputArea').value = 'Processing...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'convert');
                formData.append('input', input);
                formData.append('inputFormat', inputFormat);
                formData.append('outputFormat', outputFormat);
                
                const response = await fetch('converter.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showToast('Error: ' + data.error, 'error');
                    document.getElementById('outputArea').value = 'Error: ' + data.error;
                } else {
                    document.getElementById('outputArea').value = data.output;
                    document.querySelector('.copy-btn').style.display = 'block';
                    document.getElementById('itemCount').textContent = data.count + ' items';
                    
                    // Show stats
                    const statsBar = document.getElementById('statsBar');
                    statsBar.style.display = 'flex';
                    document.getElementById('statItems').textContent = data.count;
                    
                    let withHash = 0;
                    let withoutHash = 0;
                    if (data.items) {
                        data.items.forEach(item => {
                            if (item.hash && item.hash !== '') {
                                withHash++;
                            } else {
                                withoutHash++;
                            }
                        });
                    }
                    document.getElementById('statWithHash').textContent = withHash;
                    document.getElementById('statWithoutHash').textContent = withoutHash;
                    
                    showToast('Converted ' + data.count + ' items successfully', 'success');
                }
            } catch (error) {
                showToast('Error: ' + error.message, 'error');
                document.getElementById('outputArea').value = 'Error: ' + error.message;
            } finally {
                showLoading(false);
            }
        }
        
        function copyOutput() {
            const output = document.getElementById('outputArea');
            if (!output.value) {
                showToast('Nothing to copy', 'error');
                return;
            }
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(output.value).then(() => {
                    showToast('✅ Copied to clipboard!', 'success');
                }).catch(() => {
                    fallbackCopy(output.value);
                });
            } else {
                fallbackCopy(output.value);
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
                showToast('✅ Copied to clipboard!', 'success');
            } catch (e) {
                showToast('Failed to copy', 'error');
            }
            document.body.removeChild(textarea);
        }
        
        function downloadOutput() {
            const output = document.getElementById('outputArea').value;
            if (!output) {
                showToast('Nothing to download', 'error');
                return;
            }
            
            const format = document.getElementById('outputFormat').value;
            const extensions = {
                'txt': 'txt',
                'json': 'json',
                'ini': 'ini',
                'lua': 'lua',
                'cpp': 'cpp',
                'csv': 'csv',
                'php': 'php'
            };
            
            const ext = extensions[format] || 'txt';
            const blob = new Blob([output], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'converted_list.' + ext;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Download started!', 'success');
        }
        
        // Enter key to convert
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                convert();
            }
        });
        
        // Format change triggers auto-convert if there's input
        document.getElementById('outputFormat').addEventListener('change', () => {
            const input = document.getElementById('inputArea').value.trim();
            if (input) {
                convert();
            }
        });
    </script>
</body>
</html>