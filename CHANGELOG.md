# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.8] - 26 November 2026
### Fixed
- Admin users without the module's own permission could open the grid and see its menu item; the grid page, its menu entry and its Loki component now all require the "Array Grid (Loki)" ACL resource (under Content > Elements)
- Inline edit submissions were passed through to the read-only grid; they are now discarded
- The grid page could hang or run out of memory when composer.yireo.com was slow, unreachable or returned an oversized or malformed response; the request now times out, the response is size-limited and validated, and the package list is cached for an hour
- Require guzzlehttp/guzzle ^7.4.5 instead of allowing Guzzle 6 and vulnerable older 7.x releases, and Loki Admin Components 0.9.1 or newer

### Changed
- The grid controller only accepts GET requests

## [0.0.7] - 03 November 2026
### Fixed
- Beautify README
- Remove PHP 8.1 support, add PHP 8.5 support

## [0.0.6] - 27 September 2025
### Fixed
- Loosen constraints with Loki Admin Components

## [0.0.5] - 17 September 2025
### Fixed
- Refactor into new structure
- Refactor string-type columns into Column-class
- Change flag `allow_actions` into hide_actions

## [0.0.4] - 02 September 2025
### Fixed
- Add CI files

## [0.0.3] - 21 August 2025
### Fixed
- Declare used PHP namespaces
- Add missing `strict_types` declaration
- Add escaping of template code
- Formatting

## [0.0.2] - 24 July 2025
### Fixed
- Update deps with Loki packages
- Use `Loki_AdminComponents` namespace
- Add files via upload
- Allow for cell templates to be defined via XML layout
- Update instructions with `@dev`
- Remove unneeded layout arguments

## [0.0.1] - 16 May 2025
### Added
- Initial release
