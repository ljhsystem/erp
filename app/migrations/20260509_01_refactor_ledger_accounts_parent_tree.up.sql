SET @add_account_category_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `account_category` VARCHAR(50) NULL DEFAULT NULL COMMENT ''Financial statement/reporting category'' AFTER `account_group`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'account_category'
);
PREPARE stmt FROM @add_account_category_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_account_level_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `account_level` INT NOT NULL DEFAULT 1 COMMENT ''Parent tree depth. Root is 1.'' AFTER `account_category`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'account_level'
);
PREPARE stmt FROM @add_account_level_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_is_postable_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `is_postable` CHAR(1) NOT NULL DEFAULT ''Y'' COMMENT ''Y only for leaf accounts that can be used in journal lines'' AFTER `account_level`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'is_postable'
);
PREPARE stmt FROM @add_is_postable_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_status_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''active'' COMMENT ''active/inactive/deleted'' AFTER `is_postable`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'status'
);
PREPARE stmt FROM @add_status_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_full_path_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `full_path` VARCHAR(1000) NULL DEFAULT NULL COMMENT ''Account name path cache'' AFTER `status`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'full_path'
);
PREPARE stmt FROM @add_full_path_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_path_ids_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `path_ids` VARCHAR(2000) NULL DEFAULT NULL COMMENT ''Slash-delimited ancestor id path cache'' AFTER `full_path`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'path_ids'
);
PREPARE stmt FROM @add_path_ids_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_tree_sort_column = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `ledger_accounts` ADD COLUMN `tree_sort` VARCHAR(2000) NULL DEFAULT NULL COMMENT ''Stable parent tree sort key'' AFTER `path_ids`',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND COLUMN_NAME = 'tree_sort'
);
PREPARE stmt FROM @add_tree_sort_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `ledger_accounts`
SET
  `account_category` = COALESCE(NULLIF(`account_category`, ''), `account_group`),
  `account_level` = COALESCE(NULLIF(`account_level`, 0), `level`, 1),
  `is_postable` = CASE WHEN COALESCE(`is_posting`, 1) = 1 THEN 'Y' ELSE 'N' END,
  `status` = CASE
    WHEN `deleted_at` IS NOT NULL THEN 'deleted'
    WHEN COALESCE(`is_active`, 1) = 1 THEN 'active'
    ELSE 'inactive'
  END;

WITH RECURSIVE account_tree AS (
  SELECT
    a.id,
    a.parent_id,
    1 AS depth,
    CAST(CONCAT('/', a.id, '/') AS CHAR(2000)) AS path_ids,
    CAST(a.account_name AS CHAR(1000)) AS full_path,
    CAST(LPAD(COALESCE(a.sort_no, 0), 10, '0') AS CHAR(2000)) AS tree_sort
  FROM ledger_accounts a
  WHERE a.parent_id IS NULL

  UNION ALL

  SELECT
    c.id,
    c.parent_id,
    t.depth + 1,
    CAST(CONCAT(t.path_ids, c.id, '/') AS CHAR(2000)),
    CAST(CONCAT(t.full_path, ' > ', c.account_name) AS CHAR(1000)),
    CAST(CONCAT(t.tree_sort, '/', LPAD(COALESCE(c.sort_no, 0), 10, '0')) AS CHAR(2000))
  FROM ledger_accounts c
  INNER JOIN account_tree t ON t.id = c.parent_id
  WHERE LOCATE(CONCAT('/', c.id, '/'), t.path_ids) = 0
)
UPDATE ledger_accounts a
INNER JOIN account_tree t ON t.id = a.id
SET
  a.account_level = t.depth,
  a.level = t.depth,
  a.path_ids = t.path_ids,
  a.full_path = t.full_path,
  a.tree_sort = t.tree_sort;

UPDATE ledger_accounts parent
SET
  parent.is_postable = 'N',
  parent.is_posting = 0
WHERE EXISTS (
  SELECT 1
  FROM ledger_accounts child
  WHERE child.parent_id = parent.id
    AND child.deleted_at IS NULL
);

UPDATE ledger_accounts leaf
SET leaf.is_posting = CASE WHEN leaf.is_postable = 'Y' THEN 1 ELSE 0 END;

SET @create_account_code_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE UNIQUE INDEX `uq_ledger_accounts_account_code` ON `ledger_accounts` (`account_code`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'uq_ledger_accounts_account_code'
);
PREPARE stmt FROM @create_account_code_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_parent_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_ledger_accounts_parent` ON `ledger_accounts` (`parent_id`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_parent'
);
PREPARE stmt FROM @create_parent_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_tree_sort_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_ledger_accounts_tree_sort` ON `ledger_accounts` (`tree_sort`(255))',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_tree_sort'
);
PREPARE stmt FROM @create_tree_sort_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @create_status_index = (
    SELECT IF(
        COUNT(*) = 0,
        'CREATE INDEX `idx_ledger_accounts_status` ON `ledger_accounts` (`status`)',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_accounts'
      AND INDEX_NAME = 'idx_ledger_accounts_status'
);
PREPARE stmt FROM @create_status_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
