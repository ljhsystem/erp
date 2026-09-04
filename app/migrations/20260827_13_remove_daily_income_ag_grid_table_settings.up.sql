SET NAMES utf8mb4;

DELETE FROM system_user_settings
WHERE page_key='institution.income-data.daily-employment'
  AND setting_type='VIEW';
