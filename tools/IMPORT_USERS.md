# User Import

The project now has a standalone Excel importer for GLPI users:

```bash
php tools/import_users_from_excel.php --file=path/to/users.xlsx --dry-run
```

UI entry point:

- `Administration -> Users -> Import from spreadsheet`
- button is visible only for users who can create users manually

Supported input formats:

- `.xlsx`
- `.xls`
- `.csv`

Supported columns:

- `login` required
- `firstname`
- `realname`
- `password`
- `email`
- `phone`
- `phone2`
- `mobile`
- `entity`
- `profile`
- `location`
- `language`
- `is_active`
- `comment`

Column aliases are also supported, including Russian labels such as `логин`, `имя`, `фамилия`, `почта`, `профиль`, `локация`, `активен`.

## Import modes

```bash
php tools/import_users_from_excel.php --file=users.xlsx --mode=create
php tools/import_users_from_excel.php --file=users.xlsx --mode=update
php tools/import_users_from_excel.php --file=users.xlsx --mode=upsert
php tools/import_users_from_excel.php --file=users.xlsx --mode=update --update-passwords
```

Mode behavior:

- `create`: only creates missing users
- `update`: only updates existing users
- `upsert`: creates missing users and updates existing ones

## Dry run

Always validate the sheet first:

```bash
php tools/import_users_from_excel.php --file=users.xlsx --dry-run
```

## Template

Generate a ready-to-fill Excel template:

```bash
php tools/generate_user_import_template.php
```

Default output:

```text
tools/templates/user_import_template.xlsx
```

## Current instance mapping

Current profiles in this GLPI instance:

- `1` Self-Service
- `2` Observer
- `3` Admin
- `4` Super-Admin
- `5` Hotliner
- `6` Technician
- `7` Supervisor
- `8` Read-Only

Current entity:

- `0` Головная организация

Current locations:

- `1` Москва HQ
- `2` Санкт-Петербург
- `3` Москва HQ > Серверная
- `4` Москва HQ > Склад IT

## Notes

- For new users, `password` is required.
- For existing users, password is ignored by default. To update passwords, pass `--update-passwords`.
- `profile` and `entity` can be passed either by numeric ID or by exact displayed name.
- `location` can be passed either by numeric ID or by exact name / complete name.
- The importer ensures the selected profile authorization exists in `glpi_profiles_users` and marks it as the default authorization for the user.
