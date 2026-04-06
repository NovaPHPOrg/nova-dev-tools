# Nova Dev Tools

Nova Dev Tools is a PHP CLI toolkit for bootstrapping and maintaining Nova projects.

## Features

- Initialize a new Nova project (`init`)
- Manage framework plugins (`plugin`)
- Manage Nova UI components (`ui`)
- Manage local development server module (`serve`)
- Run project tests (`test`)
- Build release archives (`build`)
- Update and refresh git submodules (`update`, `refresh`)
- Format PHP code (`fix`)

## Requirements

- PHP `>= 8.3`
- Git
- Composer (optional, required only when project initialization enables Composer)

## Quick Start

```bash
php src/start.php help
```

Build PHAR package:

```bash
php build.php
php nova.phar help
```

## Usage

### Core Commands

```bash
php nova.phar version
php nova.phar init
php nova.phar build
php nova.phar test list
php nova.phar test all
php nova.phar test run <test-name>
php nova.phar fix
php nova.phar serve start
php nova.phar update
php nova.phar refresh
```

### Test Commands

```bash
php nova.phar test list
php nova.phar test all
php nova.phar test run <test-name>
php nova.phar test run <test-name> <another-test-name>
```

### Serve Commands

```bash
php nova.phar serve start
php nova.phar serve stop
php nova.phar serve restart
php nova.phar serve reload
php nova.phar serve status
```

`serve` is embedded during `php nova.phar init`.

### Plugin Commands

```bash
php nova.phar plugin list
php nova.phar plugin add <plugin-name>
php nova.phar plugin remove <plugin-name>
```

### UI Commands

```bash
php nova.phar ui init
php nova.phar ui list
php nova.phar ui add <component-name>
php nova.phar ui remove <component-name>
```

## Project Structure

```text
src/
  start.php                # CLI entry point
  commands/                # Command implementations
  init/project/            # Project template
  init/ui/                 # UI template
build.php                  # Build script for nova.phar
nova.phar                  # Built PHAR artifact
```

## Development

Run from source during development:

```bash
php src/start.php help
```

Rebuild PHAR after changes:

```bash
php build.php
```

## Docs

Detailed command usage guide is available in `Nova-Usage`.

## License

MIT
