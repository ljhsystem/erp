# Route Dictionary

## Route Meta Normalization

- Current compatible keys
  - `key`
  - `name`
  - `description`
  - `category`
  - `auth`
  - `permissions`
  - `skip_permission`
  - `log`
- New compatible keys
  - `page`
  - `page_description`
  - `permission_name`
  - `permission_description`
- Backward compatibility
  - Existing `name`, `description`, `category` are not removed during migration.
  - `Router` and `PermissionRegistry` derive legacy breadcrumb/name values when only the new meta keys are provided.

## Persistence Mapping

- `auth_permissions.permission_key`
  - Runtime permission check key
- `auth_permissions.permission_name`
  - Stored from route `permission_name`
- `auth_permissions.description`
  - Stored from route `permission_description`
- `auth_permissions.category`
  - Stored from route `category` until the legacy column is retired
- `auth_permissions.page_key`
  - Resolved from route breadcrumb metadata and linked to `system_page_registry.page_key`

## Representative Routes

| Key | URL | Notes |
| --- | --- | --- |
| `web.settings.organization.role_permissions` | `/dashboard/settings/organization/permission-assignment` | Canonical permission-assignment page route with legacy permission key compatibility |
| `web.settings.organization.approval` | `/dashboard/settings/organization/approval-template` | Canonical approval-template page route with legacy permission key compatibility |
| `web.settings.organization.employees` | `/dashboard/settings/organization/employee` | Canonical employee page route with legacy route compatibility |
| `web.settings.organization.departments` | `/dashboard/settings/organization/department` | Canonical department page route with legacy route compatibility |
| `web.settings.organization.positions` | `/dashboard/settings/organization/position` | Canonical position page route with legacy route compatibility |
| `web.settings.organization.roles` | `/dashboard/settings/organization/role` | Canonical role page route with legacy route compatibility |
| `api.settings.permission.delete` | `/api/settings/organization/permission/delete` | Hard deletes a permission row and clears linked `auth_role_permissions` mappings |
| `api.settings.rolepermission.list` | `/api/settings/organization/role-permission/list` | Returns role permission tree data for the selected role |
| `api.settings.rolepermission.assign` | `/api/settings/organization/role-permission/assign` | Assigns one child permission to a role |
| `api.settings.rolepermission.remove` | `/api/settings/organization/role-permission/remove` | Removes one child permission from a role |
| `api.settings.rolepermission.reorder` | `/api/settings/organization/role-permission/reorder` | Saves the flattened permission display order into `auth_permissions.sort_no` |
| `web.settings.base-info.brand_logo` | `/dashboard/settings/base-info/brand` | Canonical brand page route with legacy permission key compatibility |
| `api.settings.base-info.brand.list` | `/api/settings/base-info/brand/list` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.detail` | `/api/settings/base-info/brand/detail` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.active-type` | `/api/settings/base-info/brand/active-type` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.save` | `/api/settings/base-info/brand/save` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.delete` | `/api/settings/base-info/brand/purge` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.status` | `/api/settings/base-info/brand/updatestatus` | Initial rollout target for new permission metadata |
| `web.settings.base-info.cover` | `/dashboard/settings/base-info/cover` | Canonical cover page route |
| `web.settings.base-info.clients` | `/dashboard/settings/base-info/client` | Canonical client page route with legacy route key compatibility |
| `web.settings.base-info.projects` | `/dashboard/settings/base-info/project` | Canonical project page route with legacy route key compatibility |
| `web.settings.base-info.accounts` | `/dashboard/settings/base-info/bank-account` | Canonical bank-account page route with legacy route key compatibility |
| `web.settings.base-info.cards` | `/dashboard/settings/base-info/card` | Canonical card page route with legacy route key compatibility |
| `api.settings.base-info.cover.list` | `/api/settings/base-info/cover/list` | Canonical cover permission metadata |
| `api.settings.base-info.cover.detail` | `/api/settings/base-info/cover/detail` | Canonical cover permission metadata |
| `api.settings.base-info.cover.save` | `/api/settings/base-info/cover/save` | Canonical cover permission metadata |
| `api.settings.base-info.cover.delete` | `/api/settings/base-info/cover/delete` | Canonical cover permission metadata |
| `api.settings.base-info.cover.reorder` | `/api/settings/base-info/cover/reorder` | Canonical cover permission metadata |

## SSOT Alias Notes

- `brand`
  - standard domain: `brand`
  - legacy aliases isolated: `brand_logo`, `brand-logo`
  - canonical page key: `settings.base_info.brand`
- `cover`
  - standard domain: `cover`
  - legacy aliases isolated: `coverimage`, `cover-image`, `cover_image`
  - canonical page key: `settings.base_info.cover`
- `client`
  - standard domain: `client`
  - legacy aliases isolated: `clients`
  - canonical page key compatibility: `settings.base_info.clients`
- `project`
  - standard domain: `project`
  - legacy aliases isolated: `projects`
  - canonical page key compatibility: `settings.base_info.projects`
- `bank-account`
  - standard domain: `bank-account`
  - legacy aliases isolated: `bank-accounts`, `bank.account`
  - canonical page key compatibility: `settings.base_info.bank_accounts`
- `card`
  - standard domain: `card`
  - legacy aliases isolated: `cards`
  - canonical page key compatibility: `settings.base_info.cards`
- `work-team`
  - standard domain: `work-team`
  - legacy aliases isolated: `work-teams`, `work_team`
  - canonical page key: `settings.base_info.work_teams`
- `permission-assignment`
  - standard domain: `permission-assignment`
  - legacy aliases isolated: `role_permissions`, `role-permission`, `rolepermission`
  - canonical page key compatibility: `settings.organization.role_permissions`
- `approval-template`
  - standard domain: `approval-template`
  - legacy aliases isolated: `approval`, `approval/template`, `approval/step`, `approval.templates`
  - canonical page key compatibility: `settings.organization.approval`
- `employee`
  - standard domain: `employee`
  - legacy aliases isolated: `employees`
  - canonical page key compatibility: `settings.organization.employees`
- `department`
  - standard domain: `department`
  - legacy aliases isolated: `departments`, `dept`
  - canonical page key compatibility: `settings.organization.departments`
- `position`
  - standard domain: `position`
  - legacy aliases isolated: `positions`, `positions_modal`
  - canonical page key compatibility: `settings.organization.positions`
- `role`
  - standard domain: `role`
  - legacy aliases isolated: `roles`
  - canonical page key compatibility: `settings.organization.roles`

## Current Scope

- Route metadata still supports legacy consumers such as:
  - `PermissionRegistry` sync
  - breadcrumb rendering
  - sitemap rendering
  - permission screen grouping fallback
- The June 2026 permission refactor keeps runtime permission checks on `permission_key` and assignment writes on `permission_id`.
