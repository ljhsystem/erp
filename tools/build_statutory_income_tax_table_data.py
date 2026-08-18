import argparse
import json
import os
import re


import openpyxl
import pdfplumber


parser = argparse.ArgumentParser(description="공식 근로소득 간이세액표 원본을 운영 적재 JSON으로 정규화합니다.")
parser.add_argument("source_directory", help="공식 PDF/XLSX 원본을 내려받은 디렉터리")
args = parser.parse_args()
SOURCE_DIRECTORY = os.path.abspath(args.source_directory)
OUTPUT_PATH = os.path.join(
    os.path.dirname(__file__),
    "data",
    "statutory-income-tax-tables.json",
)
DEPENDENT_COUNTS = [str(value) for value in range(1, 12)]


def to_number(value):
    if value is None:
        return None
    normalized = str(value).replace(",", "").replace("천원", "").strip()
    if normalized == "-":
        return 0
    return int(normalized)


def to_contract_row(salary_from, salary_to, taxes):
    return {
        "salary_from": salary_from,
        "salary_to": salary_to,
        "tax_by_dependents": dict(zip(DEPENDENT_COUNTS, taxes)),
    }


def parse_pdf(filename):
    path = os.path.join(SOURCE_DIRECTORY, filename)
    rows = []
    anchor = None
    line_pattern = re.compile(r"^([\d,]+)\s+([\d,]+)\s+(.+)$")
    with pdfplumber.open(path) as pdf:
        for page in pdf.pages:
            page_text = page.extract_text() or ""
            for line in page_text.splitlines():
                anchor_match = re.match(r"^10,000천원\s+(.+)$", line.strip())
                if anchor_match:
                    anchor_values = anchor_match.group(1).split()
                    if len(anchor_values) == 11 and all(re.fullmatch(r"[\d,]+", value) for value in anchor_values):
                        anchor = to_contract_row(
                            10_000_000,
                            None,
                            [to_number(value) for value in anchor_values],
                        )
                match = line_pattern.match(line.strip())
                if not match:
                    continue
                values = match.group(3).split()
                if len(values) != 11 or not all(
                    value == "-" or re.fullmatch(r"[\d,]+", value)
                    for value in values
                ):
                    continue
                rows.append(
                    to_contract_row(
                        to_number(match.group(1)) * 1000,
                        to_number(match.group(2)) * 1000,
                        [to_number(value) for value in values],
                    )
                )
    rows_by_start = {row["salary_from"]: row for row in rows}
    result = [rows_by_start[key] for key in sorted(rows_by_start)]
    if len(result) != 646 or anchor is None:
        raise RuntimeError(f"{filename}: expected 646 interval rows and one anchor row")
    for previous, current in zip(result, result[1:]):
        if previous["salary_to"] != current["salary_from"]:
            raise RuntimeError(f"{filename}: discontinuous salary intervals")
    result.append(anchor)
    return result


def parse_xlsx(filename):
    path = os.path.join(SOURCE_DIRECTORY, filename)
    workbook = openpyxl.load_workbook(path, data_only=False, read_only=True)
    sheet = workbook.active
    tax_columns = [3, 4, 5, 7, 9, 11, 13, 15, 17, 19, 21]
    rows = []
    source_rows = list(sheet.iter_rows(min_row=6, max_row=652, min_col=1, max_col=21, values_only=True))
    for source_row in source_rows[:-1]:
        salary_from = to_number(source_row[0]) * 1000
        salary_to = to_number(source_row[1]) * 1000
        taxes = [to_number(source_row[column - 1]) for column in tax_columns]
        rows.append(to_contract_row(salary_from, salary_to, taxes))
    anchor_taxes = [to_number(source_rows[-1][column - 1]) for column in tax_columns]
    rows.append(to_contract_row(10_000_000, None, anchor_taxes))
    if len(rows) != 647:
        raise RuntimeError(f"{filename}: expected 647 rows")
    return rows


payload = {
    "2014": parse_pdf("income-tax-table-2014.bin"),
    "2017": parse_pdf("income-tax-table-2017-law.pdf"),
    "2020": parse_pdf("income-tax-table-2020-law.pdf"),
    "2023": parse_xlsx("income-tax-table-2023.xlsx"),
}

os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)
with open(OUTPUT_PATH, "w", encoding="utf-8", newline="\n") as output:
    json.dump(payload, output, ensure_ascii=False, separators=(",", ":"))
    output.write("\n")

for year, rows in payload.items():
    print(year, len(rows), rows[0]["salary_from"], rows[-2]["salary_to"])
