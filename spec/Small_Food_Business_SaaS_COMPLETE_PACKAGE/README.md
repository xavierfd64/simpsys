# SMALL FOOD BUSINESS SAAS — COMPLETE CLAUDE PACKAGE

This is the complete project package for the Small Food Business Management SaaS.

## CURRENT PROJECT SCOPE

The current project covers:

- Public SaaS
- Tenant / Owner application
- Super Admin platform
- Desktop web
- Mobile web
- WordPress-like installation and deployment

A dedicated mobile application is planned **after the current web system is completed and stable**. It is not part of the present implementation scope.

## START HERE

1. Read `00_DOCUMENTATION/01_COMPLETE_SYSTEM_OVERVIEW.md`
2. Read `00_DOCUMENTATION/02_CLAUDE_MASTER_INSTRUCTION.md`
3. Read `00_DOCUMENTATION/03_UI_REFERENCE_INVENTORY.md`
4. Review every image in `01_UI_REFERENCES/`

## INSTALLATION PHILOSOPHY

Installation must be similar to the Sukli sari-sari store system and WordPress:

```text
Upload Files
→ Open Website
→ Installer Detects Uninstalled System
→ Enter Database Details
→ Test Connection
→ Automatic Setup
→ Create Initial Account
→ Finish
```

No normal installer/user should need to manually edit `.env`, import SQL, run Composer/Artisan, edit `.htaccess`, change DocumentRoot, or manually configure the server.

## AUTHORITY ORDER

1. `02_CLAUDE_MASTER_INSTRUCTION.md` — Functional and technical authority
2. `01_COMPLETE_SYSTEM_OVERVIEW.md` — Whole-system map
3. UI reference images — Visual authority

The written specification defines how the system works. The UI references define how it should look.
