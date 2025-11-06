# MI Data Exports

Early-stage WordPress plugin intended to help export data from MonsterInsights Lite. Expect rapid iteration.

## Install
- Clone or download this repository into your WordPress `wp-content/plugins/` directory.
- Ensure the main file is named `mi-helper-reports.php` (already included).
- Activate the plugin from the WordPress admin Plugins screen.

## Requirements
- WordPress >= 5.8
- PHP >= 7.4

## Development
- Main plugin bootstrap: `mi-helper-reports.php`
- Core class: `includes/class-mi-helper-reports.php`
- Uninstall handler: `uninstall.php` (currently a placeholder)

### Local workflow
- Make changes on a feature branch.
- Open a Pull Request.
- CI runs PHP linting on push/PR.

## Roadmap
- Define export endpoints/actions.
- Add nonce-protected admin actions to trigger exports.
- Provide CSV/JSON export formats.
- Add settings page and permissions checks.

## Contributing
See `CONTRIBUTING.md` for guidelines.

## License
MIT — see `LICENSE`.
