# RDR2/GTA5 NativeDB Tools

A comprehensive web-based toolkit for managing and converting Red Dead Redemption 2/3 native function data, with tools for list conversion and script generation.

## 📦 Features

### 📋 NativeDB Explorer (index.php)
Browse and search through all RDR2/RDR3 native functions with a modern, responsive interface. Features live search, namespace filtering, and detailed native information display.

### 🔄 List Converter (converter.php)
Convert item lists between multiple formats including JSON, INI, C++, Lua, CSV, PHP arrays, and plain text. Supports importing from URLs or pasted content.

### ⚡ Script Generator (creator.php)
Generate ready-to-use command classes for YimMenuV2, Helix, HorseMenu, and Chronix mod menus. Supports LoopedCommand, PlayerCommand, and basic Command types.

## 🚀 Installation

### 1. Database Setup (Optional)
Note: If you do NOT want to use the databases, set `define('USE_DATABASE', false);` in index.php/gta.php
1. Locate the `rdr3_nativedb_detailed.sql` & `gta5_nativedb_detailed.sql` files in the repository
2. Import them into your MySQL database using phpMyAdmin or command line:
```bash
mysql -u your_username -p your_database < rdr3_nativedb_detailed.sql
```
This will create the `rdr3_nativedb` & `gta5_nativedb` databases with all native function data pre-populated.

### 2. Configuration
Edit the database credentials in `assets/php/connect.php` (No need to fill out table names, it fetches them for you):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');
```

### 3. Web Server Setup
1. Upload all files to your web server (except for .sql files, unless you want to)
2. Ensure your server meets the requirements:
   - PHP 7.4 or higher
   - MySQL/MariaDB
   - PDO extension enabled
   - `allow_url_fopen` enabled (for URL imports in converter)

### 4. File Permissions
Ensure the web server has read access to the PHP files and write access to any temporary directories if needed.

## 📖 Usage Guide

### NativeDB Explorer (index.php)
1. **Search**: Type in the search box to filter natives by name, namespace, hash, or comment
2. **Filter**: Use the namespace dropdown to view natives from a specific namespace
3. **View Details**: Click any native in the list to see its full information including parameters, return type, and comments
4. **Copy**: Use the action buttons to copy hashes or complete Namespace::Native(parameter) combinations

### List Converter (converter.php)
1. **Input**: Paste your list or provide a URL (supports .cpp, .lua, .json, .ini, .txt)
2. **Select Format**: Choose the input format or use "Auto Detect"
3. **Choose Output**: Select your desired output format
4. **Convert**: Click the Convert button
5. **Copy/Download**: Use the Copy or Download buttons to save your converted list

**Supported Input Formats:**
- URL (auto-detects content type)
- JSON (`{"items": ["item1", "item2"]}`)
- INI (`[Section]` key=value)
- C++ (`namespace::function` or `object->method`)
- Lua (`key = "value"` or table assignments)
- Plain Text (one item per line)

**Supported Output Formats:**
- Plain Text (.txt)
- JSON (.json)
- INI (.ini)
- Lua (.lua)
- C++ Array (.cpp)
- CSV (.csv)
- PHP Array (.php)

### Script Generator (creator.php)
1. **Choose Template**: Select a template (Looped Command, Basic Command, Player Command, Vehicle Command)
2. **Fill Details**:
   - Command Name: The internal command ID (e.g., `godmode`)
   - Display Name: User-facing name (e.g., "God Mode")
   - Description: Brief description of what the command does
   - Command Prefix: Optional prefix (e.g., `toggle` + `godmode` = `togglegodmode`)
3. **Select Includes**: Add the required header files for your command
4. **Add Custom Code**: Write your implementation code for the OnTick/OnCall method
5. **Generate**: Click the Generate button
6. **Copy/Download**: Copy or Download the generated output code and add it to your project

**Generated Code Structure:**
```cpp
#include "core/commands/LoopedCommand.hpp"
#include "game/backend/Self.hpp"

namespace YimMenu::Features
{
    class Godmode : public LoopedCommand
    {
        using LoopedCommand::LoopedCommand;

        virtual void OnTick() override
        {
            // Your custom code here
        }

        virtual void OnDisable() override
        {
            // Cleanup code here
        }
    };

    static Godmode _Godmode{"godmode", "God Mode", "Blocks all incoming damage"};
}
```

## 🎯 Supported Mod Menus

The Script Generator creates code compatible with:
- **YimMenuV2** - YimMenu's updated codebase for GTA5 Enhanced
- **Helix** - RDR2 Open Source/Updated Mod Menu Based off YimMenuV2 + HorseMenu (Terminus)
- **HorseMenu (Terminus)** - RDR2 Open Source mod menu based off of YimMenuV2
- **ChronixV2** - Alternative YimMenuV2 mod menu for GTA5 Enhanced

## 💡 Tips

- Use the NativeDB Explorer to find specific native functions you want to implement
- Copy the full signature from the NativeDB to use in your script
- The List Converter is perfect for converting ped lists, vehicle lists, or object hashes between formats
- Use the Script Generator to quickly scaffold new features for your mod menu

## 🔧 Troubleshooting

### Database Connection Failed
- Verify your database credentials in the config section
- Ensure the database server is running
- Check that the `rdr3_nativedb` database exists with the imported data

### Converter URL Import Fails
- Ensure `allow_url_fopen` is enabled in php.ini
- Check if the URL is accessible from your server
- Verify the URL content is in a supported format

### Script Generator Output Issues
- Ensure all required fields are filled (Command Name and Display Name are required)
- Check that the custom code doesn't contain syntax errors
- Verify the selected includes match your code requirements

## 📄 License

This project is for educational purposes only. Use responsibly and in accordance with Rockstar Games' terms of service.

## 🤝 Contributing

Found a bug or want to add a feature? Feel free to submit a pull request or open an issue on the repository.
