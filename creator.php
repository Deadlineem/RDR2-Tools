<?php
require_once 'assets/php/nav.php';

// Handle AJAX generation request - MUST be before any HTML output
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    header('Content-Type: application/json');
    
    try {
        $commandType = $_POST['commandType'] ?? 'looped';
        $commandName = $_POST['commandName'] ?? '';
        $displayName = $_POST['displayName'] ?? '';
        $description = $_POST['description'] ?? '';
        $includes = isset($_POST['includes']) ? json_decode($_POST['includes'], true) : [];
        $namespace = $_POST['namespace'] ?? 'YimMenu::Features';
        $additionalCode = $_POST['additionalCode'] ?? '';
        $onDisableCode = $_POST['onDisableCode'] ?? ''; // Make sure this is always set
        
        if (empty($commandName) || empty($displayName)) {
            echo json_encode(['error' => 'Command name and display name are required']);
            exit;
        }
        
        // Clean command name
        $className = ucfirst($commandName);
        $commandId = strtolower($commandName);
        
        // Generate the code
        $code = generateCommandCode($commandType, $className, $commandId, $displayName, $description, $includes, $namespace, $additionalCode, $onDisableCode);
        
        echo json_encode([
            'success' => true,
            'code' => $code,
            'filename' => $commandName . '.cpp'
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function indentCode($code, $indent = "\t\t\t") {
    if (empty($code)) return $code;
    
    // Split into lines, indent each line, then join back
    $lines = explode("\n", $code);
    $indented = array_map(function($line) use ($indent) {
        // Don't indent empty lines
        if (trim($line) === '') return $line;
        return $indent . $line;
    }, $lines);
    
    return implode("\n", $indented);
}

function generateCommandCode($type, $className, $commandId, $displayName, $description, $includes, $namespace, $additionalCode, $onDisableCode) {
    // Base includes based on command type
    $baseIncludes = [];
    switch ($type) {
        case 'looped':
            $baseIncludes[] = 'core/commands/LoopedCommand.hpp';
            break;
        case 'player':
            $baseIncludes[] = 'game/commands/PlayerCommand.hpp';
            break;
        case 'command':
        default:
            $baseIncludes[] = 'core/commands/Command.hpp';
            break;
    }
    
    // Merge with user includes
    $allIncludes = array_merge($baseIncludes, $includes);
    $includeLines = '';
    foreach ($allIncludes as $inc) {
        if (!empty($inc)) {
            $includeLines .= '#include "' . $inc . "\"\n";
        }
    }
    
    // Generate class body based on type
    $classBody = '';
    $commandTypeName = '';
    
    switch ($type) {
        case 'looped':
            $commandTypeName = 'LoopedCommand';
            $tickCode = !empty($additionalCode) ? indentCode($additionalCode) : "\t\t\t// Add your looped code here";
            $disableCode = !empty($onDisableCode) ? indentCode($onDisableCode) : "\t\t\t// TODO: Clean up when disabled";
            $classBody = '		virtual void OnTick() override
		{
' . $tickCode . '
		}

		virtual void OnDisable() override
		{
' . $disableCode . '
		}';
            break;
            
        case 'player':
            $commandTypeName = 'PlayerCommand';
            $codeContent = !empty($additionalCode) ? indentCode($additionalCode) : "\t\t\t// Add your code here, dont forget to add includes as needed";
            $classBody = '		virtual void OnCall(Player player) override
		{
' . $codeContent . '
		}';
            break;

            
        case 'command':
        default:
            $commandTypeName = 'Command';
            $codeContent = !empty($additionalCode) ? indentCode($additionalCode) : "\t\t\t// Add your code here, dont forget to add includes as needed";
            $classBody = '		virtual void OnCall() override
		{
' . $codeContent . '
		}';
            break;
    }
    
    // Build the full code
    $code = $includeLines . "\n";
    $code .= 'namespace ' . $namespace . "\n";
    $code .= "{\n";
    $code .= "\tclass " . $className . " : public " . $commandTypeName . "\n";
    $code .= "\t{\n";
    $code .= "\t\tusing " . $commandTypeName . "::" . $commandTypeName . ";\n";
    $code .= "\n";
    $code .= $classBody . "\n";
    $code .= "\t};\n";
    $code .= "\n";
    $code .= "\tstatic " . $className . " _" . $className . "{\"" . $commandId . "\", \"" . $displayName . "\", \"" . $description . "\"};\n";
    $code .= "}\n";
    
    return $code;
}

// If not an AJAX request, display the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Script Generator - Tools</title>
    <style>
        /* Reset and base */
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
        
        /* Navbar */
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
        
        .nav-dropdown:hover .dropdown-menu,
        .nav-dropdown:focus-within .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }
        
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
        }
        
        .nav-dropdown .dropdown-menu a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
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
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            min-height: calc(100vh - var(--nav-height));
            display: flex;
            flex-direction: column;
        }
        
        .page-header {
            margin-bottom: 24px;
            flex-shrink: 0;
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
        
        /* Grid */
        .generator-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            flex: 1;
            min-height: 0;
        }
        
        @media (max-width: 1024px) {
            .generator-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Panels - Now use flex column */
        .panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        
        /* Left panel - doesn't need to grow as much */
        .panel:first-child {
            /* Keep at natural height, but can scroll if needed */
        }
        
        /* Right panel - should fill available space */
        .panel:last-child {
            flex: 1;
        }
        
        .panel-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .panel-title .badge {
            font-size: 11px;
            font-weight: 400;
            color: var(--text-muted);
            background: var(--bg-primary);
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 14px;
            flex-shrink: 0;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-group label .required {
            color: var(--danger);
        }
        
        .form-group .hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        textarea.form-control {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238a9bb5' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 32px;
        }
        
        /* Checkbox groups - Make scrollable */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 0;
            max-height: 200px;
            overflow-y: auto;
            align-content: flex-start;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 3px 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .checkbox-group label:hover {
            background: var(--bg-hover);
            border-color: var(--accent);
        }
        
        .checkbox-group label input[type="checkbox"] {
            accent-color: var(--accent);
        }
        
        .checkbox-group label.checked {
            border-color: var(--accent);
            background: var(--accent-glow);
        }
        
        .checkbox-category {
            width: 100%;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 4px 0;
            margin-top: 4px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        /* Code textareas - specifically for code input */
        .code-textarea {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            min-height: 80px;
        }
        
        /* Buttons */
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
        
        .btn-outline {
            background: transparent;
            border-color: var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-hover);
        }
        
        /* Controls */
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 16px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .controls-group {
            display: flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Output area */
        .output-area {
            position: relative;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }
        
        .output-area .copy-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 12px;
            font-size: 12px;
            z-index: 10;
        }
        
        .output-area textarea {
            flex: 1;
            width: 100%;
            min-height: 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            resize: vertical;
            transition: all 0.3s ease;
        }
        
        .output-area textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }
        
        /* Loading */
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
        
        .toast.error {
            border-color: var(--danger);
        }
        
        .toast.success {
            border-color: var(--success);
        }
        
        /* Templates */
        .template-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .template-btn {
            padding: 10px 14px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: left;
            font-size: 13px;
        }
        
        .template-btn:hover {
            background: var(--bg-hover);
            border-color: var(--accent);
            color: var(--text-primary);
        }
        
        .template-btn .template-icon {
            font-size: 20px;
            display: block;
            margin-bottom: 4px;
        }
        
        .template-btn .template-name {
            font-weight: 600;
        }
        
        .template-btn .template-desc {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        /* Filename display */
        .filename-display {
            font-size: 13px;
            color: var(--text-muted);
            padding: 8px 12px;
            background: var(--bg-primary);
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-top: 8px;
            flex-shrink: 0;
        }
        
        .filename-display strong {
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
        }
        
        /* Responsive */
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
            
            .generator-grid {
                gap: 12px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .controls-group {
                justify-content: space-between;
            }
            
            .checkbox-group label {
                font-size: 11px;
                padding: 2px 6px;
            }
            
            .template-selector {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Scrollbar styling */
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
    <nav class="navbar">
        <a href="creator.php" class="nav-brand">
            <span class="brand-icon">⚡</span>
            Script Generator
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
            <h1>⚡ Script Generator</h1>
            <p>Generate features for YimMenuV2/HorseMenu/ChronixV2/Helix with proper includes and structure.</p>
        </div>
        
        <div class="generator-grid">
            <div class="panel">
                <div class="panel-title">
                    📝 Command Configuration
                    <span class="badge">YimMenu Style</span>
                </div>
                
                <div class="form-group">
                    <label>Templates</label>
                    <div class="template-selector">
                        <div class="template-btn" onclick="loadTemplate('looped')">
                            <span class="template-icon">🔄</span>
                            <div class="template-name">Looped Command</div>
                            <div class="template-desc">Runs every tick</div>
                        </div>
                        <div class="template-btn" onclick="loadTemplate('command')">
                            <span class="template-icon">⚡</span>
                            <div class="template-name">Basic Command</div>
                            <div class="template-desc">Single call</div>
                        </div>
                        <div class="template-btn" onclick="loadTemplate('player')">
                            <span class="template-icon">👤</span>
                            <div class="template-name">Player Command</div>
                            <div class="template-desc">Targets a player</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Command Type <span class="required">*</span></label>
                    <select class="form-control" id="commandType">
                        <option value="looped">🔄 LoopedCommand</option>
                        <option value="command">⚡ Command</option>
                        <option value="player">👤 PlayerCommand</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Command Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="commandName" placeholder="e.g., godmode, noclip, teleport" />
                    <div class="hint">Lowercase, no spaces (will be used as the command ID)</div>
                </div>
                
                <div class="form-group">
                    <label>Display Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="displayName" placeholder="e.g., God Mode, No Clip, Teleport" />
                    <div class="hint">What users will see in the menu</div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" class="form-control" id="description" placeholder="Brief description of what this command does" />
                </div>
                
                <div class="form-group">
                    <label>Namespace</label>
                    <input type="text" class="form-control" id="namespace" value="YimMenu::Features" />
                </div>
                
                <div class="form-group">
                    <label>Includes</label>
                    <div class="checkbox-group" id="includesGroup">
                        <div class="checkbox-category">Core/Commands</div>
                        <label><input type="checkbox" value="core/commands/Command.hpp" /> Command.hpp</label>
                        <label><input type="checkbox" value="core/commands/LoopedCommand.hpp" /> LoopedCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/BoolCommand.hpp" /> BoolCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/IntCommand.hpp" /> IntCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/FloatCommand.hpp" /> FloatCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/StringCommand.hpp" /> StringCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/ColorCommand.hpp" /> ColorCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/ListCommand.hpp" /> ListCommand.hpp</label>
                        <label><input type="checkbox" value="core/commands/Vector3Command.hpp" /> Vector3Command.hpp</label>
                        <label><input type="checkbox" value="core/commands/HotkeySystem.hpp" /> HotkeySystem.hpp</label>
                        
                        <div class="checkbox-category">Game/Commands</div>
                        <label><input type="checkbox" value="game/commands/PlayerCommand.hpp" /> PlayerCommand.hpp</label>
                        
                        <div class="checkbox-category">Core/FileMgr</div>
                        <label><input type="checkbox" value="core/filemgr/FileMgr.hpp" /> FileMgr.hpp</label>
                        
                        <div class="checkbox-category">Core/Frontend</div>
                        <label><input type="checkbox" value="core/frontend/Notifications.hpp" /> Notifications.hpp</label>
                        
                        <div class="checkbox-category">Game/Backend</div>
                        <label><input type="checkbox" value="game/backend/Self.hpp" /> Self.hpp</label>
                        <label><input type="checkbox" value="game/backend/Players.hpp" /> Players.hpp</label>
                        <label><input type="checkbox" value="game/backend/ScriptMgr.hpp" /> ScriptMgr.hpp</label>
                        <label><input type="checkbox" value="game/backend/FiberPool.hpp" /> FiberPool.hpp</label>
                        <label><input type="checkbox" value="game/backend/NativeHooks.hpp" /> NativeHooks.hpp</label>
                        
                        <div class="checkbox-category">Game/RDR</div>
                        <label><input type="checkbox" value="game/rdr/Entity.hpp" /> Entity.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Ped.hpp" /> Ped.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Player.hpp" /> Player.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Vehicle.hpp" /> Vehicle.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Object.hpp" /> Object.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Network.hpp" /> Network.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Packet.hpp" /> Packet.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Scripts.hpp" /> Scripts.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Pools.hpp" /> Pools.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Enums.hpp" /> Enums.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Natives.hpp" /> Natives.hpp</label>
                        <label><input type="checkbox" value="game/rdr/Nodes.hpp" /> Nodes.hpp</label>
                        <label><input type="checkbox" value="game/rdr/ScriptFunction.hpp" /> ScriptFunction.hpp</label>
                        <label><input type="checkbox" value="game/rdr/ScriptGlobal.hpp" /> ScriptGlobal.hpp</label>
                        <label><input type="checkbox" value="game/rdr/ScriptLocal.hpp" /> ScriptLocal.hpp</label>
                        
                        <div class="checkbox-category">Game/RDR/Data</div>
                        <label><input type="checkbox" value="game/rdr/data/AmmoTypes.hpp" /> AmmoTypes.hpp</label>
                        <label><input type="checkbox" value="game/rdr/data/ItemTypes.hpp" /> ItemTypes.hpp</label>
                        <label><input type="checkbox" value="game/rdr/data/PedModels.hpp" /> PedModels.hpp</label>
                        <label><input type="checkbox" value="game/rdr/data/VehicleModels.hpp" /> VehicleModels.hpp</label>
                        <label><input type="checkbox" value="game/rdr/data/ObjectModels.hpp" /> ObjectModels.hpp</label>
                        <label><input type="checkbox" value="game/rdr/data/WeaponTypes.hpp" /> WeaponTypes.hpp</label>
                        
                        <div class="checkbox-category">Util</div>
                        <label><input type="checkbox" value="util/Math.hpp" /> Math.hpp</label>
                        <label><input type="checkbox" value="util/Helpers.hpp" /> Helpers.hpp</label>
                        <label><input type="checkbox" value="util/Joaat.hpp" /> Joaat.hpp</label>
                        <label><input type="checkbox" value="util/Chat.hpp" /> Chat.hpp</label>
                        <label><input type="checkbox" value="util/teleport.hpp" /> teleport.hpp</label>
                        <label><input type="checkbox" value="util/SpawnObject.hpp" /> SpawnObject.hpp</label>
                        <label><input type="checkbox" value="util/VehicleSpawner.hpp" /> VehicleSpawner.hpp</label>
                        <label><input type="checkbox" value="util/Rewards.hpp" /> Rewards.hpp</label>
                        <label><input type="checkbox" value="util/Protobufs.hpp" /> Protobufs.hpp</label>
                        <label><input type="checkbox" value="util/network.hpp" /> network.hpp</label>
                        <label><input type="checkbox" value="util/GraphicsValue.hpp" /> GraphicsValue.hpp</label>
                        <label><input type="checkbox" value="util/StrToHex.hpp" /> StrToHex.hpp</label>
                    </div>
                    <div class="hint">Select all includes your command needs</div>
                </div>
                
                <div class="form-group">
                    <label>Custom Code</label>
                    <textarea class="form-control" id="additionalCode" placeholder="// Add your code here, dont forget to add includes as needed&#10;// This will be placed inside OnTick()/OnCall()" rows="4"></textarea>
                    <div class="hint">This code will be placed in the OnTick() or OnCall() method</div>
                </div>
                
                <div class="form-group" id="onDisableGroup" style="display:none;">
                    <label>OnDisable() Code</label>
                    <textarea class="form-control" id="onDisableCode" placeholder="// Clean up when disabled" rows="3"></textarea>
                    <div class="hint">This code will be placed inside OnDisable() method (for LoopedCommand only)</div>
                </div>
                
                <div class="controls">
                    <div class="controls-group">
                        <button class="btn btn-primary" onclick="generate()">⚡ Generate</button>
                        <button class="btn btn-danger" onclick="clearAll()">🗑️ Clear</button>
                        <button class="btn btn-outline" onclick="loadExample()">📄 Load Example</button>
                    </div>
                </div>
                
                <div class="filename-display" id="filenameDisplay">
                    Filename: <strong>command.cpp</strong>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-title">
                    📤 Generated Code
                    <span class="badge" id="lineCount">0 lines</span>
                </div>
                
                <div class="output-area">
                    <textarea class="form-control" id="outputArea" placeholder="Generated code will appear here..." readonly></textarea>
                    <button class="btn btn-success copy-btn" onclick="copyOutput()" style="display:none;">📋 Copy</button>
                </div>
                
                <div class="controls">
                    <div class="controls-group">
                        <button class="btn btn-success" onclick="copyOutput()">📋 Copy Code</button>
                        <button class="btn" onclick="downloadOutput()">💾 Download</button>
                        <button class="btn btn-primary" onclick="copyFilename()">📝 Copy Filename</button>
                    </div>
                </div>
                
                <div class="loading" id="loadingIndicator">
                    <span class="loading-spinner"></span>
                    Generating...
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
        
        // Touch support for dropdowns
        document.querySelectorAll('.nav-dropdown > a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    const menu = parent.querySelector('.dropdown-menu');
                    if (menu) {
                        const isOpen = menu.style.opacity === '1';
                        document.querySelectorAll('.nav-dropdown .dropdown-menu').forEach(m => {
                            if (m !== menu) {
                                m.style.opacity = '0';
                                m.style.visibility = 'hidden';
                                m.style.pointerEvents = 'none';
                            }
                        });
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
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-dropdown')) {
                document.querySelectorAll('.nav-dropdown .dropdown-menu').forEach(menu => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.pointerEvents = 'none';
                });
            }
        });
        
        // Show/hide OnDisable based on command type
        document.getElementById('commandType').addEventListener('change', function() {
            const onDisableGroup = document.getElementById('onDisableGroup');
            if (this.value === 'looped') {
                onDisableGroup.style.display = 'block';
            } else {
                onDisableGroup.style.display = 'none';
            }
        });
        
        // Generator functions
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
        
        function updateFilename() {
            const name = document.getElementById('commandName').value.trim();
            const display = document.getElementById('filenameDisplay');
            if (name) {
                display.innerHTML = 'Filename: <strong>' + name + '.cpp</strong>';
            } else {
                display.innerHTML = 'Filename: <strong>command.cpp</strong>';
            }
        }
        
        document.getElementById('commandName').addEventListener('input', updateFilename);
        
        function loadTemplate(type) {
            const templates = {
                'looped': {
                    type: 'looped',
                    name: 'godmode',
                    display: 'God Mode',
                    desc: 'Blocks all incoming damage',
                    tickCode: '// Toggle godmode for the local player\nif (!Self::GetPed())\n    return;\nif (Self::GetPed().IsDead())\n    Self::GetPed().SetInvincible(false);\nelse\n    Self::GetPed().SetInvincible(true);\nSelf::GetPed().SetTargetActionDisableFlag(13, true);\nSelf::GetPed().SetTargetActionDisableFlag(16, true);\nSelf::GetPed().SetTargetActionDisableFlag(17, true);',
                    disableCode: '// Disable godmode\nif (Self::GetPed())\n    Self::GetPed().SetInvincible(false);',
                    includes: ['game/backend/Self.hpp']
                },
                'command': {
                    type: 'command',
                    name: 'suicide',
                    display: 'Suicide',
                    desc: 'Kills you instantly',
                    tickCode: '// Kill the local player\nSelf::GetPed().SetInvincible(false);\nSelf::GetPed().SetHealth(0);',
                    disableCode: '',
                    includes: ['game/backend/Self.hpp']
                },
                'player': {
                    type: 'player',
                    name: 'cageplayercircus',
                    display: 'Cage Player (Circus)',
                    desc: 'Cages the player using a circus wagon',
                    tickCode: '// Cage the selected player\nauto coords = player.GetPed().GetPosition();\ncoords.z -= 1.0f;\nObject::Create(0x99C0CFCF, coords);',
                    disableCode: '',
                    includes: ['game/backend/Self.hpp', 'game/rdr/Object.hpp', 'game/rdr/Natives.hpp']
                }
            };
            
            const template = templates[type];
            if (!template) return;
            
            document.getElementById('commandType').value = template.type;
            document.getElementById('commandName').value = template.name;
            document.getElementById('displayName').value = template.display;
            document.getElementById('description').value = template.desc;
            document.getElementById('additionalCode').value = template.tickCode;
            document.getElementById('onDisableCode').value = template.disableCode;
            
            // Show/hide OnDisable
            const onDisableGroup = document.getElementById('onDisableGroup');
            if (type === 'looped') {
                onDisableGroup.style.display = 'block';
            } else {
                onDisableGroup.style.display = 'none';
            }
            
            // Update includes
            const checkboxes = document.querySelectorAll('#includesGroup input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = template.includes.includes(cb.value);
                cb.closest('label').classList.toggle('checked', cb.checked);
            });
            
            updateFilename();
            showToast('Loaded ' + template.display + ' template', 'success');
        }
        
        function loadExample() {
            loadTemplate('looped');
        }
        
        function clearAll() {
            document.getElementById('commandName').value = '';
            document.getElementById('displayName').value = '';
            document.getElementById('description').value = '';
            document.getElementById('additionalCode').value = '';
            document.getElementById('onDisableCode').value = '';
            document.getElementById('outputArea').value = '';
            document.querySelector('.copy-btn').style.display = 'none';
            document.getElementById('lineCount').textContent = '0 lines';
            updateFilename();
            showToast('Cleared all fields');
        }
        
        async function generate() {
            const commandType = document.getElementById('commandType').value;
            const commandName = document.getElementById('commandName').value.trim();
            const displayName = document.getElementById('displayName').value.trim();
            const description = document.getElementById('description').value.trim();
            const additionalCode = document.getElementById('additionalCode').value;
            const onDisableCode = document.getElementById('onDisableCode').value;
            const namespace = document.getElementById('namespace').value.trim() || 'YimMenu::Features';
            
            // Get selected includes
            const includes = [];
            document.querySelectorAll('#includesGroup input[type="checkbox"]:checked').forEach(cb => {
                includes.push(cb.value);
            });
            
            if (!commandName || !displayName) {
                showToast('Command name and display name are required', 'error');
                return;
            }
            
            showLoading(true);
            document.getElementById('outputArea').value = 'Generating...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'generate');
                formData.append('commandType', commandType);
                formData.append('commandName', commandName);
                formData.append('displayName', displayName);
                formData.append('description', description);
                formData.append('includes', JSON.stringify(includes));
                formData.append('namespace', namespace);
                formData.append('additionalCode', additionalCode);
                formData.append('onDisableCode', onDisableCode);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Server returned invalid JSON. Check PHP error logs.');
                }
                
                if (data.error) {
                    showToast('Error: ' + data.error, 'error');
                    document.getElementById('outputArea').value = 'Error: ' + data.error;
                } else {
                    document.getElementById('outputArea').value = data.code;
                    document.querySelector('.copy-btn').style.display = 'block';
                    
                    const lines = data.code.split('\n').length;
                    document.getElementById('lineCount').textContent = lines + ' lines';
                    document.getElementById('filenameDisplay').innerHTML = 'Filename: <strong>' + data.filename + '</strong>';
                    
                    showToast('Generated ' + data.filename + ' successfully!', 'success');
                }
            } catch (error) {
                showToast('Error: ' + error.message, 'error');
                document.getElementById('outputArea').value = 'Error: ' + error.message + '\n\nCheck the browser console for more details.';
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
        
        function copyFilename() {
            const name = document.getElementById('commandName').value.trim();
            if (!name) {
                showToast('No command name set', 'error');
                return;
            }
            const filename = name + '.cpp';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(filename).then(() => {
                    showToast('✅ Copied: ' + filename, 'success');
                }).catch(() => {
                    fallbackCopy(filename);
                });
            } else {
                fallbackCopy(filename);
            }
        }
        
        function downloadOutput() {
            const output = document.getElementById('outputArea').value;
            if (!output) {
                showToast('Nothing to download', 'error');
                return;
            }
            
            const name = document.getElementById('commandName').value.trim() || 'command';
            const blob = new Blob([output], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = name + '.cpp';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Download started!', 'success');
        }
        
        // Enter key to generate
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                generate();
            }
        });
        
        // Auto-update filename
        updateFilename();
        
        // Hide OnDisable initially if not looped
        const initialType = document.getElementById('commandType').value;
        if (initialType !== 'looped') {
            document.getElementById('onDisableGroup').style.display = 'none';
        }
        
        // Checkbox visual feedback
        document.querySelectorAll('#includesGroup input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', function() {
                this.closest('label').classList.toggle('checked', this.checked);
            });
            if (cb.checked) {
                cb.closest('label').classList.add('checked');
            }
        });
    </script>
</body>
</html>