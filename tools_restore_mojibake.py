from pathlib import Path
import re

paths = [
    'app/Services/Ledger/TransactionService.php',
    'app/Services/Ledger/ChartAccountService.php',
    'app/Models/System/CoverImageModel.php',
    'app/Services/System/ProjectService.php',
    'app/Models/System/ClientModel.php',
    'app/Models/System/CardModel.php',
    'public/assets/css/pages/dashboard/settings.css',
    'public/assets/js/pages/ledger/journal.js',
    'app/Services/System/CoverImageService.php',
    'app/Services/System/ClientService.php',
    'app/Controllers/Dashboard/Settings/SystemController.php',
    'app/Services/System/BankAccountService.php',
    'app/Controllers/Dashboard/Settings/ProjectController.php',
    'app/Services/Site/TransactionService.php',
    'app/Controllers/Dashboard/Settings/CoverController.php',
    'app/Controllers/Dashboard/Settings/ClientController.php',
    'app/Controllers/Dashboard/Settings/BankAccountController.php',
    'app/Services/Auth/RegisterService.php',
    'public/assets/css/pages/dashboard/settings/employee.css',
    'public/assets/css/pages/dashboard/settings/cover.css',
    'public/assets/css/pages/layout/layout.css',
    'app/Services/Backup/DatabaseBackupService.php',
    'public/assets/js/pages/dashboard/settings/organization/roles.js',
    'public/assets/js/pages/dashboard/settings/organization/positions.js',
    'public/assets/js/pages/dashboard/settings/organization/permissions.js',
    'public/assets/js/pages/dashboard/settings/organization/employees.js',
    'public/assets/js/pages/dashboard/settings/organization/departments.js',
]

suspects = [
    '筌', '熬', '濡', '怨', '鍮', '嶺', '繞', '袁', '苑', '甕', '嚥',
    '餓', '癰', '疫', '雅', '獄', '貫', '源', '醫', '뤾', '돦', '뫗',
    '덈펲', '꾩', '먮뒗', '踰덊샇', '궛', '꾨씫', '꾩쟾', '꾩껜',
    '꾩씠', '鍮꾪솢',
]
suspect_re = re.compile('|'.join(re.escape(s) for s in suspects))
run_re = re.compile(
    r"[\u3000-\u9fff\uac00-\ud7a3\uff00-\uffef?\u0080-\u04ff]+"
    r"(?:[\s/.,:;!()\[\]{}<>\-+*='\"`|&%#@~·]*"
    r"[\u3000-\u9fff\uac00-\ud7a3\uff00-\uffef?\u0080-\u04ff]+)*"
)


def score(value: str) -> int:
    hangul = sum(1 for ch in value if '\uac00' <= ch <= '\ud7a3')
    return hangul - 4 * len(suspect_re.findall(value)) - 5 * value.count('�')


def recover_run(match: re.Match[str]) -> str:
    source = match.group(0)
    if not suspect_re.search(source):
        return source

    try:
        recovered = source.encode('cp949').decode('utf-8')
    except UnicodeError:
        try:
            recovered = source.encode('cp949', errors='ignore').decode('utf-8', errors='ignore')
        except UnicodeError:
            return source

    return recovered if score(recovered) > score(source) else source


changed = []
for raw_path in paths:
    path = Path(raw_path)
    if not path.exists():
        continue

    original = path.read_text(encoding='utf-8', errors='replace')
    restored = run_re.sub(recover_run, original)
    if restored != original:
        path.write_text(restored, encoding='utf-8', newline='')
        changed.append(raw_path)

print('\n'.join(changed))
