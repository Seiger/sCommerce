---
id: import-export
title: CSV import and export
sidebar_position: 11
---

# CSV import and export

Open **Integrations → CSV import/export**. Export a fresh file to use as a template. Columns are separated by a semicolon; category-list entries use `||`.

## Category fields

| Field | Single site | Multisite |
| --- | --- | --- |
| `category` — primary category | `2` | `default:2\|\|shop:234` |
| `categories` — additional categories | `240\|\|238` | `240\|\|238` |

`category` contains one primary category ID for a single site. In multisite mode it contains `domain:id` pairs: use the domain keys configured in sMultisite and existing category resource IDs.

`categories` contains additional IDs. All domain primary categories are excluded from this list. The product editor also excludes its primary categories from the additional selection and updates the selection when a primary category changes.

## Older files

The standalone `parent` column has been removed. Before importing an old file, move the former category list to `categories` and the former primary ID to `category`. Domain-specific `category_<domain>` and legacy `parent_<domain>` import fields remain supported.

Both streaming import and import through a temporary table preserve domain scopes and existing product positions in categories.

- [Product categories](./products.md)
- [Admin guide](./index.md)
