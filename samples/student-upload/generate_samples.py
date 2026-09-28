"""Generate sample BIT and BCS student upload Excel files for Eventrix bulk registration."""
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment
from pathlib import Path

OUT_DIR = Path(__file__).parent
FACULTY = "Computing, Management and Social Sciences"

SAMPLES = [
    {
        "filename": "BCS_students_2301.xlsx",
        "course": "BCS",
        "year": "2301",
        "department": "Computer Science",
        "start": 23456,
        "count": 15,
        "names": [
            "Amina Juma", "Baraka Mushi", "Catherine Lyimo", "Daniel Msuya", "Esther Mollel",
            "Frankson Tarimo", "Grace Mwenda", "Hassan Omary", "Irene Shayo", "John Massawe",
            "Kelvin Mrosso", "Lucy Ngowi", "Michael Sanga", "Neema Kileo", "Oscar Mwakasege",
        ],
    },
    {
        "filename": "BIT_students_2401.xlsx",
        "course": "BIT",
        "year": "2401",
        "department": "Information Technology",
        "start": 24501,
        "count": 15,
        "names": [
            "Paulo Mrema", "Queen Mcharo", "Rashid Saidi", "Salma Hussein", "Thomas Lyatuu",
            "Upendo Mfinanga", "Victor Mwambene", "Winnie Komba", "Yusuf Abdallah", "Zainab Juma",
            "Abel Mrosso", "Betty Tarimo", "Charles Lyimo", "Diana Massawe", "Emmanuel Sanga",
        ],
    },
]

HEADERS = ["reg_no", "name", "email", "faculty", "department"]
HEADER_FILL = PatternFill("solid", fgColor="1B3D2B")
HEADER_FONT = Font(color="FFFFFF", bold=True)


def build_reg_no(course: str, number: int, year: str) -> str:
    return f"{course}/{number}/{year}/DT"


def email_from_reg(reg_no: str) -> str:
    return reg_no.lower().replace("/", ".") + "@student.kiut.ac.tz"


def create_workbook(sample: dict) -> None:
    wb = Workbook()
    ws = wb.active
    ws.title = "Students"

    for col, header in enumerate(HEADERS, start=1):
        cell = ws.cell(row=1, column=col, value=header)
        cell.fill = HEADER_FILL
        cell.font = HEADER_FONT
        cell.alignment = Alignment(horizontal="center")

    for i in range(sample["count"]):
        number = sample["start"] + i
        reg_no = build_reg_no(sample["course"], number, sample["year"])
        name = sample["names"][i]
        row = i + 2
        ws.cell(row=row, column=1, value=reg_no)
        ws.cell(row=row, column=2, value=name)
        ws.cell(row=row, column=3, value=email_from_reg(reg_no))
        ws.cell(row=row, column=4, value=FACULTY)
        ws.cell(row=row, column=5, value=sample["department"])

    ws.column_dimensions["A"].width = 22
    ws.column_dimensions["B"].width = 22
    ws.column_dimensions["C"].width = 38
    ws.column_dimensions["D"].width = 42
    ws.column_dimensions["E"].width = 24

    out_path = OUT_DIR / sample["filename"]
    wb.save(out_path)
    print(f"Created {out_path}")


if __name__ == "__main__":
    for item in SAMPLES:
        create_workbook(item)
